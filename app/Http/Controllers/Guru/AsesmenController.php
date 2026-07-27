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

            // Populate initial empty NilaiSiswa rows for all enrolled students
            $siswaList = $asesmen->kelas->siswa()->get();
            foreach ($siswaList as $siswa) {
                NilaiSiswa::firstOrCreate([
                    'asesmen_id' => $asesmen->id,
                    'siswa_id' => $siswa->id,
                ]);
            }

            return $asesmen;
        });

        return redirect()->route('guru.asesmen.show', $asesmen)->with('status', 'Asesmen berhasil dibuat. Silakan masukkan nilai peserta didik.');
    }

    public function show(Asesmen $asesmen): View
    {
        $this->authorize('asesmen.kelola');
        $this->authorizeMilikGuru($asesmen);

        // Ensure any newly added students to the class have a NilaiSiswa row
        foreach ($asesmen->kelas->siswa as $siswa) {
            NilaiSiswa::firstOrCreate([
                'asesmen_id' => $asesmen->id,
                'siswa_id' => $siswa->id,
            ]);
        }

        return view('guru.asesmen.show', [
            'asesmen' => $asesmen->load(['kelas', 'mataPelajaran', 'semester', 'komponenPenilaian']),
            'nilaiList' => NilaiSiswa::where('asesmen_id', $asesmen->id)
                ->with('siswa')
                ->get()
                ->sortBy(fn ($item) => $item->siswa->nama_lengkap),
        ]);
    }

    public function updateNilai(Request $request, Asesmen $asesmen): RedirectResponse
    {
        $this->authorize('asesmen.kelola');
        $this->authorizeMilikGuru($asesmen);

        $data = $request->validate([
            'nilai' => ['required', 'array'],
            'nilai.*.skor' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'nilai.*.catatan' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($asesmen, $data) {
            foreach ($data['nilai'] as $siswaId => $values) {
                NilaiSiswa::updateOrCreate(
                    ['asesmen_id' => $asesmen->id, 'siswa_id' => $siswaId],
                    [
                        'skor' => isset($values['skor']) && $values['skor'] !== '' ? (float) $values['skor'] : null,
                        'catatan' => $values['catatan'] ?? null,
                    ]
                );
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
