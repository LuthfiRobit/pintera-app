<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesSpmbTenant;
use App\Mail\PendaftaranBerhasilMail;
use App\Models\AkunPendaftar;
use App\Models\AlamatCalonMurid;
use App\Models\CalonMurid;
use App\Models\DataKhususCalonMurid;
use App\Models\DataPeriodikCalonMurid;
use App\Models\DokumenPendaftaran;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JawabanFormulirPendaftaran;
use App\Models\KeluargaCalonMurid;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Services\KodePendaftaranGenerator;
use App\Services\PendaftaranWizardSession;
use App\Services\TagihanGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class ReviewSubmitController extends BaseController
{
    use ResolvesSpmbTenant;

    private const MAKS_PERCOBAAN_KODE = 5;

    public function show(string $lembagaSlug, JalurPpdb $jalur, PendaftaranWizardSession $wizardSession): View|RedirectResponse
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);
        $session = $wizardSession->get($lembaga, $jalur);

        if ($redirect = $this->redirectJikaSesiBelumLengkap($lembaga, $jalur, $session)) {
            return $redirect;
        }

        return view('spmb.review', ['lembaga' => $lembaga, 'jalur' => $jalur, 'session' => $session]);
    }

    public function submit(
        string $lembagaSlug,
        JalurPpdb $jalur,
        PendaftaranWizardSession $wizardSession,
        KodePendaftaranGenerator $kodeGenerator,
        TagihanGenerator $tagihanGenerator
    ): RedirectResponse {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);
        $session = $wizardSession->get($lembaga, $jalur);

        if ($redirect = $this->redirectJikaSesiBelumLengkap($lembaga, $jalur, $session)) {
            return $redirect;
        }

        try {
            $pendaftaran = DB::transaction(function () use ($lembaga, $jalur, $session, $kodeGenerator) {
                $gelombang = $this->resolveGelombangAktifUntukJalur($lembaga, $jalur);
                $tahunAjaran = $gelombang->tahunAjaran;

                $calonMurid = CalonMurid::updateOrCreate(
                    ['nik_hash' => hash('sha256', $session['nik'])],
                    array_merge(['yayasan_id' => $lembaga->yayasan_id, 'nik' => $session['nik']], $session['data_pribadi'])
                );

                AlamatCalonMurid::updateOrCreate(['calon_murid_id' => $calonMurid->id], $session['alamat']);

                KeluargaCalonMurid::where('calon_murid_id', $calonMurid->id)->delete();
                foreach ($session['keluarga'] as $anggota) {
                    KeluargaCalonMurid::create(array_merge(['calon_murid_id' => $calonMurid->id], $anggota));
                }

                if (! empty($session['data_periodik'])) {
                    DataPeriodikCalonMurid::updateOrCreate(['calon_murid_id' => $calonMurid->id], $session['data_periodik']);
                }
                if (! empty($session['data_khusus'])) {
                    DataKhususCalonMurid::updateOrCreate(['calon_murid_id' => $calonMurid->id], $session['data_khusus']);
                }

                $pendaftaran = $this->buatPendaftaranDenganRetryKode(
                    $calonMurid, $lembaga, $tahunAjaran, $jalur, $gelombang, $session, $kodeGenerator
                );

                foreach ($session['jawaban_formulir'] ?? [] as $fieldId => $nilai) {
                    JawabanFormulirPendaftaran::create([
                        'pendaftaran_id' => $pendaftaran->id, 'formulir_field_id' => $fieldId, 'nilai' => $nilai,
                    ]);
                }

                foreach ($session['dokumen'] ?? [] as $syaratId => $berkas) {
                    DokumenPendaftaran::create([
                        'pendaftaran_id' => $pendaftaran->id,
                        'dokumen_syarat_ppdb_id' => $syaratId,
                        'file_path' => $berkas['file_path'],
                        'nama_file_asli' => $berkas['nama_file_asli'],
                        'mime_type' => $berkas['mime_type'],
                        'ukuran_bytes' => $berkas['ukuran_bytes'],
                    ]);
                }

                return $pendaftaran;
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'pendaftaran_calon_murid_id_gelombang_ppdb_id_unique')) {
                return redirect()->route('spmb.review', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id])
                    ->withErrors(['submit' => 'Anda sudah terdaftar untuk gelombang ini. Silakan cek status pendaftaran Anda.']);
            }

            throw $exception;
        }

        $akun = AkunPendaftar::where('email', $pendaftaran->email_pendaftaran)
            ->whereNotNull('email_verified_at')
            ->first();

        if ($akun) {
            $pendaftaran->update(['akun_pendaftar_id' => $akun->id]);
        }

        $this->pindahkanDokumenKeLokasiFinal($pendaftaran);

        $tagihanGenerator->generate($pendaftaran, 'pendaftaran');

        Mail::to($pendaftaran->email_pendaftaran)->send(new PendaftaranBerhasilMail($pendaftaran));

        $wizardSession->clear($lembaga, $jalur);

        return redirect()->route('spmb.berhasil', [
            'lembagaSlug' => $lembaga->slug,
            'kodePendaftaran' => $pendaftaran->kode_pendaftaran,
            'email' => $pendaftaran->email_pendaftaran,
        ]);
    }

    private function redirectJikaSesiBelumLengkap(Lembaga $lembaga, JalurPpdb $jalur, array $session): ?RedirectResponse
    {
        if (empty($session['email_pendaftaran'])) {
            return redirect()->route('spmb.mulai', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id])
                ->withErrors(['sesi' => 'Sesi Anda telah berakhir. Silakan mulai dari awal.']);
        }

        foreach (['nik', 'data_pribadi', 'alamat', 'keluarga'] as $kunci) {
            if (empty($session[$kunci])) {
                return redirect()->route('portal.wizard.data-diri')
                    ->withErrors(['sesi' => 'Data belum lengkap. Silakan lengkapi data diri terlebih dahulu.']);
            }
        }

        return null;
    }

    /**
     * Runs after the DB transaction commits, not inside it — Storage::move() is not
     * rollback-safe, so keeping it out of the transaction means a failed move never
     * orphans a file against a rolled-back Pendaftaran, and never leaves the wizard
     * session pointing at a tmp path that a mid-transaction failure already invalidated.
     * The Pendaftaran row itself is already durably committed by the time this runs.
     */
    private function pindahkanDokumenKeLokasiFinal(Pendaftaran $pendaftaran): void
    {
        foreach ($pendaftaran->dokumen as $dokumen) {
            if (! Storage::disk('public')->exists($dokumen->file_path)) {
                continue;
            }

            $tujuan = 'pendaftaran/'.$pendaftaran->id.'/'.basename($dokumen->file_path);
            Storage::disk('public')->move($dokumen->file_path, $tujuan);
            $dokumen->update(['file_path' => $tujuan]);
        }
    }

    /**
     * KodePendaftaranGenerator::generate() checks for an existing row and returns a
     * candidate code, but a second request can insert the same code in the gap between
     * that check and this create() call. Retry with a freshly generated code when the
     * (lembaga_id, kode_pendaftaran) unique constraint is the one that failed — any other
     * constraint violation (e.g. a genuine duplicate calon_murid+gelombang registration)
     * is a real, different condition and must propagate to the caller in submit().
     */
    private function buatPendaftaranDenganRetryKode(
        CalonMurid $calonMurid,
        Lembaga $lembaga,
        TahunAjaran $tahunAjaran,
        JalurPpdb $jalur,
        GelombangPpdb $gelombang,
        array $session,
        KodePendaftaranGenerator $kodeGenerator
    ): Pendaftaran {
        for ($percobaan = 0; $percobaan < self::MAKS_PERCOBAAN_KODE; $percobaan++) {
            $kode = $kodeGenerator->generate($lembaga->id);

            try {
                return Pendaftaran::create([
                    'calon_murid_id' => $calonMurid->id,
                    'lembaga_id' => $lembaga->id,
                    'tahun_ajaran_id' => $tahunAjaran->id,
                    'jalur_ppdb_id' => $jalur->id,
                    'gelombang_ppdb_id' => $gelombang->id,
                    'kode_pendaftaran' => $kode,
                    'email_pendaftaran' => $session['email_pendaftaran'],
                    'submitted_at' => now(),
                ]);
            } catch (QueryException $exception) {
                $adalahBentrokKode = str_contains($exception->getMessage(), 'pendaftaran_lembaga_id_kode_pendaftaran_unique');

                if (! $adalahBentrokKode) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Gagal membuat pendaftaran setelah '.self::MAKS_PERCOBAAN_KODE.' percobaan kode.');
    }

    public function berhasil(Request $request, string $lembagaSlug, string $kodePendaftaran): View
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $pendaftaran = Pendaftaran::where('lembaga_id', $lembaga->id)
            ->where('kode_pendaftaran', $kodePendaftaran)
            ->where('email_pendaftaran', $request->query('email'))
            ->firstOrFail();

        return view('spmb.berhasil', ['lembaga' => $lembaga, 'pendaftaran' => $pendaftaran]);
    }
}
