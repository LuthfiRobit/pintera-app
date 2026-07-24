<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Spmb\Concerns\ResolvesWizardContext;
use App\Http\Controllers\Spmb\PortalController as SpmbPortalController;
use App\Models\DokumenSyaratPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Services\PendaftaranWizardSession;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    use ResolvesWizardContext;

    private const LANGKAH_WIZARD = [
        ['key' => 'data_pribadi', 'label' => 'Data Diri', 'route' => 'portal.wizard.data-diri'],
        ['key' => 'jawaban_formulir', 'label' => 'Formulir Jalur', 'route' => 'portal.wizard.formulir-tambahan'],
        ['key' => 'dokumen', 'label' => 'Dokumen', 'route' => 'portal.wizard.dokumen'],
        ['key' => null, 'label' => 'Review', 'route' => 'portal.wizard.review'],
    ];

    public function index(PendaftaranWizardSession $wizardSession): View
    {
        $akun = Auth::guard('portal')->user();

        $pendaftaranList = $akun->pendaftaran()
            ->with(['calonMurid', 'lembaga', 'jalurPpdb', 'gelombangPpdb'])
            ->latest('submitted_at')
            ->get();

        $lembaga = session('spmb_pilihan.lembaga_id')
            ? Lembaga::find(session('spmb_pilihan.lembaga_id'))
            : null;
        $jalur = session('spmb_pilihan.jalur_id')
            ? JalurPpdb::find(session('spmb_pilihan.jalur_id'))
            : null;

        $progress = null;

        if ($lembaga && $jalur) {
            if ($this->sudahDidaftarkan($pendaftaranList, $lembaga, $jalur)) {
                session()->forget('spmb_pilihan');
                session()->flash('status', 'Kamu sudah terdaftar pada jalur ini. Lihat riwayat pendaftaranmu di bawah.');
            } elseif ($this->punyaPendaftaranMenungguKeputusan($pendaftaranList)) {
                session()->forget('spmb_pilihan');
                session()->flash('status', 'Kamu masih memiliki pendaftaran yang menunggu keputusan. Selesaikan itu dulu sebelum mendaftar jalur baru.');
            } else {
                $progress = $this->bangunKemajuanWizard($lembaga, $jalur, $wizardSession);
            }
        }

        if (! $progress) {
            $menunggu = $pendaftaranList->firstWhere('status', 'menunggu_verifikasi');
            if ($menunggu) {
                $progress = $this->bangunRingkasanMenungguVerifikasi($menunggu);
            }
        }

        return view('portal.dashboard', [
            'pendaftaranList' => $pendaftaranList,
            'progress' => $progress,
        ]);
    }

    private function sudahDidaftarkan($pendaftaranList, Lembaga $lembaga, JalurPpdb $jalur): bool
    {
        return $pendaftaranList->contains(
            fn (Pendaftaran $p) => $p->lembaga_id === $lembaga->id && $p->jalur_ppdb_id === $jalur->id
        );
    }

    private function punyaPendaftaranMenungguKeputusan($pendaftaranList): bool
    {
        return $pendaftaranList->contains(fn (Pendaftaran $p) => $p->status === 'menunggu_verifikasi');
    }

    /**
     * Builds the hero card for a wizard IN PROGRESS: no `Pendaftaran` row exists yet
     * (only created on final wizard submit), so every field here is read from the
     * SESSION only — `spmb_wizard.{lembagaId}.{jalurId}`, written by each wizard step's
     * own `PendaftaranWizardSession::put()` call (`data_pribadi`, `jawaban_formulir`,
     * `dokumen`). No `Tagihan` exists yet either, so the biaya stat shows the nominal
     * amount only, without a payment-status claim.
     */
    private function bangunKemajuanWizard(Lembaga $lembaga, JalurPpdb $jalur, PendaftaranWizardSession $wizardSession): array
    {
        $data = $wizardSession->get($lembaga, $jalur);

        $langkah = [];
        $selesai = 0;
        $aksiUrl = route(self::LANGKAH_WIZARD[0]['route']);
        $sudahTemukanBerikutnya = false;

        foreach (self::LANGKAH_WIZARD as $l) {
            $done = $l['key'] !== null && isset($data[$l['key']]);
            if ($done) {
                $selesai++;
            } elseif (! $sudahTemukanBerikutnya) {
                $aksiUrl = route($l['route']);
                $sudahTemukanBerikutnya = true;
            }
            $langkah[] = ['label' => $l['label'], 'done' => $done];
        }
        foreach ($langkah as $i => &$l) {
            $l['active'] = ! $l['done'] && $i === $selesai;
        }
        unset($l);

        $gelombang = SpmbPortalController::cariGelombangAktif($lembaga);
        if ($gelombang && $gelombang->tahun_ajaran_id !== $jalur->tahun_ajaran_id) {
            $gelombang = null;
        }

        return [
            'lembaga' => $lembaga,
            'jalur' => $jalur,
            'judul' => 'Lengkapi Data Pendaftaran',
            'persen' => (int) round($selesai / count(self::LANGKAH_WIZARD) * 100),
            'langkah' => $langkah,
            'aksi_label' => 'Lanjutkan Pengisian',
            'aksi_url' => $aksiUrl,
            'dokumen_label' => 'Dokumen Terupload',
            'dokumen_terisi' => count($data['dokumen'] ?? []),
            'dokumen_total' => DokumenSyaratPpdb::where('jalur_ppdb_id', $jalur->id)->count(),
            'gelombang' => $gelombang,
            'biaya_nominal' => $this->resolveNominalPendaftaran($lembaga, $jalur)?->nominal,
            'biaya_keterangan' => 'Ditagihkan setelah selesai',
            'biaya_warn' => false,
        ];
    }

    /**
     * Builds the same hero card shape for a Pendaftaran that has already been SUBMITTED
     * and is awaiting a decision (`status = menunggu_verifikasi`). Unlike the in-progress
     * card, this one CAN show real per-document verification status and real payment
     * status, since a `Pendaftaran`, its `DokumenPendaftaran` rows, and its `Tagihan`
     * already exist in the database at this point.
     */
    private function bangunRingkasanMenungguVerifikasi(Pendaftaran $pendaftaran): array
    {
        $langkah = collect(self::LANGKAH_WIZARD)
            ->map(fn ($l) => ['label' => $l['label'], 'done' => true, 'active' => false])
            ->all();

        $totalDokumen = $pendaftaran->dokumen()->count();
        $terverifikasi = $pendaftaran->dokumen()->where('status_verifikasi', 'diterima')->count();

        $tagihan = $pendaftaran->tagihan()->where('kategori', 'pendaftaran')->first();
        [$biayaKeterangan, $biayaWarn] = match ($tagihan?->status) {
            'lunas' => ['Lunas', false],
            'dicicil' => ['Dicicil', false],
            'belum_bayar' => ['Belum Dibayar', true],
            default => ['Belum Ditagihkan', false],
        };

        return [
            'lembaga' => $pendaftaran->lembaga,
            'jalur' => $pendaftaran->jalurPpdb,
            'judul' => 'Menunggu Verifikasi',
            'persen' => 100,
            'langkah' => $langkah,
            'aksi_label' => 'Unduh Bukti Pendaftaran',
            'aksi_url' => route('portal.pendaftaran.bukti', $pendaftaran),
            'dokumen_label' => 'Dokumen Terverifikasi',
            'dokumen_terisi' => $terverifikasi,
            'dokumen_total' => $totalDokumen,
            'gelombang' => $pendaftaran->gelombangPpdb,
            'biaya_nominal' => $tagihan?->total_tagihan,
            'biaya_keterangan' => $biayaKeterangan,
            'biaya_warn' => $biayaWarn,
        ];
    }
}
