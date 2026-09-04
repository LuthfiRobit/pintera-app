<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Models\Presensi;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PresensiSayaController extends Controller
{
    public function index(Request $request): View
    {
        $siswa = $request->user()->siswa;
        abort_unless($siswa !== null, 403, 'Akun Anda tidak terhubung ke data siswa.');

        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->startOfMonth();

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfMonth();

        // SEMUA status presensi (hadir, sakit, izin, alpa, terlambat) ditampilkan di Ruang Siswa.
        // TIDAK ADA filter whereIn('status', ['izin', 'sakit']) seperti Ruang Orang Tua.
        // Catatan TenantScope: mulai DENGAN whereHas biasa (tanpa bypass). Jalankan test (Task 3 Step 6)
        // untuk membuktikan apakah bypass TenantScope diperlukan.
        $riwayatList = Presensi::query()
            ->where('siswa_id', $siswa->id)
            ->whereHas('sesiPembelajaran', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('tanggal', [$startDate, $endDate]);
            })
            ->with(['sesiPembelajaran.jadwalPelajaran.mataPelajaran', 'sesiPembelajaran.mataPelajaran'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.siswa-akademik.presensi-saya', [
            'siswa' => $siswa,
            'riwayatList' => $riwayatList,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
        ]);
    }
}
