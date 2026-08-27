<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Enums\StatusRpp;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\Rpp;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Kasus\Enums\StatusKasus;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Sdm\Models\PenugasanShift;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Enums\Hari;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Karyawan;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use App\Services\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function __construct(private DashboardStatsService $dashboardStats) {}

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

            $hariIni = Hari::fromCarbonDayOfWeek(now()->dayOfWeek);
            $jadwalHariIni = $user->guru === null
                ? collect()
                : JadwalPelajaran::where('guru_id', $user->guru->id)
                    ->semesterAktif()
                    ->whereHas('jamPelajaran', fn ($q) => $q->where('hari', $hariIni))
                    ->with(['kelas', 'mataPelajaran', 'jamPelajaran'])
                    ->get();

            $kelasWali = $user->guru === null
                ? null
                : Kelas::where('wali_kelas_guru_id', $user->guru->id)->first();
            $progressKelasWali = $kelasWali
                ? $this->dashboardStats->statistikProgressRaporKelas($kelasWali)
                : null;

            $presensiDiriHariIni = null;
            $rppPerluTindakan = 0;
            $rekapPresensiSiswaHariIni = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'terlambat' => 0];

            if ($user->guru !== null) {
                $presensiDiriHariIni = $user->guru->attendanceRecords()->withoutGlobalScope(TenantScope::class)
                    ->where('tanggal', now()->toDateString())->first();

                $rppPerluTindakan = Rpp::where('guru_id', $user->guru->id)
                    ->whereIn('status', [StatusRpp::Draft, StatusRpp::PerluRevisi])
                    ->count();

                $sesiHariIni = SesiPembelajaran::where('guru_id', $user->guru->id)
                    ->whereDate('tanggal', now()->toDateString())
                    ->pluck('id');

                if ($sesiHariIni->isNotEmpty()) {
                    $counts = Presensi::whereIn('sesi_pembelajaran_id', $sesiHariIni)
                        ->selectRaw('status, count(*) as total')
                        ->groupBy('status')
                        ->pluck('total', 'status');

                    $rekapPresensiSiswaHariIni = [
                        'hadir' => (int) ($counts['hadir'] ?? 0),
                        'izin' => (int) ($counts['izin'] ?? 0),
                        'sakit' => (int) ($counts['sakit'] ?? 0),
                        'alpa' => (int) ($counts['alpa'] ?? 0),
                        'terlambat' => (int) ($counts['terlambat'] ?? 0),
                    ];
                }
            }

            return view('admin.dashboard.guru', [
                'jabatanTambahan' => $user->guru?->jabatanTambahan ?? collect(),
                'kasusDiajukan' => $kasusDiajukan,
                'kasusDitangani' => $kasusDitangani,
                'kasusDiajukanStats' => $this->kasusStatusCounts($kasusDiajukan),
                'kasusDitanganiStats' => $this->kasusStatusCounts($kasusDitangani),
                'jadwalHariIni' => $jadwalHariIni,
                'kelasWali' => $kelasWali,
                'progressKelasWali' => $progressKelasWali,
                'presensiDiriHariIni' => $presensiDiriHariIni,
                'rppPerluTindakan' => $rppPerluTindakan,
                'rekapPresensiSiswaHariIni' => $rekapPresensiSiswaHariIni,
            ]);
        }

        if ($user->hasRole('siswa')) {
            $siswa = $user->siswa()->withoutGlobalScope(TenantScope::class)->with('kelas')->first();
            $jadwalHariIni = collect();
            $tagihanBelumLunas = 0;
            $nilaiTerbaru = collect();
            $presensiBulanIni = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'terlambat' => 0];

            if ($siswa !== null) {
                if ($siswa->kelas_id !== null) {
                    $hariIni = Hari::fromCarbonDayOfWeek(now()->dayOfWeek);
                    $jadwalHariIni = JadwalPelajaran::withoutGlobalScope(TenantScope::class)
                        ->where('kelas_id', $siswa->kelas_id)
                        ->semesterAktif()
                        ->whereHas('jamPelajaran', fn ($q) => $q->where('hari', $hariIni))
                        ->with(['kelas', 'mataPelajaran', 'jamPelajaran'])
                        ->get();
                }

                $tagihanBelumLunas = (int) Tagihan::withoutGlobalScope(TenantScope::class)
                    ->where(function ($q) use ($siswa) {
                        $q->where(fn ($q2) => $q2->where('tagihable_type', Siswa::class)->where('tagihable_id', $siswa->id));
                        if ($siswa->pendaftaran_asal_id !== null) {
                            $q->orWhere('pendaftaran_id', $siswa->pendaftaran_asal_id);
                        }
                    })
                    ->whereIn('status', ['belum_bayar', 'dicicil'])
                    ->sum('total_tagihan');

                $nilaiTerbaru = NilaiSiswa::where('siswa_id', $siswa->id)
                    ->whereNotNull('nilai_angka')
                    ->whereHas('asesmen', fn ($q) => $q->whereIn('jenis', JenisAsesmen::masukRapor()))
                    ->with(['komponenPenilaian.subjek', 'asesmen.subjek'])
                    ->latest('id')
                    ->limit(5)
                    ->get();

                $counts = Presensi::where('siswa_id', $siswa->id)
                    ->whereHas('sesiPembelajaran', fn ($q) => $q->whereMonth('tanggal', now()->month)->whereYear('tanggal', now()->year))
                    ->selectRaw('status, count(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status');

                $presensiBulanIni = [
                    'hadir' => (int) ($counts['hadir'] ?? 0),
                    'izin' => (int) ($counts['izin'] ?? 0),
                    'sakit' => (int) ($counts['sakit'] ?? 0),
                    'alpa' => (int) ($counts['alpa'] ?? 0),
                    'terlambat' => (int) ($counts['terlambat'] ?? 0),
                ];
            }

            return view('admin.dashboard.siswa', [
                'siswa' => $siswa,
                'jadwalHariIni' => $jadwalHariIni,
                'tagihanBelumLunas' => $tagihanBelumLunas,
                'nilaiTerbaru' => $nilaiTerbaru,
                'presensiBulanIni' => $presensiBulanIni,
            ]);
        }

        if ($user->hasRole('orang_tua')) {
            $orangTua = $user->orangTua;
            $kasusList = collect();
            $kontakUtamaKasusIds = [];
            $anakList = collect();
            $tagihanBelumLunas = 0;
            $nilaiTerbaru = collect();
            $riwayatIzinSakit = collect();
            $jadwalAnakHariIni = collect();

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

                $anakList = $orangTua->siswa()->withoutGlobalScope(TenantScope::class)
                    ->with(['kelas' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
                    ->get();
                $siswaIds = $anakList->pluck('id')->all();
                $pendaftaranIds = $anakList->pluck('pendaftaran_asal_id')->filter()->all();

                $tagihanBelumLunas = (int) Tagihan::withoutGlobalScope(TenantScope::class)
                    ->where(function ($q) use ($siswaIds, $pendaftaranIds) {
                        $q->where(fn ($q2) => $q2->where('tagihable_type', Siswa::class)->whereIn('tagihable_id', $siswaIds));
                        if (! empty($pendaftaranIds)) {
                            $q->orWhereIn('pendaftaran_id', $pendaftaranIds);
                        }
                    })
                    ->whereIn('status', ['belum_bayar', 'dicicil'])
                    ->sum('total_tagihan');

                $nilaiTerbaru = NilaiSiswa::withoutGlobalScope(TenantScope::class)->whereIn('siswa_id', $siswaIds)
                    ->whereNotNull('nilai_angka')
                    ->whereHas('asesmen', fn ($q) => $q->withoutGlobalScope(TenantScope::class)->whereIn('jenis', JenisAsesmen::masukRapor()))
                    ->with([
                        'komponenPenilaian' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->with(['subjek' => fn ($q2) => $q2->withoutGlobalScope(TenantScope::class)]),
                        'asesmen' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->with(['subjek' => fn ($q2) => $q2->withoutGlobalScope(TenantScope::class)]),
                        'siswa' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                    ])
                    ->latest('id')
                    ->limit(5)
                    ->get();

                $riwayatIzinSakit = Presensi::whereIn('siswa_id', $siswaIds)
                    ->whereIn('status', ['izin', 'sakit'])
                    ->with(['siswa' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
                    ->latest('id')
                    ->limit(5)
                    ->get();

                $kelasIds = $anakList->pluck('kelas_id')->filter()->all();
                $hariIni = Hari::fromCarbonDayOfWeek(now()->dayOfWeek);
                $jadwalAnakHariIni = empty($kelasIds)
                    ? collect()
                    : JadwalPelajaran::withoutGlobalScope(TenantScope::class)
                        ->whereIn('kelas_id', $kelasIds)
                        ->whereHas('jamPelajaran', fn ($q) => $q->withoutGlobalScope(TenantScope::class)->where('hari', $hariIni))
                        ->with([
                            'kelas' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                            'mataPelajaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                            'jamPelajaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                        ])
                        ->get();
            }

            return view('admin.dashboard.orang-tua', [
                'kasusList' => $kasusList,
                'kontakUtamaKasusIds' => $kontakUtamaKasusIds,
                'kasusStats' => $this->kasusStatusCounts($kasusList),
                'anakList' => $anakList,
                'tagihanBelumLunas' => $tagihanBelumLunas,
                'nilaiTerbaru' => $nilaiTerbaru,
                'riwayatIzinSakit' => $riwayatIzinSakit,
                'jadwalAnakHariIni' => $jadwalAnakHariIni,
            ]);
        }

        if ($user->hasRole('pegawai_yayasan') || $user->hasRole('pegawai_lembaga')) {
            $karyawan = Guru::where('user_id', $user->id)->withoutGlobalScope(TenantScope::class)->first()
                ?? Karyawan::where('user_id', $user->id)->withoutGlobalScope(TenantScope::class)->with('jenisKaryawan')->first();
            $karyawanId = $karyawan?->id;
            $jabatanLabel = match (true) {
                $karyawan instanceof Karyawan => $karyawan->jenisKaryawan?->nama,
                $karyawan instanceof Guru => $karyawan->jenis_ptk ? str($karyawan->jenis_ptk)->replace('_', ' ')->title()->toString() : null,
                default => null,
            };
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

            $sisaKuotaCuti = $karyawan instanceof Karyawan ? $this->dashboardStats->statistikSisaKuotaCuti($karyawan) : null;
            $jadwalShiftHariIni = $karyawan ? PenugasanShift::withoutGlobalScope(TenantScope::class)
                ->where('pegawai_type', $karyawan::class)
                ->where('pegawai_id', $karyawan->id)
                ->whereDate('tanggal_mulai', '<=', now()->toDateString())
                ->whereDate('tanggal_selesai', '>=', now()->toDateString())
                ->with('jenisShift')
                ->first() : null;

            $riwayatPresensi30Hari = ['labels' => [], 'hadir' => [], 'izin' => [], 'sakit' => [], 'alpa' => []];
            if ($karyawan !== null) {
                $records = $karyawan->attendanceRecords()->withoutGlobalScope(TenantScope::class)
                    ->where('tanggal', '>=', now()->subDays(29)->toDateString())
                    ->get()
                    ->keyBy(fn ($r) => $r->tanggal->toDateString());

                for ($i = 29; $i >= 0; $i--) {
                    $tanggal = now()->subDays($i);
                    $record = $records->get($tanggal->toDateString());
                    $riwayatPresensi30Hari['labels'][] = $tanggal->translatedFormat('d M');
                    $riwayatPresensi30Hari['hadir'][] = $record?->status?->value === 'hadir' ? 1 : 0;
                    $riwayatPresensi30Hari['izin'][] = $record?->status?->value === 'izin' ? 1 : 0;
                    $riwayatPresensi30Hari['sakit'][] = $record?->status?->value === 'sakit' ? 1 : 0;
                    $riwayatPresensi30Hari['alpa'][] = $record?->status?->value === 'alpa' ? 1 : 0;
                }
            }

            return view('admin.dashboard.karyawan', [
                'karyawan' => $karyawan,
                'jabatanLabel' => $jabatanLabel,
                'presensiHariIni' => $presensiHariIni,
                'sisaKuotaCuti' => $sisaKuotaCuti,
                'jadwalShiftHariIni' => $jadwalShiftHariIni,
                'riwayatPresensi30Hari' => $riwayatPresensi30Hari,
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
            'tahunAjaranList' => TahunAjaran::where('lembaga_id', $lembagaId)->orderBy('tanggal_mulai', 'desc')->get(),
            'spmbStats' => null,
            'tren' => null,
            'keuanganStats' => null,
            'presensiSdmHariIni' => $this->dashboardStats->statistikPresensiSdm([$lembagaId]),
            'izinCutiPendingCount' => PengajuanIzinCuti::where('lembaga_id', $lembagaId)
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
            $data['progressRaporPerKelas'] = Kelas::where('lembaga_id', $lembagaId)->get()
                ->map(fn (Kelas $kelas) => [
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

    private function kasusStatusCounts(Collection $kasusList): array
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
