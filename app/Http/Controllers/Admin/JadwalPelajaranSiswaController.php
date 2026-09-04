<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\JadwalPelajaran;
use App\Models\Scopes\TenantScope;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class JadwalPelajaranSiswaController extends BaseController
{
    private const HARI_ORDER = [
        'senin' => 1, 'selasa' => 2, 'rabu' => 3, 'kamis' => 4,
        'jumat' => 5, 'sabtu' => 6, 'minggu' => 7,
    ];

    public function index(Request $request): View
    {
        $siswa = $request->user()->siswa;

        $semesterList = $siswa && $siswa->kelas
            ? Semester::where('tahun_ajaran_id', $siswa->kelas->tahun_ajaran_id)->orderByDesc('id')->get()
            : collect();
        $semesterId = $request->integer('semester_id')
            ?: ($siswa && $siswa->kelas
                ? Semester::where('tahun_ajaran_id', $siswa->kelas->tahun_ajaran_id)->where('status_aktif', true)->value('id')
                : null)
            ?: $semesterList->first()?->id;

        $jadwalList = ($siswa && $siswa->kelas_id !== null && $semesterId)
            ? JadwalPelajaran::withoutGlobalScope(TenantScope::class)
                ->where('kelas_id', $siswa->kelas_id)
                ->where('semester_id', $semesterId)
                ->with([
                    'jamPelajaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                    'mataPelajaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                    'guru' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->with('person'),
                ])
                ->get()
                ->sortBy(fn (JadwalPelajaran $jadwal) => sprintf(
                    '%d-%s',
                    self::HARI_ORDER[$jadwal->jamPelajaran?->hari?->value ?? ''] ?? 9,
                    $jadwal->jamPelajaran?->jam_mulai ?? ''
                ))
                ->groupBy(fn (JadwalPelajaran $jadwal) => $jadwal->jamPelajaran->hari->value)
            : collect();

        return view('admin.siswa-akademik.jadwal-pelajaran', [
            'siswa' => $siswa,
            'semesterList' => $semesterList,
            'semesterId' => $semesterId,
            'jadwalList' => $jadwalList,
        ]);
    }
}
