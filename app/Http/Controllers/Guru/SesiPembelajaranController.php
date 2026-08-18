<?php

namespace App\Http\Controllers\Guru;

use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Akademik\Services\SesiPembelajaranGenerator;
use App\Models\Kelas;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class SesiPembelajaranController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('presensi.isi');

        $guru = $request->user()->guru;
        $hariIni = now();

        if ($guru) {
            $kelasList = Kelas::where(function ($query) use ($guru) {
                $query->whereHas('jadwalPelajaran', fn ($q) => $q->where('guru_id', $guru->id))
                    ->orWhere('wali_kelas_guru_id', $guru->id);
            })->get();

            foreach ($kelasList as $kelas) {
                $semesterId = optional($kelas->tahunAjaran->semester()->where('status_aktif', true)->first())->id;
                if ($semesterId) {
                    (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, $hariIni, $semesterId);
                }
            }
        }

        return view('guru.sesi-pembelajaran.index', [
            'sesiList' => $guru
                ? SesiPembelajaran::where('guru_id', $guru->id)->whereDate('tanggal', $hariIni)->with('kelas', 'mataPelajaran')->get()
                : collect(),
        ]);
    }

    public function show(SesiPembelajaran $sesi): View
    {
        $this->authorize('presensi.isi');
        $this->authorizeMilikGuru($sesi);

        return view('guru.sesi-pembelajaran.show', [
            'sesi' => $sesi,
            'presensiList' => $sesi->presensi()->with('siswa')->get(),
        ]);
    }

    public function update(Request $request, SesiPembelajaran $sesi): RedirectResponse
    {
        $this->authorize('presensi.isi');
        $this->authorizeMilikGuru($sesi);

        $data = $request->validate([
            'materi' => ['nullable', 'string'],
            'presensi' => ['required', 'array'],
            'presensi.*' => ['required', 'in:hadir,izin,sakit,alpa,terlambat'],
        ]);

        $sesi->update(['materi' => $data['materi'] ?? null]);

        foreach ($data['presensi'] as $siswaId => $status) {
            $sesi->presensi()->where('siswa_id', $siswaId)->update(['status' => $status]);
        }

        return redirect()->route('guru.sesi.index')->with('status', 'Jurnal dan presensi berhasil disimpan.');
    }

    private function authorizeMilikGuru(SesiPembelajaran $sesi): void
    {
        $guru = auth()->user()->guru;

        abort_if($guru === null || $sesi->guru_id !== $guru->id, 403);
    }
}
