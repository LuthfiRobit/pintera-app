<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Kasus\Enums\StatusKasus;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Guru;
use App\Domains\Kasus\Models\Kasus;
use App\Models\Lembaga;
use App\Models\Scopes\TenantScope;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
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
            $kasusDiajukan = $user->guru === null
                ? collect()
                : Kasus::with('siswa')->where('diajukan_oleh_guru_id', $user->guru->id)->latest()->get();
            $kasusDitangani = $user->guru === null
                ? collect()
                : Kasus::with('siswa')->where('konselor_guru_id', $user->guru->id)->latest()->get();

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

        if ($user->hasRole('pegawai_yayasan') || $user->hasRole('pegawai_lembaga')) {
            $karyawan = $user->karyawan()->withoutGlobalScope(TenantScope::class)->with('jenisKaryawan')->first();
            $karyawanId = $karyawan?->id;
            $kasusDitangani = $karyawanId === null
                ? collect()
                : Kasus::withoutGlobalScope(TenantScope::class)
                    ->with(['siswa' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
                    ->where('konselor_karyawan_id', $karyawanId)
                    ->latest()->get();

            $presensiHariIni = $karyawanId === null
                ? null
                : $karyawan->attendanceRecords()->withoutGlobalScope(TenantScope::class)->where('tanggal', now()->toDateString())->first();

            $izinCutiPending = $karyawanId === null
                ? 0
                : $karyawan->pengajuanIzinCuti()->withoutGlobalScope(TenantScope::class)
                    ->whereHas('approvalRequest', fn ($q) => $q->where('status', ApprovalStatus::Pending->value))
                    ->count();

            $sisaKuotaCuti = $karyawan ? $this->dashboardStats->statistikSisaKuotaCuti($karyawan) : null;
            $jadwalShiftHariIni = $karyawan ? \App\Domains\Sdm\Models\PenugasanShift::withoutGlobalScope(TenantScope::class)
                ->where('pegawai_type', Karyawan::class)
                ->where('pegawai_id', $karyawan->id)
                ->whereDate('tanggal_mulai', '<=', now()->toDateString())
                ->whereDate('tanggal_selesai', '>=', now()->toDateString())
                ->with('jenisShift')
                ->first() : null;

            return view('admin.dashboard.karyawan', [
                'karyawan' => $karyawan,
                'presensiHariIni' => $presensiHariIni,
                'sisaKuotaCuti' => $sisaKuotaCuti,
                'jadwalShiftHariIni' => $jadwalShiftHariIni,
                'izinCutiPending' => $izinCutiPending,
                'kasusDitangani' => $kasusDitangani,
                'kasusDitanganiStats' => $this->kasusStatusCounts($kasusDitangani),
            ]);
        }

        if ($user->widestScopeLevel() === 'platform') {
            $yayasanList = Yayasan::withCount('lembaga')->get();

            $ringkasanPerYayasan = $yayasanList->map(function (Yayasan $yayasan) {
                $lembagaIds = Lembaga::where('yayasan_id', $yayasan->id)->pluck('id');

                return [
                    'yayasan' => $yayasan,
                    'lembaga' => $yayasan->lembaga_count,
                    'guru' => Guru::whereIn('lembaga_id', $lembagaIds)->count(),
                    'pengguna' => User::withoutGlobalScope(TenantScope::class)
                        ->where(fn ($q) => $q->whereIn('lembaga_id', $lembagaIds)->orWhere('yayasan_id', $yayasan->id))
                        ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'siswa'))
                        ->count(),
                    'adaTahunAjaranAktif' => TahunAjaran::whereIn('lembaga_id', $lembagaIds)->where('status_aktif', true)->exists(),
                    'akunNonaktif' => User::withoutGlobalScope(TenantScope::class)
                        ->where(fn ($q) => $q->whereIn('lembaga_id', $lembagaIds)->orWhere('yayasan_id', $yayasan->id))
                        ->where('is_active', false)
                        ->count(),
                ];
            });

            return view('admin.dashboard.platform', [
                'ringkasanPerYayasan' => $ringkasanPerYayasan,
                'trenTenant' => $this->dashboardStats->trenPertumbuhanYayasan(),
                'stats' => [
                    'yayasan' => $yayasanList->count(),
                    'lembaga' => Lembaga::count(),
                    'guru' => Guru::count(),
                    'pengguna' => User::withoutGlobalScope(TenantScope::class)
                        ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'siswa'))
                        ->count(),
                ],
            ]);
        }

        if ($user->widestScopeLevel() === 'yayasan') {
            $lembagaAktifId = session('active_lembaga_id');

            if ($lembagaAktifId !== null) {
                return view('admin.dashboard.lembaga', $this->lembagaViewData((int) $lembagaAktifId, $user));
            }

            // Deliberate trade-off vs. the design spec's "single groupBy('lembaga_id')
            // query" wording: this calls DashboardStatsService once per lembaga instead,
            // so the yayasan view reuses the exact same aggregation methods as the lembaga
            // view (one definition of every metric, per the plan's Architecture section).
            // A dedicated batched-aggregation variant would satisfy the spec's query-count
            // wording but require a second, parallel implementation of the same metrics.
            // Negligible at pilot scale (a yayasan has a handful of lembaga); revisit if a
            // yayasan ever grows to dozens.
            $lembagaList = Lembaga::where('yayasan_id', $user->yayasan_id)->get();
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

            $lembagaIds = $lembagaList->pluck('id')->all();
            $presensiSdmHariIni = $this->dashboardStats->statistikPresensiSdm($lembagaIds);
            $kasusEskalasiUnassigned = Kasus::withoutGlobalScope(TenantScope::class)
                ->whereIn('lembaga_id', $lembagaIds)
                ->where('status', StatusKasus::Eskalasi)
                ->whereNull('konselor_guru_id')
                ->whereNull('konselor_karyawan_id')
                ->count();

            return view('admin.dashboard.yayasan', [
                'lembagaList' => $lembagaList,
                'ringkasanPerLembaga' => $ringkasanPerLembaga,
                'totalPendaftar' => $ringkasanPerLembaga->sum(fn ($r) => $r['spmb']['total']),
                'totalDiterima' => $ringkasanPerLembaga->sum(fn ($r) => $r['spmb']['diterima']),
                'totalRpTerkumpul' => $ringkasanPerLembaga->sum(fn ($r) => $r['keuangan']['rpTerkumpul']),
                'presensiSdmHariIni' => $presensiSdmHariIni,
                'kasusEskalasiUnassigned' => $kasusEskalasiUnassigned,
                'stats' => [
                    'lembaga' => Lembaga::where('yayasan_id', $user->yayasan_id)->count(),
                    'guru' => Guru::count(),
                    'pengguna' => User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'siswa'))->count(),
                    'tahunAjaranAktif' => TahunAjaran::where('status_aktif', true)->count(),
                ],
            ]);
        }

        $lembagaId = $user->lembaga_id ?? session('active_lembaga_id') ?? Lembaga::first()?->id;

        if ($lembagaId === null) {
            return view('admin.dashboard.yayasan', [
                'lembagaList' => collect(),
                'ringkasanPerLembaga' => collect(),
                'totalPendaftar' => 0,
                'totalDiterima' => 0,
                'totalRpTerkumpul' => 0,
                'stats' => [
                    'lembaga' => 0,
                    'guru' => 0,
                    'pengguna' => 0,
                    'tahunAjaranAktif' => 0,
                ],
            ]);
        }

        return view('admin.dashboard.lembaga', $this->lembagaViewData((int) $lembagaId, $user));
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
            'presensiSdmHariIni' => $this->dashboardStats->statistikPresensiSdm([$lembagaId]),
            'izinCutiPendingCount' => \App\Domains\Sdm\Models\PengajuanIzinCuti::where('lembaga_id', $lembagaId)
                ->whereHas('approvalRequest', fn ($q) => $q->where('status', ApprovalStatus::Pending))
                ->count(),
            'progressRaporPerKelas' => null,
        ];

        if ($user->can('spmb-pendaftaran.view')) {
            $data['spmbStats'] = $this->dashboardStats->statistikSpmb($lembagaId);
            $data['tren'] = $this->dashboardStats->trenPendaftaranHarian($lembagaId);
        }

        if ($user->can('tagihan.view')) {
            $data['keuanganStats'] = $this->dashboardStats->statistikKeuangan($lembagaId);
        }

        if ($user->can('komponen-penilaian.kelola')) {
            $data['progressRaporPerKelas'] = \App\Models\Kelas::where('lembaga_id', $lembagaId)->get()
                ->map(fn (\App\Models\Kelas $kelas) => [
                    'kelas' => $kelas,
                    'progress' => $this->dashboardStats->statistikProgressRaporKelas($kelas),
                ]);
        }

        if ($user->can('kasus.triase')) {
            $kasusList = Kasus::with('siswa')
                ->where('lembaga_id', $lembagaId)
                ->orderByRaw("CASE WHEN status = 'eskalasi' THEN 0 ELSE 1 END")
                ->latest()
                ->get();

            $data['kasusList'] = $kasusList;
            $data['kasusStats'] = $this->kasusStatusCounts($kasusList);
        } else {
            $data['kasusList'] = null;
            $data['kasusStats'] = null;
        }

        return $data;
    }

    private function kasusStatusCounts(\Illuminate\Support\Collection $kasusList): array
    {
        return [
            'diajukan' => $kasusList->filter(fn (Kasus $k) => $k->status->value === 'diajukan')->count(),
            'menunggu_consent' => $kasusList->filter(fn (Kasus $k) => $k->status->value === 'menunggu_consent')->count(),
            'ditugaskan' => $kasusList->filter(fn (Kasus $k) => $k->status->value === 'ditugaskan')->count(),
            'berjalan' => $kasusList->filter(fn (Kasus $k) => $k->status->value === 'berjalan')->count(),
            'eskalasi' => $kasusList->filter(fn (Kasus $k) => $k->status->value === 'eskalasi')->count(),
            'selesai' => $kasusList->filter(fn (Kasus $k) => $k->status->value === 'selesai')->count(),
        ];
    }
}
