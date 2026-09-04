<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Models\Presensi;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PresensiSayaController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'dari_tanggal' => ['nullable', 'date'],
            'sampai_tanggal' => ['nullable', 'date', 'after_or_equal:dari_tanggal'],
        ]);

        $siswa = $request->user()->siswa;
        abort_unless($siswa !== null, 403, 'Akun Anda tidak terhubung ke data siswa.');

        $dariTanggal = $request->date('dari_tanggal') ?: now()->startOfMonth();
        $sampaiTanggal = $request->date('sampai_tanggal') ?: now()->endOfMonth();

        // SEMUA status presensi (hadir, sakit, izin, alpa, terlambat) ditampilkan di Ruang Siswa.
        // TIDAK ADA filter whereIn('status', ['izin', 'sakit']) seperti Ruang Orang Tua.
        // Catatan TenantScope: dibuktikan lewat test (PresensiSayaControllerTest) bahwa whereHas
        // biasa (tanpa bypass) sudah cukup untuk actor siswa (lembaga_id asli, beda dari orang tua).
        $riwayatList = Presensi::query()
            ->where('siswa_id', $siswa->id)
            ->whereHas('sesiPembelajaran', function ($query) use ($dariTanggal, $sampaiTanggal) {
                $query->whereBetween('tanggal', [$dariTanggal->toDateString(), $sampaiTanggal->toDateString()]);
            })
            ->with(['sesiPembelajaran.jadwalPelajaran.mataPelajaran', 'sesiPembelajaran.mataPelajaran'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.siswa-akademik.presensi-saya', [
            'siswa' => $siswa,
            'riwayatList' => $riwayatList,
            'dariTanggal' => $dariTanggal->toDateString(),
            'sampaiTanggal' => $sampaiTanggal->toDateString(),
        ]);
    }
}
