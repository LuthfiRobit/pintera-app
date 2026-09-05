<?php

namespace App\Http\Controllers\Guru;

use App\Domains\Akademik\Actions\Penilaian\CreateAsesmenAction;
use App\Domains\Akademik\Actions\Penilaian\SimpanNilaiSiswaAction;
use App\Domains\Akademik\Enums\BentukPendidikan;
use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Http\Requests\Akademik\StoreAsesmenRequest;
use App\Http\Requests\Akademik\UpdateNilaiSiswaRequest;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Semester;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class AsesmenController extends BaseController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CreateAsesmenAction $createAsesmenAction,
        private readonly SimpanNilaiSiswaAction $simpanNilaiSiswaAction,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('asesmen.kelola');

        $guru = $request->user()->guru;

        $asesmenList = $guru
            ? Asesmen::where('guru_id', $guru->id)->with(['kelas', 'subjek', 'semester'])->orderByDesc('tanggal')->get()
            : collect();

        return view('portals.guru.akademik.asesmen.index', [
            'asesmenList' => $asesmenList,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('asesmen.kelola');

        $guru = $request->user()->guru;
        abort_if(! $guru, 403, 'Profil guru tidak ditemukan untuk akun ini.');

        $jadwalList = JadwalPelajaran::where('guru_id', $guru->id)
            ->with(['kelas', 'mataPelajaran', 'semester'])
            ->get();

        // Kelas Tematik (PAUD -- KB/TPA/SPS/TK) tidak pernah punya baris JadwalPelajaran
        // sama sekali (lihat SesiTematikGenerator); penugasan gurunya lewat
        // Kelas.wali_kelas_guru_id. Tanpa union ini, dropdown kelas & semester akan
        // SELALU KOSONG untuk wali kelas PAUD -- fitur asesmen elemen_cp jadi tidak
        // bisa diakses sama sekali lewat UI.
        $kelasWali = Kelas::where('wali_kelas_guru_id', $guru->id)->get();

        $kelasIds = $jadwalList->pluck('kelas_id')->merge($kelasWali->pluck('id'))->unique();
        $mapelIds = $jadwalList->pluck('mata_pelajaran_id')->filter()->unique();
        $semesterIds = $jadwalList->pluck('semester_id')
            ->merge(Semester::whereIn('tahun_ajaran_id', $kelasWali->pluck('tahun_ajaran_id')->unique())->pluck('id'))
            ->unique();

        $bentukPendidikan = $request->user()->lembaga?->bentuk_pendidikan;
        $subjekType = BentukPendidikan::tryFrom($bentukPendidikan ?? '')?->isPaud() ? 'elemen_cp' : 'mata_pelajaran';

        // Sebelumnya cuma pernah query subjek_type=mata_pelajaran di sini -- untuk
        // guru PAUD (elemen_cp), komponenList akan selalu kosong dan checklist TP
        // di form tidak pernah menampilkan apa pun.
        $komponenList = $subjekType === 'elemen_cp'
            ? KomponenPenilaian::where('subjek_type', 'elemen_cp')->get()
            : KomponenPenilaian::where('subjek_type', 'mata_pelajaran')->whereIn('subjek_id', $mapelIds)->get();

        return view('portals.guru.akademik.asesmen.create', [
            'kelasList' => Kelas::whereIn('id', $kelasIds)->orderBy('nama')->get(),
            'mataPelajaranList' => MataPelajaran::whereIn('id', $mapelIds)->orderBy('nama')->get(),
            'elemenCpList' => ElemenCp::orderBy('no_urut')->get(),
            'semesterList' => Semester::whereIn('id', $semesterIds)->orderByDesc('id')->get(),
            'komponenList' => $komponenList,
            'jenisAsesmenList' => JenisAsesmen::cases(),
            'subjekType' => $subjekType,
        ]);
    }

    public function store(StoreAsesmenRequest $request): RedirectResponse
    {
        $guru = $request->user()->guru;
        abort_if(! $guru, 403, 'Profil guru tidak ditemukan.');

        $data = $request->validated();
        if ($data['subjek_type'] === 'mata_pelajaran') {
            $mengajarKombinasiIni = JadwalPelajaran::where('guru_id', $guru->id)
                ->where('kelas_id', $data['kelas_id'])
                ->where('mata_pelajaran_id', $data['subjek_id'])
                ->where('semester_id', $data['semester_id'])
                ->exists();

            abort_unless($mengajarKombinasiIni, 403, 'Anda tidak mengajar kombinasi kelas dan mata pelajaran ini.');
        } else {
            $waliKelasIni = Kelas::where('id', $data['kelas_id'])
                ->where('wali_kelas_guru_id', $guru->id)
                ->exists();

            abort_unless($waliKelasIni, 403, 'Anda bukan wali kelas dari kelas ini.');
        }

        $asesmen = $this->createAsesmenAction->execute($guru, $request->toDTO());

        return redirect()->route('guru.asesmen.show', $asesmen)->with('status', 'Asesmen berhasil dibuat. Silakan masukkan nilai peserta didik.');
    }

    public function show(Asesmen $asesmen): View
    {
        $this->authorize('asesmen.kelola');
        $this->authorizeMilikGuru($asesmen);

        $komponenList = $asesmen->komponenPenilaian;
        $siswaList = $asesmen->kelas->siswa()->with('person')->orderByNama()->get();

        $existingNilai = NilaiSiswa::where('asesmen_id', $asesmen->id)->get();
        $existingKeys = $existingNilai->map(fn ($n) => $n->siswa_id.'-'.$n->komponen_penilaian_id)->flip();

        // Ensure any newly added student/komponen combination has a NilaiSiswa row,
        // via a single bulk insert instead of one firstOrCreate() per (siswa, komponen) pair.
        $now = now();
        $missingRows = [];
        foreach ($siswaList as $siswa) {
            foreach ($komponenList as $komponen) {
                $key = $siswa->id.'-'.$komponen->id;
                if (! $existingKeys->has($key)) {
                    $missingRows[] = [
                        'asesmen_id' => $asesmen->id,
                        'siswa_id' => $siswa->id,
                        'komponen_penilaian_id' => $komponen->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if (! empty($missingRows)) {
            NilaiSiswa::insertOrIgnore($missingRows);
        }

        $nilaiMatrix = NilaiSiswa::where('asesmen_id', $asesmen->id)
            ->get()
            ->keyBy(fn ($n) => $n->siswa_id.'-'.$n->komponen_penilaian_id);

        return view('portals.guru.akademik.asesmen.show', [
            'asesmen' => $asesmen->load(['kelas', 'subjek', 'semester']),
            'komponenList' => $komponenList,
            'siswaList' => $siswaList,
            'nilaiMatrix' => $nilaiMatrix,
        ]);
    }

    public function updateNilai(UpdateNilaiSiswaRequest $request, Asesmen $asesmen): RedirectResponse
    {
        $this->authorizeMilikGuru($asesmen);

        $this->simpanNilaiSiswaAction->execute($asesmen, $request->toDTO());

        return redirect()->route('guru.asesmen.show', $asesmen)->with('status', 'Nilai dan catatan asesmen berhasil disimpan.');
    }

    private function authorizeMilikGuru(Asesmen $asesmen): void
    {
        $guru = auth()->user()->guru;
        abort_if($guru === null || $asesmen->guru_id !== $guru->id, 403);
    }
}
