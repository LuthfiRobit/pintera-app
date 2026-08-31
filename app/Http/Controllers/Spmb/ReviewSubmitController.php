<?php

namespace App\Http\Controllers\Spmb;

use App\Domains\Identity\Actions\CreatePersonAction;
use App\Domains\Identity\Actions\UpdatePersonAction;
use App\Domains\Identity\Exceptions\PersonAlreadyExistsException;
use App\Http\Controllers\Spmb\Concerns\ResolvesWizardContext;
use App\Mail\PendaftaranBerhasilMail;
use App\Models\AkunPendaftar;
use App\Models\AlamatCalonMurid;
use App\Models\CalonMurid;
use App\Models\DataKhususCalonMurid;
use App\Models\DataPeriodikCalonMurid;
use App\Models\DokumenPendaftaran;
use App\Models\DokumenSyaratPpdb;
use App\Models\FormulirField;
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
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class ReviewSubmitController extends BaseController
{
    use ResolvesWizardContext;

    private const MAKS_PERCOBAAN_KODE = 5;

    public function show(PendaftaranWizardSession $wizardSession): View|RedirectResponse
    {
        [$lembaga, $jalur] = $this->resolveWizardContext();
        $session = $wizardSession->get($lembaga, $jalur);

        if ($redirect = $this->redirectJikaSesiBelumLengkap($session)) {
            return $redirect;
        }

        $formulirFieldList = FormulirField::where('jalur_ppdb_id', $jalur->id)->orderBy('urutan')->get();
        $dokumenSyaratList = DokumenSyaratPpdb::where('jalur_ppdb_id', $jalur->id)->orderBy('urutan')->get();
        $nominal = $this->resolveNominalPendaftaran($lembaga, $jalur);

        return view('spmb.review', [
            'lembaga' => $lembaga, 'jalur' => $jalur, 'session' => $session,
            'formulirFieldList' => $formulirFieldList, 'dokumenSyaratList' => $dokumenSyaratList, 'nominal' => $nominal,
        ]);
    }

    public function submit(
        PendaftaranWizardSession $wizardSession,
        KodePendaftaranGenerator $kodeGenerator,
        TagihanGenerator $tagihanGenerator
    ): RedirectResponse {
        [$lembaga, $jalur] = $this->resolveWizardContext();
        $session = $wizardSession->get($lembaga, $jalur);

        if ($redirect = $this->redirectJikaSesiBelumLengkap($session)) {
            return $redirect;
        }

        /** @var AkunPendaftar $akun */
        $akun = Auth::guard('portal')->user();

        try {
            $pendaftaran = DB::transaction(function () use ($lembaga, $jalur, $session, $kodeGenerator, $akun) {
                $gelombang = $this->resolveGelombangAktifUntukJalur($lembaga, $jalur);
                $tahunAjaran = $gelombang->tahunAjaran;

                try {
                    $person = app(CreatePersonAction::class)->execute(
                        identityData: [
                            'nama_lengkap' => $session['data_pribadi']['nama_lengkap'],
                            'nik' => $session['nik'],
                            'jenis_kelamin' => $session['data_pribadi']['jenis_kelamin'] ?? null,
                            'tempat_lahir' => $session['data_pribadi']['tempat_lahir'] ?? null,
                            'tanggal_lahir' => $session['data_pribadi']['tanggal_lahir'] ?? null,
                            'agama' => $session['data_pribadi']['agama'] ?? null,
                            'no_hp' => $session['data_pribadi']['no_telepon'] ?? null,
                        ],
                        lembagaId: $lembaga->id,
                        actingYayasanId: $lembaga->yayasan_id,
                    );
                } catch (PersonAlreadyExistsException $e) {
                    $person = $e->existing;
                    app(UpdatePersonAction::class)->execute($person, [
                        'nama_lengkap' => $session['data_pribadi']['nama_lengkap'],
                        'jenis_kelamin' => $session['data_pribadi']['jenis_kelamin'] ?? null,
                        'tempat_lahir' => $session['data_pribadi']['tempat_lahir'] ?? null,
                        'tanggal_lahir' => $session['data_pribadi']['tanggal_lahir'] ?? null,
                        'agama' => $session['data_pribadi']['agama'] ?? null,
                        'no_hp' => $session['data_pribadi']['no_telepon'] ?? null,
                    ]);
                }

                $calonMurid = CalonMurid::where('person_id', $person->id)->first();
                if (! $calonMurid) {
                    $calonMurid = CalonMurid::create([
                        'person_id' => $person->id,
                        'yayasan_id' => $lembaga->yayasan_id,
                        'nisn' => $session['data_pribadi']['nisn'] ?? null,
                        'no_kk' => $session['data_pribadi']['no_kk'] ?? null,
                        'golongan_darah' => $session['data_pribadi']['golongan_darah'] ?? null,
                    ]);
                } else {
                    $calonMurid->update([
                        'person_id' => $person->id,
                        'nisn' => $session['data_pribadi']['nisn'] ?? $calonMurid->nisn,
                        'no_kk' => $session['data_pribadi']['no_kk'] ?? $calonMurid->no_kk,
                        'golongan_darah' => $session['data_pribadi']['golongan_darah'] ?? $calonMurid->golongan_darah,
                    ]);
                }

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
                    $calonMurid, $lembaga, $tahunAjaran, $jalur, $gelombang, $kodeGenerator, $akun
                );

                foreach ($session['jawaban_formulir'] ?? [] as $fieldId => $nilai) {
                    // A 'file'-type field's answer is the enriched array FormulirTambahanController
                    // wrote to session (file_path/nama_file_asli/mime_type/ukuran_bytes) — everything
                    // else is a plain scalar string, same as before.
                    if (is_array($nilai)) {
                        JawabanFormulirPendaftaran::create([
                            'pendaftaran_id' => $pendaftaran->id,
                            'formulir_field_id' => $fieldId,
                            'nilai' => $nilai['file_path'],
                            'nama_file_asli' => $nilai['nama_file_asli'],
                            'mime_type' => $nilai['mime_type'],
                            'ukuran_bytes' => $nilai['ukuran_bytes'],
                        ]);

                        continue;
                    }

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
                return redirect()->route('portal.wizard.review')
                    ->withErrors(['submit' => 'Anda sudah terdaftar untuk gelombang ini. Silakan cek status pendaftaran Anda.']);
            }

            throw $exception;
        }

        $this->pindahkanDokumenKeLokasiFinal($pendaftaran);
        $this->pindahkanJawabanFileKeLokasiFinal($pendaftaran);

        $tagihanGenerator->generate($pendaftaran, 'pendaftaran');

        Mail::to($pendaftaran->email_pendaftaran)->send(new PendaftaranBerhasilMail($pendaftaran));

        $wizardSession->clear($lembaga, $jalur);

        return redirect()->route('portal.wizard.berhasil', ['pendaftaran' => $pendaftaran]);
    }

    private function redirectJikaSesiBelumLengkap(array $session): ?RedirectResponse
    {
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
     * Sibling of pindahkanDokumenKeLokasiFinal() for the other place a file can land
     * in a submission — a 'file'-type dynamic formulir field's answer. Same rollback-
     * safety reasoning: runs after the transaction commits, keyed off nama_file_asli
     * being set (the marker that this jawaban is a file answer, not a plain scalar).
     */
    private function pindahkanJawabanFileKeLokasiFinal(Pendaftaran $pendaftaran): void
    {
        foreach ($pendaftaran->jawabanFormulir as $jawaban) {
            if (! $jawaban->nama_file_asli || ! Storage::disk('public')->exists($jawaban->nilai)) {
                continue;
            }

            $tujuan = 'pendaftaran/'.$pendaftaran->id.'/'.basename($jawaban->nilai);
            Storage::disk('public')->move($jawaban->nilai, $tujuan);
            $jawaban->update(['nilai' => $tujuan]);
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
        KodePendaftaranGenerator $kodeGenerator,
        AkunPendaftar $akun
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
                    'akun_pendaftar_id' => $akun->id,
                    'email_pendaftaran' => $akun->email,
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

    public function berhasil(Pendaftaran $pendaftaran): View
    {
        abort_unless($pendaftaran->akun_pendaftar_id === Auth::guard('portal')->user()->id, 404);

        return view('spmb.berhasil', ['pendaftaran' => $pendaftaran]);
    }
}
