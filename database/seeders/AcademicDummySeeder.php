<?php

namespace Database\Seeders;

use App\Enums\JenisAsesmen;
use App\Models\Asesmen;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\KomponenPenilaian;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\PolaJam;
use App\Models\Presensi;
use App\Models\Role;
use App\Models\Semester;
use App\Models\SesiPembelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AcademicDummySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Dapatkan Lembaga SMP Permata & Tahun Ajaran / Semester Aktif
        $smp = Lembaga::where('npsn', '20223344')->first();
        if (!$smp) {
            $this->command->error("Lembaga SMP Permata (NPSN 20223344) tidak ditemukan. Pastikan seeder utama sudah dijalankan.");
            return;
        }

        $tahunAjaran = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->first();
        if (!$tahunAjaran) {
            $tahunAjaran = TahunAjaran::create([
                'lembaga_id' => $smp->id,
                'nama' => '2026/2027',
                'tanggal_mulai' => '2026-07-01',
                'tanggal_selesai' => '2027-06-30',
                'status_aktif' => true,
            ]);
        }

        $semester = Semester::where('tahun_ajaran_id', $tahunAjaran->id)->where('status_aktif', true)->first();
        if (!$semester) {
            $semester = Semester::create([
                'tahun_ajaran_id' => $tahunAjaran->id,
                'lembaga_id' => $smp->id,
                'nama' => 'Ganjil',
                'urutan' => 1,
                'kode_dapodik' => '20261',
                'tanggal_mulai' => '2026-07-01',
                'tanggal_selesai' => '2026-12-20',
                'status_aktif' => true,
            ]);
        }

        // 2. Berikan permission presensi.isi & asesmen.kelola ke role guru
        $roleGuru = Role::where('name', 'guru')->first();
        if ($roleGuru) {
            $roleGuru->givePermissionTo(['presensi.isi', 'asesmen.kelola']);
        }

        // 3. Dapatkan Guru yang ter-seed dari GuruSeeder
        $budiUser = User::where('email', 'budi.santoso@permata.sch.id')->first();
        $sitiUser = User::where('email', 'siti.rahmawati@permata.sch.id')->first();
        $andiUser = User::where('email', 'andi.wijaya@permata.sch.id')->first();

        $guruBudi = $budiUser ? Guru::where('user_id', $budiUser->id)->first() : null;
        $guruSiti = $sitiUser ? Guru::where('user_id', $sitiUser->id)->first() : null;
        $guruAndi = $andiUser ? Guru::where('user_id', $andiUser->id)->first() : null;

        if (!$guruBudi || !$guruSiti) {
            $this->command->error("Profil Guru (Budi / Siti) tidak ditemukan. Pastikan GuruSeeder sudah dijalankan.");
            return;
        }

        // 4. Seed Mata Pelajaran
        $mapelMatematika = MataPelajaran::firstOrCreate(
            ['lembaga_id' => $smp->id, 'nama' => 'Matematika'],
            ['tipe' => 'mapel']
        );
        $mapelIPA = MataPelajaran::firstOrCreate(
            ['lembaga_id' => $smp->id, 'nama' => 'Ilmu Pengetahuan Alam (IPA)'],
            ['tipe' => 'mapel']
        );
        $mapelIndo = MataPelajaran::firstOrCreate(
            ['lembaga_id' => $smp->id, 'nama' => 'Bahasa Indonesia'],
            ['tipe' => 'mapel']
        );
        $mapelAgama = MataPelajaran::firstOrCreate(
            ['lembaga_id' => $smp->id, 'nama' => 'Pendidikan Agama Islam'],
            ['tipe' => 'mapel']
        );

        // 5. Seed Pola Jam & Jam Pelajaran
        $polaJam = PolaJam::firstOrCreate(
            ['lembaga_id' => $smp->id, 'nama' => 'Pola Jam SMP']
        );

        $daftarHari = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];
        foreach ($daftarHari as $hari) {
            // Jam ke-1: Pelajaran
            JamPelajaran::firstOrCreate(
                ['pola_jam_id' => $polaJam->id, 'hari' => $hari, 'urutan' => 1],
                ['label' => 'Jam ke-1', 'jam_mulai' => '07:00:00', 'jam_selesai' => '07:40:00', 'is_pelajaran' => true]
            );
            // Jam ke-2: Pelajaran
            JamPelajaran::firstOrCreate(
                ['pola_jam_id' => $polaJam->id, 'hari' => $hari, 'urutan' => 2],
                ['label' => 'Jam ke-2', 'jam_mulai' => '07:40:00', 'jam_selesai' => '08:20:00', 'is_pelajaran' => true]
            );
            // Jam ke-3: Istirahat
            JamPelajaran::firstOrCreate(
                ['pola_jam_id' => $polaJam->id, 'hari' => $hari, 'urutan' => 3],
                ['label' => 'Istirahat', 'jam_mulai' => '08:20:00', 'jam_selesai' => '08:50:00', 'is_pelajaran' => false]
            );
            // Jam ke-4: Pelajaran
            JamPelajaran::firstOrCreate(
                ['pola_jam_id' => $polaJam->id, 'hari' => $hari, 'urutan' => 4],
                ['label' => 'Jam ke-3', 'jam_mulai' => '08:50:00', 'jam_selesai' => '09:30:00', 'is_pelajaran' => true]
            );
            // Jam ke-5: Pelajaran
            JamPelajaran::firstOrCreate(
                ['pola_jam_id' => $polaJam->id, 'hari' => $hari, 'urutan' => 5],
                ['label' => 'Jam ke-4', 'jam_mulai' => '09:30:00', 'jam_selesai' => '10:10:00', 'is_pelajaran' => true]
            );
        }

        // 6. Seed Kelas VII-A & VII-B
        $kelasA = Kelas::firstOrCreate(
            ['lembaga_id' => $smp->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'VII-A'],
            ['tingkat' => 7, 'pola_jam_id' => $polaJam->id, 'wali_kelas_guru_id' => $guruBudi->id]
        );
        // Pastikan terhubung ke PolaJam jika kelasA sudah ada sebelumnya
        if ($kelasA->pola_jam_id !== $polaJam->id) {
            $kelasA->update(['pola_jam_id' => $polaJam->id, 'wali_kelas_guru_id' => $guruBudi->id]);
        }

        $kelasB = Kelas::firstOrCreate(
            ['lembaga_id' => $smp->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'VII-B'],
            ['tingkat' => 7, 'pola_jam_id' => $polaJam->id, 'wali_kelas_guru_id' => $guruSiti->id]
        );
        if ($kelasB->pola_jam_id !== $polaJam->id) {
            $kelasB->update(['pola_jam_id' => $polaJam->id, 'wali_kelas_guru_id' => $guruSiti->id]);
        }

        // 7. Seed Siswa VII-A & VII-B
        $siswaDataA = [
            ['nis' => '2627001', 'nisn' => '0098765431', 'nama' => 'Aditya Pratama', 'jk' => 'L'],
            ['nis' => '2627002', 'nisn' => '0098765432', 'nama' => 'Budi Setiawan', 'jk' => 'L'],
            ['nis' => '2627003', 'nisn' => '0098765433', 'nama' => 'Citra Lestari', 'jk' => 'P'],
            ['nis' => '2627004', 'nisn' => '0098765434', 'nama' => 'Dedi Wijaya', 'jk' => 'L'],
            ['nis' => '2627005', 'nisn' => '0098765435', 'nama' => 'Eliana Putri', 'jk' => 'P'],
        ];

        $siswaA = [];
        foreach ($siswaDataA as $data) {
            $siswaA[] = Siswa::firstOrCreate(
                ['lembaga_id' => $smp->id, 'nis' => $data['nis']],
                [
                    'kelas_id' => $kelasA->id,
                    'nisn' => $data['nisn'],
                    'nama_lengkap' => $data['nama'],
                    'jenis_kelamin' => $data['jk'],
                    'tempat_lahir' => 'Bandung',
                    'tanggal_lahir' => '2014-05-10',
                    'agama' => 'Islam',
                    'sumber_data' => 'manual',
                    'status' => 'aktif'
                ]
            );
        }

        $siswaDataB = [
            ['nis' => '2627006', 'nisn' => '0098765436', 'nama' => 'Fahri Ramadhan', 'jk' => 'L'],
            ['nis' => '2627007', 'nisn' => '0098765437', 'nama' => 'Gita Permata', 'jk' => 'P'],
            ['nis' => '2627008', 'nisn' => '0098765438', 'nama' => 'Hendra Kusuma', 'jk' => 'L'],
            ['nis' => '2627009', 'nisn' => '0098765439', 'nama' => 'Indah Sari', 'jk' => 'P'],
            ['nis' => '2627010', 'nisn' => '0098765440', 'nama' => 'Joko Susilo', 'jk' => 'L'],
        ];

        $siswaB = [];
        foreach ($siswaDataB as $data) {
            $siswaB[] = Siswa::firstOrCreate(
                ['lembaga_id' => $smp->id, 'nis' => $data['nis']],
                [
                    'kelas_id' => $kelasB->id,
                    'nisn' => $data['nisn'],
                    'nama_lengkap' => $data['nama'],
                    'jenis_kelamin' => $data['jk'],
                    'tempat_lahir' => 'Bandung',
                    'tanggal_lahir' => '2014-08-15',
                    'agama' => 'Islam',
                    'sumber_data' => 'manual',
                    'status' => 'aktif'
                ]
            );
        }

        // 8. Seed Jadwal Pelajaran (untuk semua hari agar selalu valid saat test hari apa saja)
        foreach ($daftarHari as $hari) {
            // Dapatkan JamPelajaran ID untuk urutan 1, 2, 4, 5 pada hari tersebut
            $jam1 = JamPelajaran::where('pola_jam_id', $polaJam->id)->where('hari', $hari)->where('urutan', 1)->first();
            $jam2 = JamPelajaran::where('pola_jam_id', $polaJam->id)->where('hari', $hari)->where('urutan', 2)->first();
            $jam4 = JamPelajaran::where('pola_jam_id', $polaJam->id)->where('hari', $hari)->where('urutan', 4)->first();
            $jam5 = JamPelajaran::where('pola_jam_id', $polaJam->id)->where('hari', $hari)->where('urutan', 5)->first();

            if ($jam1 && $jam2 && $jam4 && $jam5) {
                // Kelas VII-A: Jam 1 & 2 Matematika (Budi)
                JadwalPelajaran::firstOrCreate([
                    'kelas_id' => $kelasA->id,
                    'jam_pelajaran_id' => $jam1->id,
                    'semester_id' => $semester->id,
                ], [
                    'mata_pelajaran_id' => $mapelMatematika->id,
                    'guru_id' => $guruBudi->id,
                ]);

                JadwalPelajaran::firstOrCreate([
                    'kelas_id' => $kelasA->id,
                    'jam_pelajaran_id' => $jam2->id,
                    'semester_id' => $semester->id,
                ], [
                    'mata_pelajaran_id' => $mapelMatematika->id,
                    'guru_id' => $guruBudi->id,
                ]);

                // Kelas VII-A: Jam 4 & 5 IPA (Siti)
                JadwalPelajaran::firstOrCreate([
                    'kelas_id' => $kelasA->id,
                    'jam_pelajaran_id' => $jam4->id,
                    'semester_id' => $semester->id,
                ], [
                    'mata_pelajaran_id' => $mapelIPA->id,
                    'guru_id' => $guruSiti->id,
                ]);

                JadwalPelajaran::firstOrCreate([
                    'kelas_id' => $kelasA->id,
                    'jam_pelajaran_id' => $jam5->id,
                    'semester_id' => $semester->id,
                ], [
                    'mata_pelajaran_id' => $mapelIPA->id,
                    'guru_id' => $guruSiti->id,
                ]);

                // Kelas VII-B: Jam 1 & 2 IPA (Siti)
                JadwalPelajaran::firstOrCreate([
                    'kelas_id' => $kelasB->id,
                    'jam_pelajaran_id' => $jam1->id,
                    'semester_id' => $semester->id,
                ], [
                    'mata_pelajaran_id' => $mapelIPA->id,
                    'guru_id' => $guruSiti->id,
                ]);

                JadwalPelajaran::firstOrCreate([
                    'kelas_id' => $kelasB->id,
                    'jam_pelajaran_id' => $jam2->id,
                    'semester_id' => $semester->id,
                ], [
                    'mata_pelajaran_id' => $mapelIPA->id,
                    'guru_id' => $guruSiti->id,
                ]);

                // Kelas VII-B: Jam 4 & 5 Matematika (Budi)
                JadwalPelajaran::firstOrCreate([
                    'kelas_id' => $kelasB->id,
                    'jam_pelajaran_id' => $jam4->id,
                    'semester_id' => $semester->id,
                ], [
                    'mata_pelajaran_id' => $mapelMatematika->id,
                    'guru_id' => $guruBudi->id,
                ]);

                JadwalPelajaran::firstOrCreate([
                    'kelas_id' => $kelasB->id,
                    'jam_pelajaran_id' => $jam5->id,
                    'semester_id' => $semester->id,
                ], [
                    'mata_pelajaran_id' => $mapelMatematika->id,
                    'guru_id' => $guruBudi->id,
                ]);
            }
        }

        // 9. Seed Historis Sesi Pembelajaran & Presensi (Kemarin)
        $kemarin = Carbon::yesterday();
        $hariKemarinName = strtolower($kemarin->locale('id')->dayName);
        if ($hariKemarinName === 'minggu') {
            // Jika kemarin hari minggu, kita geser ke 2 hari lalu agar dapat hari kerja jika perlu,
            // tapi karena kita seed Senin-Minggu, hari minggu pun ada jadwalnya.
        }

        $jam1Kemarin = JamPelajaran::where('pola_jam_id', $polaJam->id)->where('hari', $hariKemarinName)->where('urutan', 1)->first();
        $jam4Kemarin = JamPelajaran::where('pola_jam_id', $polaJam->id)->where('hari', $hariKemarinName)->where('urutan', 4)->first();

        if ($jam1Kemarin && $jam4Kemarin) {
            $jadwalMatematikaA = JadwalPelajaran::where('kelas_id', $kelasA->id)->where('jam_pelajaran_id', $jam1Kemarin->id)->first();
            $jadwalIpaA = JadwalPelajaran::where('kelas_id', $kelasA->id)->where('jam_pelajaran_id', $jam4Kemarin->id)->first();

            if ($jadwalMatematikaA) {
                // Sesi Matematika Kelas VII-A kemarin
                $sesiMtk = SesiPembelajaran::firstOrCreate(
                    [
                        'jadwal_pelajaran_id' => $jadwalMatematikaA->id,
                        'tanggal' => $kemarin->toDateString(),
                    ],
                    [
                        'kelas_id' => $kelasA->id,
                        'guru_id' => $guruBudi->id,
                        'mata_pelajaran_id' => $mapelMatematika->id,
                        'jam_mulai' => '07:00:00',
                        'jam_selesai' => '08:20:00',
                        'materi' => 'Pengenalan Aljabar dan Persamaan Linier Satu Variabel.',
                        'status' => 'terlaksana',
                    ]
                );

                // Buat presensi kemarin: Aditya Pratama Sakit, lainnya Hadir
                foreach ($siswaA as $index => $siswa) {
                    Presensi::firstOrCreate(
                        ['sesi_pembelajaran_id' => $sesiMtk->id, 'siswa_id' => $siswa->id],
                        ['status' => ($index === 0) ? 'sakit' : 'hadir', 'keterangan' => ($index === 0) ? 'Demam tinggi' : null]
                    );
                }
            }

            if ($jadwalIpaA) {
                // Sesi IPA Kelas VII-A kemarin
                $sesiIpa = SesiPembelajaran::firstOrCreate(
                    [
                        'jadwal_pelajaran_id' => $jadwalIpaA->id,
                        'tanggal' => $kemarin->toDateString(),
                    ],
                    [
                        'kelas_id' => $kelasA->id,
                        'guru_id' => $guruSiti->id,
                        'mata_pelajaran_id' => $mapelIPA->id,
                        'jam_mulai' => '08:50:00',
                        'jam_selesai' => '10:10:00',
                        'materi' => 'Pengenalan Metode Ilmiah dan Pengukuran Fisika.',
                        'status' => 'terlaksana',
                    ]
                );

                // Buat presensi kemarin: Budi Setiawan Izin, lainnya Hadir
                foreach ($siswaA as $index => $siswa) {
                    Presensi::firstOrCreate(
                        ['sesi_pembelajaran_id' => $sesiIpa->id, 'siswa_id' => $siswa->id],
                        ['status' => ($index === 1) ? 'izin' : 'hadir', 'keterangan' => ($index === 1) ? 'Acara keluarga' : null]
                    );
                }
            }
        }

        // 9. Seed Komponen Penilaian (Tujuan Pembelajaran) Kurikulum Merdeka
        $tpMtk1 = KomponenPenilaian::firstOrCreate(
            ['mata_pelajaran_id' => $mapelMatematika->id, 'semester_id' => $semester->id, 'kode' => 'TP.1.1'],
            ['deskripsi' => 'Peserta didik dapat menyelesaikan operasi aritmetika pada bilangan bulat dan pecahan.', 'kktp' => 'Minimal 75% benar']
        );
        $tpMtk2 = KomponenPenilaian::firstOrCreate(
            ['mata_pelajaran_id' => $mapelMatematika->id, 'semester_id' => $semester->id, 'kode' => 'TP.1.2'],
            ['deskripsi' => 'Peserta didik mendeskripsikan dan mengekspresikan relasi serta fungsi dengan representasi grafik.', 'kktp' => 'Mampu menggambar grafik linier']
        );

        $tpIpa1 = KomponenPenilaian::firstOrCreate(
            ['mata_pelajaran_id' => $mapelIPA->id, 'semester_id' => $semester->id, 'kode' => 'TP.IPA.1'],
            ['deskripsi' => 'Peserta didik memahami besaran pokok dan besaran turunan dalam satuan internasional.', 'kktp' => 'Tepat menggunakan alat ukur']
        );

        // 10. Seed Asesmen dan Nilai Siswa VII-A
        $asesmenMtk = Asesmen::firstOrCreate(
            ['guru_id' => $guruBudi->id, 'kelas_id' => $kelasA->id, 'mata_pelajaran_id' => $mapelMatematika->id, 'semester_id' => $semester->id, 'judul' => 'Sumatif Lingkup Materi 1: Bilangan & Aljabar'],
            ['jenis' => JenisAsesmen::SumatifLingkupMateri, 'tanggal' => now()->subDays(5)->toDateString()]
        );
        $asesmenMtk->komponenPenilaian()->syncWithoutDetaching([$tpMtk1->id, $tpMtk2->id]);

        $skorMtk = [88.5, 92.0, 78.0, 85.0, 95.0];
        $catatanMtk = [
            'Menunjukkan pemahaman yang sangat baik dalam operasi aritmetika dan aljabar.',
            'Sangat unggul dalam analisis soal cerita dan pemecahan masalah bilangan.',
            'Perlu penguatan pada pemahaman representasi grafik dan fungsi.',
            'Cukup baik, konsisten dalam menyelesaikan latihan operasi bilangan bulat.',
            'Sempurna dalam menguasai seluruh indikator Tujuan Pembelajaran 1 dan 2.',
        ];
        foreach ($siswaA as $i => $siswa) {
            NilaiSiswa::updateOrCreate(
                ['asesmen_id' => $asesmenMtk->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $tpMtk1->id],
                ['nilai_angka' => (int) round($skorMtk[$i] ?? 80), 'catatan' => $catatanMtk[$i] ?? 'Baik']
            );
        }

        $asesmenIpa = Asesmen::firstOrCreate(
            ['guru_id' => $guruSiti->id, 'kelas_id' => $kelasA->id, 'mata_pelajaran_id' => $mapelIPA->id, 'semester_id' => $semester->id, 'judul' => 'Sumatif Lingkup Materi 1: Besaran & Pengukuran'],
            ['jenis' => JenisAsesmen::SumatifLingkupMateri, 'tanggal' => now()->subDays(3)->toDateString()]
        );
        $asesmenIpa->komponenPenilaian()->syncWithoutDetaching([$tpIpa1->id]);

        $skorIpa = [84.0, 89.0, 91.5, 82.0, 88.0];
        foreach ($siswaA as $i => $siswa) {
            NilaiSiswa::updateOrCreate(
                ['asesmen_id' => $asesmenIpa->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $tpIpa1->id],
                ['nilai_angka' => (int) round($skorIpa[$i] ?? 85), 'catatan' => 'Mampu menggunakan jangka sorong dan mikrometer sekrup dengan ketelitian baik.']
            );
        }
    }
}
