<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KenaikanKelas;

use App\Domains\Akademik\Actions\Jadwal\CreateJadwalPelajaranAction;
use App\Domains\Akademik\DataTransferObjects\JadwalPelajaranData;
use App\Domains\Akademik\DataTransferObjects\KenaikanKelasData;
use App\Enums\StatusSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProsesKenaikanKelasAction
{
    public function __construct(
        private readonly CreateJadwalPelajaranAction $createJadwalPelajaranAction
    ) {}

    /**
     * @return array{jadwalGagal: array<int, string>}
     *
     * @throws \DomainException kalau kelas tujuan berada di tahun ajaran yang sama dengan kelas asal
     */
    public function execute(KenaikanKelasData $data): array
    {
        $jadwalGagal = [];

        DB::transaction(function () use ($data, &$jadwalGagal) {
            foreach ($data->mapping as $kelasLamaId => $aksi) {
                if ($aksi['tindakan'] === 'lewati') {
                    continue;
                }

                $kelasLama = Kelas::findOrFail($kelasLamaId);

                if ($aksi['tindakan'] === 'lulus') {
                    Siswa::where('kelas_id', $kelasLama->id)->update([
                        'status' => StatusSiswa::Lulus->value,
                        'kelas_id' => null,
                    ]);

                    continue;
                }

                $kelasBaru = Kelas::find($aksi['kelas_baru_id']);
                abort_if($kelasBaru === null || $kelasBaru->lembaga_id !== $kelasLama->lembaga_id, 404);

                if ($kelasBaru->tahun_ajaran_id === $kelasLama->tahun_ajaran_id) {
                    throw new \DomainException("Kelas tujuan \"{$kelasBaru->nama}\" masih berada di tahun ajaran yang sama dengan kelas asal \"{$kelasLama->nama}\". Pilih kelas tujuan dari tahun ajaran berikutnya.");
                }

                $tahunAjaranLama = TahunAjaran::findOrFail($kelasLama->tahun_ajaran_id);
                $tahunAjaranBaru = TahunAjaran::findOrFail($kelasBaru->tahun_ajaran_id);

                if ($tahunAjaranBaru->tanggal_mulai < $tahunAjaranLama->tanggal_mulai) {
                    throw new \DomainException("Kelas tujuan \"{$kelasBaru->nama}\" berada di tahun ajaran \"{$tahunAjaranBaru->nama}\" yang lebih lama dari tahun ajaran kelas asal \"{$tahunAjaranLama->nama}\". Pilih kelas tujuan dari tahun ajaran berikutnya.");
                }

                Siswa::where('kelas_id', $kelasLama->id)->update(['kelas_id' => $kelasBaru->id]);

                if (($aksi['salin_jadwal'] ?? false) && ! empty($aksi['semester_tujuan_id'])) {
                    $semesterTujuan = Semester::find($aksi['semester_tujuan_id']);
                    abort_if($semesterTujuan === null || $semesterTujuan->lembaga_id !== $kelasLama->lembaga_id, 404);

                    $gagalDiBaris = $this->salinJadwal($kelasLama, $kelasBaru, $semesterTujuan->id);
                    $jadwalGagal = array_merge($jadwalGagal, $gagalDiBaris);
                }
            }
        });

        return ['jadwalGagal' => $jadwalGagal];
    }

    /**
     * @return array<int, string> deskripsi baris yang gagal disalin karena bentrok
     */
    private function salinJadwal(Kelas $kelasLama, Kelas $kelasBaru, int $semesterTujuanId): array
    {
        $jadwalLama = JadwalPelajaran::where('kelas_id', $kelasLama->id)->with('jamPelajaran')->get();
        $gagal = [];

        foreach ($jadwalLama as $jadwal) {
            $sudahAda = JadwalPelajaran::where('kelas_id', $kelasBaru->id)
                ->where('jam_pelajaran_id', $jadwal->jam_pelajaran_id)
                ->where('semester_id', $semesterTujuanId)
                ->exists();

            if ($sudahAda) {
                continue;
            }

            try {
                $this->createJadwalPelajaranAction->execute(JadwalPelajaranData::fromArray([
                    'lembaga_id' => $kelasBaru->lembaga_id,
                    'kelas_id' => $kelasBaru->id,
                    'guru_id' => $jadwal->guru_id,
                    'jam_pelajaran_id' => $jadwal->jam_pelajaran_id,
                    'semester_id' => $semesterTujuanId,
                    'mata_pelajaran_id' => $jadwal->mata_pelajaran_id,
                    'ruangan_id' => $jadwal->ruangan_id,
                ]));
            } catch (ValidationException $e) {
                $labelJam = $jadwal->jamPelajaran->label ?? "slot #{$jadwal->jam_pelajaran_id}";
                $gagal[] = "{$kelasLama->nama} → {$kelasBaru->nama} ({$labelJam}): ".$e->validator->errors()->first();
            }
        }

        return $gagal;
    }
}
