<?php

// database/seeders/CatatanWaliKelasSeeder.php

namespace Database\Seeders;

use App\Domains\Akademik\Actions\Rapor\SimpanCatatanWaliKelasAction;
use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class CatatanWaliKelasSeeder extends Seeder
{
    private const array CATATAN_SIKAP = [
        'Menunjukkan sikap disiplin dan santun dalam kegiatan pembelajaran sehari-hari.',
        'Aktif berpartisipasi di kelas, mampu bekerja sama dengan baik dalam kelompok.',
        'Menunjukkan rasa percaya diri yang baik, perlu bimbingan lebih dalam kedisiplinan waktu.',
        'Sopan terhadap guru dan teman, cukup mandiri dalam menyelesaikan tugas.',
    ];

    private const array CATATAN_PERKEMBANGAN = [
        'Mengalami kemajuan yang baik dalam kemampuan membaca dan berhitung.',
        'Perkembangan motorik dan kognitif sesuai dengan tahapan usianya.',
        'Menunjukkan minat yang tinggi terhadap kegiatan seni dan olahraga.',
        'Membutuhkan pendampingan tambahan pada mata pelajaran Matematika.',
    ];

    /**
     * Mengisi CatatanWaliKelas untuk SEMUA siswa di tahun ajaran aktif + semester aktif,
     * supaya seluruh Kelas siap diajukan rapornya (SubmitPengajuanRaporAction menolak
     * pengajuan kalau ada satu saja siswa yang belum punya catatan).
     */
    public function run(): void
    {
        $action = new SimpanCatatanWaliKelasAction;

        foreach (Lembaga::all() as $lembaga) {
            $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
            if (! $tahunAjaranAktif) {
                continue;
            }

            $semesterAktif = Semester::where('tahun_ajaran_id', $tahunAjaranAktif->id)->where('status_aktif', true)->first();
            if (! $semesterAktif) {
                continue;
            }

            $kelasList = Kelas::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $tahunAjaranAktif->id)->get();

            foreach ($kelasList as $kelas) {
                $siswaList = $kelas->siswa()->orderBy('id')->get();

                foreach ($siswaList as $idx => $siswa) {
                    $action->execute(CatatanWaliKelasData::fromArray([
                        'siswa_id' => $siswa->id,
                        'semester_id' => $semesterAktif->id,
                        'catatan_sikap' => self::CATATAN_SIKAP[$idx % count(self::CATATAN_SIKAP)],
                        'catatan_perkembangan' => self::CATATAN_PERKEMBANGAN[$idx % count(self::CATATAN_PERKEMBANGAN)],
                        'ekstrakurikuler' => $idx % 2 === 0 ? [['nama' => 'Pramuka', 'peran' => 'Anggota']] : [],
                        'prestasi' => $idx % 5 === 0 ? [['nama' => 'Juara 2 Lomba Mewarnai Tingkat Kecamatan', 'tingkat' => 'Kecamatan']] : [],
                        'pkl_info' => [],
                        'keterangan_kenaikan' => null,
                    ]));
                }
            }
        }
    }
}
