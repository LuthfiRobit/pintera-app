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
     * Builds the wizard progress-card data from the SESSION only — nothing here queries
     * `Pendaftaran`, because a `Pendaftaran` row does not exist yet at this point (it's
     * only created on final wizard submit). Step completion is read from the same
     * `spmb_wizard.{lembagaId}.{jalurId}` session keys each wizard step writes via
     * `PendaftaranWizardSession::put()` (`data_pribadi`, `jawaban_formulir`, `dokumen`).
     */
    private function bangunKemajuanWizard(Lembaga $lembaga, JalurPpdb $jalur, PendaftaranWizardSession $wizardSession): array
    {
        $data = $wizardSession->get($lembaga, $jalur);

        $langkah = [];
        $selesai = 0;
        $lanjutkanKe = self::LANGKAH_WIZARD[0]['route'];
        $sudahTemukanBerikutnya = false;

        foreach (self::LANGKAH_WIZARD as $l) {
            $done = $l['key'] !== null && isset($data[$l['key']]);
            if ($done) {
                $selesai++;
            } elseif (! $sudahTemukanBerikutnya) {
                $lanjutkanKe = $l['route'];
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
            'persen' => (int) round($selesai / count(self::LANGKAH_WIZARD) * 100),
            'langkah' => $langkah,
            'lanjutkan_ke' => $lanjutkanKe,
            'total_syarat_dokumen' => DokumenSyaratPpdb::where('jalur_ppdb_id', $jalur->id)->count(),
            'dokumen_terupload' => count($data['dokumen'] ?? []),
            'gelombang' => $gelombang,
            'nominal' => $this->resolveNominalPendaftaran($lembaga, $jalur)?->nominal,
        ];
    }
}
