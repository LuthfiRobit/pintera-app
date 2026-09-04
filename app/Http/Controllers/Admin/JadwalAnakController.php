<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Support\ResolveAnakOrangTuaTrait;
use App\Models\JadwalPelajaran;
use App\Models\Scopes\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class JadwalAnakController extends BaseController
{
    use ResolveAnakOrangTuaTrait;

    public function index(Request $request): View
    {
        $anakList = $this->resolveAnakList($request->user());
        $anak = $this->resolveAnakTerpilih($anakList, $request->integer('siswa_id') ?: null);

        $hariOrder = [
            'senin' => 1,
            'selasa' => 2,
            'rabu' => 3,
            'kamis' => 4,
            'jumat' => 5,
            'sabtu' => 6,
            'minggu' => 7,
        ];

        $jadwalList = ($anak && $anak->kelas_id !== null)
            ? JadwalPelajaran::withoutGlobalScope(TenantScope::class)
                ->where('kelas_id', $anak->kelas_id)
                ->semesterAktif()
                ->with([
                    'jamPelajaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                    'mataPelajaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                    'guru' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->with('person'),
                ])
                ->get()
                ->sortBy(fn (JadwalPelajaran $jadwal) => sprintf(
                    '%d-%s',
                    $hariOrder[$jadwal->jamPelajaran?->hari?->value ?? ''] ?? 9,
                    $jadwal->jamPelajaran?->jam_mulai ?? ''
                ))
                ->groupBy(fn (JadwalPelajaran $jadwal) => $jadwal->jamPelajaran->hari->value)
            : collect();

        return view('admin.orang-tua.jadwal-anak', [
            'anakList' => $anakList,
            'anak' => $anak,
            'jadwalList' => $jadwalList,
        ]);
    }
}
