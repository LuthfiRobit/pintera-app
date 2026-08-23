<?php

namespace App\Http\Controllers;

use App\Domains\Sdm\Actions\AjukanIzinCutiAction;
use App\Domains\Sdm\Actions\BatalkanPengajuanIzinCutiAction;
use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Sdm\Services\KuotaCutiResolver;
use App\Models\Guru;
use App\Models\Karyawan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class PengajuanIzinCutiController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kehadiran-sdm.izin.lihat-sendiri');

        $pegawai = $this->resolvePegawai($request);
        abort_if($pegawai === null, 404, 'Data kepegawaian Anda tidak ditemukan.');

        $riwayat = $pegawai->pengajuanIzinCuti()->with('approvalRequest.currentStep')->latest('tanggal_mulai')->get();

        return view('sdm.izin-cuti.index', ['riwayat' => $riwayat]);
    }

    public function create(Request $request, KuotaCutiResolver $kuotaResolver): View
    {
        $this->authorize('kehadiran-sdm.izin.ajukan');

        $pegawai = $this->resolvePegawai($request);
        $sisaKuotaCuti = $pegawai ? $kuotaResolver->sisaKuota($pegawai, (int) now()->format('Y')) : null;
        $adaKonfigurasiKuota = $pegawai && $kuotaResolver->jatahTahunan($pegawai) !== null;

        return view('sdm.izin-cuti.create', [
            'kategoriOptions' => KategoriPengajuanIzin::cases(),
            'sisaKuotaCuti' => $sisaKuotaCuti,
            'adaKonfigurasiKuota' => $adaKonfigurasiKuota,
        ]);
    }

    public function store(Request $request, AjukanIzinCutiAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.izin.ajukan');

        $data = $request->validate([
            'kategori' => ['required', 'in:izin,sakit,cuti'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date'],
            'alasan' => ['required', 'string', 'max:1000'],
        ]);

        $pegawai = $this->resolvePegawai($request);
        abort_if($pegawai === null, 404, 'Data kepegawaian Anda tidak ditemukan.');

        $action->execute(
            $pegawai,
            KategoriPengajuanIzin::from($data['kategori']),
            $data['tanggal_mulai'],
            $data['tanggal_selesai'],
            $data['alasan'],
        );

        return redirect()->route('sdm.izin-cuti.index')->with('status', 'Pengajuan berhasil dikirim, menunggu persetujuan.');
    }

    public function destroy(Request $request, PengajuanIzinCuti $izinCuti, BatalkanPengajuanIzinCutiAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.izin.lihat-sendiri');

        $action->execute($izinCuti, $request->user());

        return redirect()->route('sdm.izin-cuti.index')->with('status', 'Pengajuan berhasil dibatalkan.');
    }

    private function resolvePegawai(Request $request): Guru|Karyawan|null
    {
        $userId = $request->user()->id;

        return Guru::where('user_id', $userId)->first() ?? Karyawan::where('user_id', $userId)->first();
    }
}
