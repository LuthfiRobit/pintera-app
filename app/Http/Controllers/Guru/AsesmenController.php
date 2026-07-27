<?php

namespace App\Http\Controllers\Guru;

use App\Enums\JenisAsesmen;
use App\Models\Asesmen;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\KomponenPenilaian;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Semester;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AsesmenController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('asesmen.kelola');

        $guru = $request->user()->guru;

        $asesmenList = $guru
            ? Asesmen::where('guru_id', $guru->id)->with(['kelas', 'mataPelajaran', 'semester'])->orderByDesc('tanggal')->get()
            : collect();

        return view('guru.asesmen.index', [
            'asesmenList' => $asesmenList,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('asesmen.kelola');

        $guru = $request->user()->guru;
        abort_if(!$guru, 403, 'Profil guru tidak ditemukan untuk akun ini.');

        $jadwalList = JadwalPelajaran::where('guru_id', $guru->id)
            ->with(['kelas', 'mataPelajaran', 'semester'])
            ->get();

        $kelasIds = $jadwalList->pluck('kelas_id')->unique();
        $mapelIds = $jadwalList->pluck('mata_pelajaran_id')->filter()->unique();
        $semesterIds = $jadwalList->pluck('semester_id')->unique();

        return view('guru.asesmen.create', [
            'kelasList' => Kelas::whereIn('id', $kelasIds)->orderBy('nama')->get(),
            'mataPelajaranList' => MataPelajaran::whereIn('id', $mapelIds)->orderBy('nama')->get(),
            'semesterList' => Semester::whereIn('id', $semesterIds)->orderByDesc('id')->get(),
            'komponenList' => KomponenPenilaian::whereIn('mata_pelajaran_id', $mapelIds)->get(),
            'jenisAsesmenList' => JenisAsesmen::v1Didukung(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('asesmen.kelola');

        $guru = $request->user()->guru;
        abort_if(!$guru, 403, 'Profil guru tidak ditemukan.');

        $data = $request->validate([
            'kelas_id' => ['required', 'integer'],
            'mata_pelajaran_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
            'jenis' => ['required', 'in:sumatif_lingkup_materi,sumatif_akhir_semester,sumatif_akhir_jenjang'],
            'judul' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'komponen_id' => ['nullable', 'array'],
            'komponen_id.*' => ['integer'],
        ]);

        $mengajarKombinasiIni = JadwalPelajaran::where('guru_id', $guru->id)
            ->where('kelas_id', $data['kelas_id'])
            ->where('mata_pelajaran_id', $data['mata_pelajaran_id'])
            ->where('semester_id', $data['semester_id'])
            ->exists();

        abort_unless($mengajarKombinasiIni, 403, 'Anda tidak mengajar kombinasi kelas dan mata pelajaran ini.');

        $komponenIds = !empty($data['komponen_id'])
            ? KomponenPenilaian::whereIn('id', $data['komponen_id'])->where('mata_pelajaran_id', $data['mata_pelajaran_id'])->pluck('id')
            : collect();

        $asesmen = DB::transaction(function () use ($guru, $data, $komponenIds) {
            $asesmen = Asesmen::create([
                'guru_id' => $guru->id,
                'kelas_id' => $data['kelas_id'],
                'mata_pelajaran_id' => $data['mata_pelajaran_id'],
                'semester_id' => $data['semester_id'],
                'jenis' => JenisAsesmen::from($data['jenis']),
                'judul' => $data['judul'],
                'tanggal' => $data['tanggal'],
            ]);

            if ($komponenIds->isNotEmpty()) {
                $asesmen->komponenPenilaian()->attach($komponenIds);
            }

            // Populate initial empty NilaiSiswa rows for all enrolled students, per komponen
            $siswaList = $asesmen->kelas->siswa()->get();
            foreach ($siswaList as $siswa) {
                foreach ($komponenIds as $komponenId) {
                    NilaiSiswa::firstOrCreate([
                        'asesmen_id' => $asesmen->id,
                        'siswa_id' => $siswa->id,
                        'komponen_penilaian_id' => $komponenId,
                    ]);
                }
            }

            return $asesmen;
        });

        return redirect()->route('guru.asesmen.show', $asesmen)->with('status', 'Asesmen berhasil dibuat. Silakan masukkan nilai peserta didik.');
    }

    public function show(Asesmen $asesmen): View
    {
        $this->authorize('asesmen.kelola');
        $this->authorizeMilikGuru($asesmen);

        $komponenList = $asesmen->komponenPenilaian;
        $siswaList = $asesmen->kelas->siswa()->orderBy('nama_lengkap')->get();

        // Ensure any newly added student/komponen combination has a NilaiSiswa row
        foreach ($siswaList as $siswa) {
            foreach ($komponenList as $komponen) {
                NilaiSiswa::firstOrCreate([
                    'asesmen_id' => $asesmen->id,
                    'siswa_id' => $siswa->id,
                    'komponen_penilaian_id' => $komponen->id,
                ]);
            }
        }

        $nilaiMatrix = NilaiSiswa::where('asesmen_id', $asesmen->id)
            ->get()
            ->keyBy(fn ($n) => $n->siswa_id.'-'.$n->komponen_penilaian_id);

        return view('guru.asesmen.show', [
            'asesmen' => $asesmen->load(['kelas', 'mataPelajaran', 'semester']),
            'komponenList' => $komponenList,
            'siswaList' => $siswaList,
            'nilaiMatrix' => $nilaiMatrix,
        ]);
    }

    public function updateNilai(Request $request, Asesmen $asesmen): RedirectResponse
    {
        $this->authorize('asesmen.kelola');
        $this->authorizeMilikGuru($asesmen);

        $komponenIds = $asesmen->komponenPenilaian()->pluck('komponen_penilaian.id');

        $data = $request->validate([
            'nilai' => ['required', 'array'],
            'nilai.*.*.nilai_angka' => ['nullable', 'integer', 'min:0', 'max:100'],
            'nilai.*.*.catatan' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($asesmen, $data, $komponenIds) {
            foreach ($data['nilai'] as $siswaId => $perKomponen) {
                foreach ($perKomponen as $komponenId => $values) {
                    if (!$komponenIds->contains((int) $komponenId)) {
                        continue;
                    }

                    NilaiSiswa::updateOrCreate(
                        ['asesmen_id' => $asesmen->id, 'siswa_id' => $siswaId, 'komponen_penilaian_id' => $komponenId],
                        [
                            'nilai_angka' => isset($values['nilai_angka']) && $values['nilai_angka'] !== '' ? (int) $values['nilai_angka'] : null,
                            'catatan' => $values['catatan'] ?? null,
                        ]
                    );
                }
            }
        });

        return redirect()->route('guru.asesmen.show', $asesmen)->with('status', 'Nilai dan catatan asesmen berhasil disimpan.');
    }

    private function authorizeMilikGuru(Asesmen $asesmen): void
    {
        $guru = auth()->user()->guru;
        abort_if($guru === null || $asesmen->guru_id !== $guru->id, 403);
    }
}
