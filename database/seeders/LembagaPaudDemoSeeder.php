<?php

namespace Database\Seeders;

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Models\PolaJam;
use App\Enums\Hari;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Data demo 1 Lembaga PAUD (TK) supaya jalur penilaian elemen_cp (diperbaiki
 * 2026-09-05, lihat .agents/logs/2026-09-05-audit-alur-nilai-rapor-fix-elemen-cp-paud.md)
 * bisa dicoba & discreenshot dengan data nyata untuk manual book. Seed data
 * sebelumnya cuma punya 1 Lembaga (SD), nol Lembaga PAUD sama sekali.
 */
class LembagaPaudDemoSeeder extends Seeder
{
    public function run(): void
    {
        $yayasan = Yayasan::first();

        $lembaga = Lembaga::firstOrCreate(
            ['npsn' => '20223399'],
            [
                'yayasan_id' => $yayasan->id,
                'kode_lembaga' => 'TKPINTERA',
                'nama' => 'TK Pintera Ceria',
                'bentuk_pendidikan' => 'TK',
                'status_sekolah' => 'swasta',
                'naungan' => 'kemendikdasmen',
                'akreditasi' => 'A',
                'nama_kepala_sekolah' => 'Siti Aminah, S.Pd.',
                'status_aktif' => true,
            ]
        );

        $tahunAjaran = TahunAjaran::firstOrCreate(
            ['lembaga_id' => $lembaga->id, 'nama' => '2026/2027'],
            [
                'tanggal_mulai' => now()->startOfYear(),
                'tanggal_selesai' => now()->endOfYear(),
                'status_aktif' => true,
            ]
        );

        $semester = Semester::firstOrCreate(
            ['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil'],
            [
                'lembaga_id' => $lembaga->id,
                'urutan' => 1,
                'tanggal_mulai' => now()->startOfYear(),
                'tanggal_selesai' => now()->startOfYear()->addMonths(6),
                'status_aktif' => true,
            ]
        );

        // Guru + User: User dibuat DULU, lalu Guru::factory(['user_id' => ...]) --
        // JANGAN create() lalu update(['user_id' => ...]), itu silent no-op karena
        // user_id bukan kolom asli tabel guru (link sebenarnya lewat person_id ->
        // Person.user_id, dibaca di closure factory).
        $guruUser = User::firstOrCreate(
            ['email' => 'guru.tk@demo.test'],
            ['lembaga_id' => $lembaga->id, 'password' => bcrypt('password'), 'name' => 'Bu Siti Wali Kelas TK']
        );
        $guruUser->assignRole('guru');

        $guru = Guru::where('lembaga_id', $lembaga->id)->whereHas('person', fn ($q) => $q->where('user_id', $guruUser->id))->first()
            ?? Guru::factory()->create([
                'user_id' => $guruUser->id,
                'lembaga_id' => $lembaga->id,
                'nama' => 'Bu Siti Wali Kelas TK',
                'jenis_ptk' => 'guru_kelas',
            ]);

        $polaJam = PolaJam::firstOrCreate(
            ['lembaga_id' => $lembaga->id, 'nama' => 'Kelompok Bermain'],
        );

        foreach ([Hari::Senin, Hari::Selasa, Hari::Rabu, Hari::Kamis, Hari::Jumat] as $hari) {
            JamPelajaran::firstOrCreate(
                ['pola_jam_id' => $polaJam->id, 'hari' => $hari->value, 'urutan' => 1],
                ['label' => 'Sesi Pagi', 'jam_mulai' => '07:30', 'jam_selesai' => '09:30', 'is_pelajaran' => true]
            );
        }

        $kelas = Kelas::firstOrCreate(
            ['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Kelompok A'],
            [
                'lembaga_id' => $lembaga->id,
                'tingkat' => 'A',
                'wali_kelas_guru_id' => $guru->id,
                'pola_jam_id' => $polaJam->id,
            ]
        );

        // Konsistensi kalau kelas sudah ada dari run sebelumnya tapi belum ter-assign.
        if ($kelas->wali_kelas_guru_id !== $guru->id || $kelas->pola_jam_id !== $polaJam->id) {
            $kelas->update(['wali_kelas_guru_id' => $guru->id, 'pola_jam_id' => $polaJam->id]);
        }

        $namaSiswa = ['Ahmad Rizki', 'Bunga Lestari', 'Citra Wulandari'];
        $siswaList = collect($namaSiswa)->map(function (string $nama) use ($kelas, $lembaga) {
            $nis = 'TK-'.Str::slug($nama);

            return Siswa::where('lembaga_id', $lembaga->id)->where('nis', $nis)->first()
                ?? Siswa::factory()->create([
                    'lembaga_id' => $lembaga->id,
                    'kelas_id' => $kelas->id,
                    'nis' => $nis,
                    'nama_lengkap' => $nama,
                ]);
        });

        // subjek_type=elemen_cp: lembaga_id WAJIB eksplisit dari Semester -- booted()
        // hook KomponenPenilaian cuma auto-isi lembaga_id untuk subjek_type=mata_pelajaran
        // (lihat CreateKomponenPenilaianAction sebagai referensi pola yang benar).
        $elemenCp = ElemenCp::first();
        $komponen = KomponenPenilaian::firstOrCreate(
            ['subjek_type' => 'elemen_cp', 'subjek_id' => $elemenCp->id, 'semester_id' => $semester->id],
            [
                'lembaga_id' => $lembaga->id,
                'assessment_type' => 'narrative',
                'deskripsi' => "Perkembangan anak pada {$elemenCp->nama}",
                'bobot' => 100,
            ]
        );

        $asesmen = Asesmen::firstOrCreate(
            ['kelas_id' => $kelas->id, 'subjek_type' => 'elemen_cp', 'subjek_id' => $elemenCp->id, 'semester_id' => $semester->id],
            [
                'guru_id' => $guru->id,
                'jenis' => JenisAsesmen::SumatifLingkupMateri,
                'judul' => 'Observasi Perkembangan Semester Ganjil',
                'tanggal' => now(),
            ]
        );

        // WAJIB attach lewat pivot -- komponen yang tidak pernah di-attach ke asesmen
        // manapun TIDAK PERNAH dihitung sebagai "harus diisi" di kelengkapanNilaiKelas()
        // ataupun statistikProgressRaporKelas() (fix 2026-09-05), jadi data akan
        // terlihat "kosong tidak wajar" alih-alih "belum lengkap secara nyata".
        $asesmen->komponenPenilaian()->syncWithoutDetaching([$komponen->id]);

        // 2 siswa lengkap, 1 siswa (siswa terakhir) SENGAJA kosong -- supaya Bab 5
        // (peringatan kelengkapan nilai) punya skenario nyata untuk discreenshot.
        foreach ($siswaList as $i => $siswa) {
            $catatan = $i < 2
                ? 'Menunjukkan perkembangan yang baik, mampu mengikuti kegiatan dengan antusias.'
                : null;

            NilaiSiswa::updateOrCreate(
                ['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id],
                ['catatan' => $catatan]
            );
        }
    }
}
