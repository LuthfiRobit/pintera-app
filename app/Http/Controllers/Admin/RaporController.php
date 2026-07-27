<?php

namespace App\Http\Controllers\Admin;

use App\Models\Asesmen;
use App\Models\Kelas;
use App\Models\NilaiSiswa;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class RaporController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('rapor.view');

        $kelasList = Kelas::orderBy('nama')->get();
        $semesterList = Semester::orderByDesc('id')->get();

        $selectedKelas = $request->kelas_id ? Kelas::find($request->kelas_id) : $kelasList->first();
        $selectedSemester = $request->semester_id ? Semester::find($request->semester_id) : $semesterList->first();

        $rekapNilai = [];
        $mapelList = collect();
        $siswaList = collect();

        if ($selectedKelas && $selectedSemester) {
            $siswaList = Siswa::where('kelas_id', $selectedKelas->id)->orderBy('nama_lengkap')->get();

            $asesmenList = Asesmen::where('kelas_id', $selectedKelas->id)
                ->where('semester_id', $selectedSemester->id)
                ->with('mataPelajaran')
                ->get();

            $mapelList = $asesmenList->pluck('mataPelajaran')->unique('id')->sortBy('nama');
            $asesmenIds = $asesmenList->pluck('id');

            $allNilai = NilaiSiswa::whereIn('asesmen_id', $asesmenIds)->get();

            foreach ($siswaList as $siswa) {
                $rekapNilai[$siswa->id] = [];
                foreach ($mapelList as $mapel) {
                    $mapelAsesmenIds = $asesmenList->where('mata_pelajaran_id', $mapel->id)->pluck('id');
                    $scores = $allNilai->whereIn('asesmen_id', $mapelAsesmenIds)
                        ->where('siswa_id', $siswa->id)
                        ->whereNotNull('nilai_angka')
                        ->pluck('nilai_angka');

                    $avg = $scores->count() > 0 ? round($scores->avg(), 1) : null;
                    $rekapNilai[$siswa->id][$mapel->id] = $avg;
                }
            }
        }

        return view('admin.rapor.index', [
            'kelasList' => $kelasList,
            'semesterList' => $semesterList,
            'selectedKelas' => $selectedKelas,
            'selectedSemester' => $selectedSemester,
            'siswaList' => $siswaList,
            'mapelList' => $mapelList,
            'rekapNilai' => $rekapNilai,
        ]);
    }
}
