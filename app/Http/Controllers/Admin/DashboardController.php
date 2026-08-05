<?php

namespace App\Http\Controllers\Admin;

use App\Models\Guru;
use App\Models\Kasus;
use App\Models\Lembaga;
use App\Models\Scopes\TenantScope;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function __construct(private DashboardStatsService $dashboardStats)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->hasRole('guru')) {
            $kasusDiajukan = Kasus::with('siswa')->where('diajukan_oleh_guru_id', $user->guru?->id)->latest()->get();
            $kasusDitangani = Kasus::with('siswa')->where('konselor_guru_id', $user->guru?->id)->latest()->get();

            return view('admin.dashboard.guru', [
                'jabatanTambahan' => $user->guru?->jabatanTambahan ?? collect(),
                'kasusDiajukan' => $kasusDiajukan,
                'kasusDitangani' => $kasusDitangani,
                'kasusDiajukanStats' => $this->kasusStatusCounts($kasusDiajukan),
                'kasusDitanganiStats' => $this->kasusStatusCounts($kasusDitangani),
            ]);
        }

        if ($user->hasRole('siswa')) {
            return view('admin.dashboard.siswa');
        }

        if ($user->hasRole('orang_tua')) {
            $orangTua = $user->orangTua;
            $kasusList = collect();
            $kontakUtamaKasusIds = [];

            if ($orangTua !== null) {
                $kasusList = Kasus::withoutGlobalScope(TenantScope::class)
                    ->with(['siswa' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
                    ->whereHas('siswa', function ($q) use ($orangTua) {
                        $q->withoutGlobalScope(TenantScope::class)
                            ->whereHas('orangTua', fn ($q2) => $q2->where('orang_tua_id', $orangTua->id));
                    })
                    ->latest()->get();

                $kontakUtamaKasusIds = $kasusList->filter(function (Kasus $kasus) use ($orangTua) {
                    return $kasus->siswa->orangTua()->where('orang_tua_id', $orangTua->id)->wherePivot('is_kontak_utama', true)->exists();
                })->pluck('id')->all();
            }

            return view('admin.dashboard.orang-tua', [
                'kasusList' => $kasusList,
                'kontakUtamaKasusIds' => $kontakUtamaKasusIds,
                'kasusStats' => $this->kasusStatusCounts($kasusList),
            ]);
        }

        if ($user->hasRole('karyawan_pool') || $user->hasRole('karyawan_lembaga')) {
            $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;
            $kasusDitangani = Kasus::withoutGlobalScope(TenantScope::class)
                ->with(['siswa' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
                ->where('konselor_karyawan_id', $karyawanId)
                ->latest()->get();

            return view('admin.dashboard.karyawan', [
                'kasusDitangani' => $kasusDitangani,
                'kasusDitanganiStats' => $this->kasusStatusCounts($kasusDitangani),
            ]);
        }

        if ($user->widestScopeLevel() === 'yayasan') {
            $lembagaAktifId = session('active_lembaga_id');

            if ($lembagaAktifId !== null) {
                return view('admin.dashboard.lembaga', $this->lembagaViewData((int) $lembagaAktifId, $user));
            }

            // Lembaga::all(), not filtered by any yayasan_id on $user: the User model has
            // no yayasan_id column (only lembaga_id) — a yayasan-scoped user is identified
            // purely by an assigned role with scope_level='yayasan', not by any FK to a
            // specific Yayasan row. This matches the pre-existing behavior of this exact
            // branch before this task (also unfiltered Lembaga::all()) — do not invent a
            // yayasan_id relationship on User to "fix" this; that would be a schema change
            // outside this plan's scope.
            // Deliberate trade-off vs. the design spec's "single groupBy('lembaga_id')
            // query" wording: this calls DashboardStatsService once per lembaga instead,
            // so the yayasan view reuses the exact same aggregation methods as the lembaga
            // view (one definition of every metric, per the plan's Architecture section).
            // A dedicated batched-aggregation variant would satisfy the spec's query-count
            // wording but require a second, parallel implementation of the same metrics.
            // Negligible at pilot scale (a yayasan has a handful of lembaga); revisit if a
            // yayasan ever grows to dozens.
            $lembagaList = Lembaga::all();
            $ringkasanPerLembaga = $lembagaList->map(function (Lembaga $lembaga) use ($user) {
                return [
                    'lembaga' => $lembaga,
                    'spmb' => $user->can('spmb-pendaftaran.view')
                        ? $this->dashboardStats->statistikSpmb($lembaga->id)
                        : ['total' => 0, 'diterima' => 0],
                    'keuangan' => $user->can('tagihan.view')
                        ? $this->dashboardStats->statistikKeuangan($lembaga->id)
                        : ['rpTerkumpul' => 0],
                ];
            });

            return view('admin.dashboard.yayasan', [
                'lembagaList' => $lembagaList,
                'ringkasanPerLembaga' => $ringkasanPerLembaga,
                'totalPendaftar' => $ringkasanPerLembaga->sum(fn ($r) => $r['spmb']['total']),
                'totalDiterima' => $ringkasanPerLembaga->sum(fn ($r) => $r['spmb']['diterima']),
                'totalRpTerkumpul' => $ringkasanPerLembaga->sum(fn ($r) => $r['keuangan']['rpTerkumpul']),
                'stats' => [
                    'lembaga' => Lembaga::count(),
                    'guru' => Guru::count(),
                    'pengguna' => User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'siswa'))->count(),
                    'tahunAjaranAktif' => TahunAjaran::where('status_aktif', true)->count(),
                ],
            ]);
        }

        return view('admin.dashboard.lembaga', $this->lembagaViewData($user->lembaga_id, $user));
    }

    private function lembagaViewData(int $lembagaId, User $user): array
    {
        $data = [
            'stats' => [
                'guru' => Guru::where('lembaga_id', $lembagaId)->count(),
                'pengguna' => User::where('lembaga_id', $lembagaId)
                    ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'siswa'))
                    ->count(),
                'tahunAjaranAktif' => TahunAjaran::where('lembaga_id', $lembagaId)->where('status_aktif', true)->count(),
            ],
            'tahunAjaranAktif' => TahunAjaran::where('lembaga_id', $lembagaId)->where('status_aktif', true)->first(),
            'spmbStats' => null,
            'tren' => null,
            'keuanganStats' => null,
        ];

        if ($user->can('spmb-pendaftaran.view')) {
            $data['spmbStats'] = $this->dashboardStats->statistikSpmb($lembagaId);
            $data['tren'] = $this->dashboardStats->trenPendaftaranHarian($lembagaId);
        }

        if ($user->can('tagihan.view')) {
            $data['keuanganStats'] = $this->dashboardStats->statistikKeuangan($lembagaId);
        }

        return $data;
    }

    private function kasusStatusCounts(\Illuminate\Support\Collection $kasusList): array
    {
        return [
            'ditugaskan' => $kasusList->filter(fn (Kasus $k) => $k->status->value === 'ditugaskan')->count(),
            'berjalan' => $kasusList->filter(fn (Kasus $k) => $k->status->value === 'berjalan')->count(),
            'eskalasi' => $kasusList->filter(fn (Kasus $k) => $k->status->value === 'eskalasi')->count(),
            'selesai' => $kasusList->filter(fn (Kasus $k) => $k->status->value === 'selesai')->count(),
        ];
    }
}
