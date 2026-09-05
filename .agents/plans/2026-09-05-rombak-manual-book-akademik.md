# Rombak Manual Book Akademik Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tulis ulang total manual book Akademik (`docs/manual-book/akademik/`) supaya mencerminkan 100% fitur yang benar-benar ada di aplikasi hari ini, ditambah 1 halaman indeks baru yang menjawab "per role, fitur apa yang perlu diisi dan urutannya" (pemicu langsung: pertanyaan client).

**Architecture:** Dokumentasi Markdown + screenshot Playwright asli, dirakit jadi 1 HTML self-contained lewat script existing, dipublish sebagai Claude Artifact. Satu-satunya komponen kode nyata adalah 1 seeder PHP baru (`LembagaPaudDemoSeeder`) yang menyediakan data lembaga PAUD supaya sub-bagian "Sisi Guru PAUD" di Bab 4 bisa dicoba dengan data nyata.

**Tech Stack:** Laravel 12 (seeder), Markdown, Playwright (`scripts/manual-book-screenshots.mjs`), Node.js (`scripts/manual-book-artifact/build.mjs`), Claude Artifact tool.

## Global Constraints

- Semua fitur yang didokumentasikan WAJIB dicoba/diklik langsung di aplikasi yang jalan sebelum ditulis prosanya — bukan ditulis dari membaca kode. Satu-satunya pengecualian: sub-bagian "Sisi Guru PAUD" di Bab 4 boleh skip SCREENSHOT baru (tetap wajib dicoba), lihat Task 8.
- Format bab topik WAJIB 5 bagian, urutan tetap: `# Bab N — <Topik>` → **Untuk siapa** → **Prasyarat** → **Langkah-langkah** (tiap langkah + screenshot asli) → **Kesalahan umum** (gejala → penyebab → cara benerin, diverifikasi nyata terjadi, bukan ditebak).
- TIDAK pakai worktree — semua kerja langsung di branch `akademik-v2`.
- Republish Artifact ke URL yang SAMA: `https://claude.ai/code/artifact/92e6b639-d846-48ad-9e42-2a270abc5e03` (parameter `url` saat publish) — JANGAN buat link baru.
- Manual book Keuangan (`docs/manual-book/keuangan/`) TIDAK disentuh sama sekali.
- `docs/**` gitignored (commit `a7e3a517`) — `git status` TIDAK akan menampilkan perubahan file manual book, itu memang seharusnya begitu. JANGAN pakai `git add -f` untuk memaksa masuk git.
- Kalau saat mencoba fitur ditemukan perilaku aneh/bug, STOP task itu, laporkan status BLOCKED dengan detail temuan — jangan didiamkan atau ditulis seolah normal.

---

## File Structure

**Baru (kode, masuk git):**
- `database/seeders/LembagaPaudDemoSeeder.php`
- `database/seeders/DatabaseSeeder.php` (tambah 1 baris registrasi)

**Baru/ditulis ulang (dokumentasi, TIDAK masuk git — `docs/` gitignored):**
- `docs/manual-book/akademik/README.md` (baru)
- `docs/manual-book/akademik/00-setup-lembaga.md` s.d. `06-kenaikan-kelas.md`, `lampiran-lintas-lembaga.md` (ditulis ulang, nama file sama seperti sebelumnya)
- `docs/manual-book/akademik/07-ruang-orang-tua.md` (baru)
- `docs/manual-book/akademik/08-ruang-siswa.md` (baru)
- `docs/manual-book/akademik/images/*.png` (screenshot baru, sebagian lama dihapus)

**Ditulis (dokumentasi proses, masuk git):**
- `.agents/logs/2026-09-05-rombak-manual-book-akademik.md`
- `PETA_PENGEMBANGAN.md` (update)

---

### Task 1: Seeder `LembagaPaudDemoSeeder`

**Files:**
- Create: `database/seeders/LembagaPaudDemoSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php:86` (tambah 1 baris setelah `PengajuanRaporSeeder::class,`)

**Interfaces:**
- Consumes: `App\Models\Lembaga`, `App\Models\Yayasan`, `App\Models\TahunAjaran`, `App\Models\Semester`, `App\Models\Guru`, `App\Models\User`, `App\Models\Kelas`, `App\Models\Siswa`, `App\Domains\Akademik\Models\PolaJam`, `App\Domains\Akademik\Models\JamPelajaran`, `App\Domains\Akademik\Models\ElemenCp` (data global, sudah ada via `ElemenCpSeeder`, JANGAN buat baru), `App\Domains\Akademik\Models\KomponenPenilaian`, `App\Domains\Akademik\Models\Asesmen`, `App\Domains\Akademik\Models\NilaiSiswa`, `App\Domains\Akademik\Enums\JenisAsesmen`, `App\Enums\Hari`.
- Produces: 1 Lembaga TK (npsn `20223399`, kode_lembaga `TKPINTERA`) dengan 1 Kelas (`Kelompok A`), 1 Guru wali kelas dengan akun login (email `guru.tk@demo.test`, password `password`), 3 Siswa, 1 Asesmen ber-nilai (2 siswa lengkap, 1 siswa sengaja kosong) — dipakai Task 8 (Bab 4 sub-bagian PAUD) dan sebagai bukti fix elemen_cp/PAUD sesi sebelumnya benar-benar berfungsi.

- [ ] **Step 1: Tulis seeder**

```php
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
        $siswaList = collect($namaSiswa)->map(
            fn (string $nama) => Siswa::firstOrCreate(
                ['kelas_id' => $kelas->id, 'nis' => 'TK-'.Str::slug($nama)],
                ['lembaga_id' => $lembaga->id, 'nama_lengkap' => $nama]
            )
        );

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
```

`Siswa.nis` unik per `(lembaga_id, nis)` (bukan global) — pola `'nis' => 'TK-'.Str::slug($nama)` di atas aman karena scoped ke 1 lembaga baru dengan 3 nama berbeda, tidak akan tabrakan.

- [ ] **Step 2: Daftarkan di `DatabaseSeeder.php`**

Buka `database/seeders/DatabaseSeeder.php`, cari baris `PengajuanRaporSeeder::class,` (sekitar baris 86), tambahkan tepat setelahnya:

```php
            PengajuanRaporSeeder::class,
            LembagaPaudDemoSeeder::class,
            SarprasPengadaanDemoSeeder::class,
```

- [ ] **Step 3: Jalankan fresh migrate+seed**

Run: `php artisan migrate:fresh --seed`
Expected: seluruh seeder jalan tanpa error, termasuk `LembagaPaudDemoSeeder`.

- [ ] **Step 4: Verifikasi relasi lewat tinker — kelas & wali kelas**

Run:
```
php artisan tinker --execute '
$kelas = App\Models\Kelas::where("nama", "Kelompok A")->first();
echo "Kelas: {$kelas->nama}, Lembaga: {$kelas->lembaga->nama}, Wali Kelas: {$kelas->waliKelas?->nama}, PolaJam: {$kelas->pola_jam_id}" . PHP_EOL;
echo "Jumlah siswa: " . App\Models\Siswa::where("kelas_id", $kelas->id)->count() . PHP_EOL;
'
```
Expected: `Kelas: Kelompok A, Lembaga: TK Pintera Ceria, Wali Kelas: Bu Siti Wali Kelas TK, PolaJam: <angka bukan null>`, `Jumlah siswa: 3`.

- [ ] **Step 5: Verifikasi kelengkapan nilai terdeteksi tepat 1 siswa**

Run:
```
php artisan tinker --execute '
$kelas = App\Models\Kelas::where("nama", "Kelompok A")->first();
$semester = App\Models\Semester::where("lembaga_id", $kelas->lembaga_id)->first();
$hasil = (new App\Domains\Akademik\Services\RaporCalculationService())->kelengkapanNilaiKelas($kelas, $semester);
echo "Subjek belum lengkap: " . $hasil->count() . PHP_EOL;
foreach ($hasil as $sel) {
    echo "{$sel->subjek->nama}: " . $sel->siswaBelumLengkap->count() . " dari {$sel->totalSiswa} siswa belum lengkap" . PHP_EOL;
}
'
```
Expected: `Subjek belum lengkap: 1`, baris kedua menunjukkan `1 dari 3 siswa belum lengkap`.

- [ ] **Step 6: Verifikasi end-to-end lewat browser — dropdown kelas PAUD terisi**

Jalankan dev server (`php artisan serve` atau `composer run dev`), login sebagai `guru.tk@demo.test` / `password`, buka `/admin/asesmen/create` (route `guru.asesmen.create`).
Expected: dropdown "Pilih Kelas" menampilkan "Kelompok A", dropdown "Elemen CP (PAUD)" (bukan Mata Pelajaran) muncul otomatis tanpa toggle manual, checklist Tujuan Pembelajaran menampilkan komponen yang dibuat di Step 1. Ini pembuktian nyata bahwa fix elemen_cp/PAUD sesi sebelumnya benar-benar berfungsi dengan data seeder baru, bukan cuma lolos test otomatis.

- [ ] **Step 7: Commit**

```bash
git add database/seeders/LembagaPaudDemoSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat(akademik): seeder demo Lembaga PAUD untuk manual book & manual QA jalur elemen_cp

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: Audit gaya bahasa bab lama, lalu hapus

**Files:**
- Read (lalu hapus): `docs/manual-book/akademik/00-setup-lembaga.md`, `01-data-master.md`, `02-penjadwalan.md`, `03-presensi-jurnal.md`, `04-asesmen-nilai.md`, `05-rekap-rapor.md`, `06-kenaikan-kelas.md`, `lampiran-lintas-lembaga.md`

**Interfaces:**
- Produces: satu paragraf ringkasan gaya bahasa (nada, tingkat detail, panjang kalimat rata-rata) dicatat di laporan task ini — dipakai Task 3-12 sebagai referensi tanpa perlu baca file yang sudah dihapus.

- [ ] **Step 1: Baca ke-8 file, catat pola gaya bahasa**

Baca tiap file, perhatikan: bagaimana instruksi ditulis (formal tapi hangat? langsung ke aksi?), bagaimana istilah teknis dijelaskan ke pengguna awam, bagaimana "Untuk siapa" membedakan wewenang antar role, bagaimana "Kesalahan umum" dirumuskan. Tulis 1 paragraf ringkasan di laporan task.

- [ ] **Step 2: Putuskan nasib folder `images/`**

Buka `docs/manual-book/akademik/images/`, list isinya. Untuk tiap gambar: kalau berasal dari bab yang FITURNYA TIDAK BERUBAH sejak Juli (kandidat: Bab 0 Setup Lembaga, Bab 1 Data Master, Bab 2 Penjadwalan, Bab 6 Kenaikan Kelas — TAPI verifikasi dulu dengan membuka halaman aslinya di browser, jangan asumsikan dari nama file), boleh dipertahankan untuk dipakai ulang. Gambar dari Bab 3, 4, 5 (yang pasti berubah — field Keterangan baru, jalur PAUD, kelengkapan nilai, fix nama Wali Kelas) HARUS dihapus karena pasti sudah tidak representasi kondisi aktual.

- [ ] **Step 3: Hapus 8 file `.md` lama**

```bash
rm docs/manual-book/akademik/00-setup-lembaga.md docs/manual-book/akademik/01-data-master.md docs/manual-book/akademik/02-penjadwalan.md docs/manual-book/akademik/03-presensi-jurnal.md docs/manual-book/akademik/04-asesmen-nilai.md docs/manual-book/akademik/05-rekap-rapor.md docs/manual-book/akademik/06-kenaikan-kelas.md docs/manual-book/akademik/lampiran-lintas-lembaga.md
```

- [ ] **Step 4: Verifikasi**

Run: `ls docs/manual-book/akademik/`
Expected: cuma folder `images/` (isinya sesuai keputusan Step 2) — tidak ada file `.md` tersisa.

Tidak ada commit di task ini — `docs/**` gitignored, perubahan tidak akan muncul di `git status`.

---

### Task 3: Bab 0 — Setup Lembaga

**Files:**
- Create: `docs/manual-book/akademik/00-setup-lembaga.md`

**Interfaces:**
- Consumes: ringkasan gaya bahasa dari Task 2. Login admin lembaga/yayasan existing (cek `database/seeders/EssentialUserSeeder.php` atau `UserSeeder.php` untuk kredensial demo admin yang benar — JANGAN menebak email/password, baca file seeder-nya).
- Produces: `00-setup-lembaga.md` mengikuti format 5-bagian, jadi prasyarat untuk Task 4-12 (semua bab lain link balik ke sini untuk hal "lembaga harus sudah ada").

- [ ] **Step 1: Cari kredensial demo admin**

Baca `database/seeders/EssentialUserSeeder.php` dan `database/seeders/UserSeeder.php`, catat email+password akun admin lembaga (untuk SDIT PINTERA) yang valid untuk login.

- [ ] **Step 2: Coba fitur di browser**

Dev server jalan, login sebagai admin lembaga, buka halaman edit Lembaga (route `admin.lembaga.edit`). Perhatikan KHUSUS field `nama_kepala_sekolah` (dipakai fix rapor PDF sesi ini — pastikan field ini ada di form dan berfungsi). Coba juga halaman index/filter Lembaga (route `admin.lembaga.index`) untuk dropdown `bentuk_pendidikan` (baru saja diretrofit ke `BentukPendidikan::cases()` sesi ini — pastikan menampilkan 9 pilihan lengkap termasuk KB/TPA/SPS/TK).

- [ ] **Step 3: Screenshot**

Run: `node scripts/manual-book-screenshots.mjs --bab=00`
(Kalau script belum punya definisi bab `00` untuk skenario baru di atas, tambahkan config screenshot yang sesuai di dalam script sebelum run — lihat pola skenario existing lain di file yang sama sebagai referensi.)

- [ ] **Step 4: Tulis prosa**

Tulis `00-setup-lembaga.md` mengikuti format 5-bagian, berdasarkan apa yang BENAR-BENAR terjadi di Step 2 (bukan diasumsikan). "Untuk siapa": Admin Lembaga/Yayasan. "Prasyarat": tidak ada (ini bab pertama).

- [ ] **Step 5: Self-check Definition of Done**

Setiap langkah di "Langkah-langkah" sudah dicoba sendiri (Step 2) dan screenshot diambil dari hasil percobaan itu (Step 3) — bukan retroactive. Kalau ada penyimpangan dari dugaan awal, tulis apa adanya.

---

### Task 4: Bab 1 — Data Master

**Files:**
- Create: `docs/manual-book/akademik/01-data-master.md`

**Interfaces:**
- Consumes: `00-setup-lembaga.md` (prasyarat: Lembaga sudah ada).
- Produces: `01-data-master.md`, jadi prasyarat Task 5-12 (Tahun Ajaran/Semester/Mata Pelajaran/Kurikulum Assignment dipakai di semua bab operasional).

- [ ] **Step 1: Coba fitur di browser**

Login admin lembaga/akademik, coba berurutan: Tahun Ajaran (`admin.tahun-ajaran.index/create`, termasuk aksi "Aktifkan"), Semester (`admin.semester.store/activate`), Mata Pelajaran (`admin.mata-pelajaran.index/create/edit`), Kurikulum Assignment (`admin.kurikulum-assignment.index/create`).

- [ ] **Step 2: Screenshot**

Run: `node scripts/manual-book-screenshots.mjs --bab=01`

- [ ] **Step 3: Tulis prosa**

"Untuk siapa": Admin Lembaga/Operator Akademik. "Prasyarat": link ke Bab 0 (Lembaga harus ada). Bagi jadi sub-bagian per fitur (Tahun Ajaran & Semester, Mata Pelajaran, Kurikulum Assignment) mengikuti pola existing.

- [ ] **Step 4: Self-check Definition of Done** (sama seperti Task 3 Step 5)

---

### Task 5: Bab 2 — Penjadwalan

**Files:**
- Create: `docs/manual-book/akademik/02-penjadwalan.md`

**Interfaces:**
- Consumes: `01-data-master.md` (prasyarat: Tahun Ajaran/Semester/Mata Pelajaran sudah ada).
- Produces: `02-penjadwalan.md`, prasyarat Task 6 (Presensi/Jurnal butuh Jadwal Pelajaran sudah ada) dan Task 7 (Asesmen butuh guru sudah punya Jadwal Pelajaran).

- [ ] **Step 1: Coba fitur di browser**

Coba berurutan: Kelas (`admin.kelas.index/create`, termasuk assign wali kelas & pola jam), Pola Jam + Jam Pelajaran (`admin.pola-jam.*`, `admin.jam-pelajaran.*`), Jadwal Pelajaran (`admin.jadwal-pelajaran.index/create`, termasuk cek bentrok guru/ruangan), Kalender Akademik.

- [ ] **Step 2: Screenshot**

Run: `node scripts/manual-book-screenshots.mjs --bab=02`

- [ ] **Step 3: Tulis prosa**

"Untuk siapa": Admin Lembaga/Operator Akademik. "Prasyarat": link ke Bab 1.

- [ ] **Step 4: Self-check Definition of Done**

---

### Task 6: Bab 3 — Presensi & Jurnal (+ field Keterangan baru)

**Files:**
- Create: `docs/manual-book/akademik/03-presensi-jurnal.md`

**Interfaces:**
- Consumes: `02-penjadwalan.md` (prasyarat: guru sudah punya Jadwal Pelajaran).
- Produces: `03-presensi-jurnal.md`.

- [ ] **Step 1: Coba fitur di browser, TERMASUK field baru**

Login guru (kredensial dari `EssentialUserSeeder`/`UserSeeder` untuk guru SD), buka Jurnal KBM (`guru.jurnal-kbm.index/show/update`). Set status siswa ke Izin atau Sakit, PASTIKAN field **Keterangan** (baru ditambahkan sesi ini) muncul untuk status itu dan tersimpan dengan benar — cek juga bahwa field ini TIDAK muncul untuk status Hadir/Alpa/Terlambat.

- [ ] **Step 2: Screenshot**

Run: `node scripts/manual-book-screenshots.mjs --bab=03`

- [ ] **Step 3: Tulis prosa**

"Untuk siapa": Guru. "Prasyarat": link ke Bab 2. Tambahkan penjelasan field Keterangan sebagai bagian dari langkah isi presensi (bukan sub-bagian terpisah, karena memang bagian dari 1 alur yang sama).

- [ ] **Step 4: Self-check Definition of Done**

---

### Task 7: Bab 4 — Asesmen & Nilai (+ sub-bagian Sisi Guru PAUD)

**Files:**
- Create: `docs/manual-book/akademik/04-asesmen-nilai.md`

**Interfaces:**
- Consumes: `02-penjadwalan.md` (prasyarat: Jadwal Pelajaran), seeder dari Task 1 (akun `guru.tk@demo.test`).
- Produces: `04-asesmen-nilai.md`, prasyarat Task 9 (Rekap Rapor butuh Asesmen & Nilai sudah ada).

- [ ] **Step 1: Coba fitur mata pelajaran (guru SD)**

Login guru SD, coba Komponen Penilaian (`guru.komponen-penilaian.index/create`) dan Asesmen (`guru.asesmen.index/create/show/update-nilai`) untuk mata pelajaran yang diajar.

- [ ] **Step 2: Coba fitur PAUD (guru TK dari seeder Task 1)**

Login `guru.tk@demo.test`, buka `guru.asesmen.create` — konfirmasi dropdown Kelas ("Kelompok A") dan Elemen CP muncul otomatis (BUKAN toggle manual — fix sesi ini), buat Asesmen, buka halaman nilai, konfirmasi 1 siswa memang kosong (dari data seeder Task 1) dan 2 lainnya terisi.

- [ ] **Step 3: Screenshot (kecuali sub-bagian PAUD)**

Run: `node scripts/manual-book-screenshots.mjs --bab=04`
Sub-bagian "Sisi Guru PAUD" TIDAK butuh screenshot baru (pengecualian resmi, lihat Global Constraints) — cukup dicoba di Step 2, ditulis berdasarkan hasil percobaan itu, dengan referensi visual ke screenshot "Sisi Guru" (mata pelajaran) yang sudah diambil di Step 1 plus penjelasan eksplisit bagian yang beda (dropdown Elemen CP menggantikan Mata Pelajaran, nilai berupa teks naratif bukan angka).

- [ ] **Step 4: Tulis prosa**

"Untuk siapa": Admin Akademik & Guru. "Prasyarat": Bab 2. Struktur: Komponen Penilaian (Admin/Guru mata pelajaran) → Asesmen & Nilai mata pelajaran (Guru) → **sub-bagian baru "Sisi Guru PAUD"** (jelaskan bedanya dari mata pelajaran, sesuai Step 2-3).

- [ ] **Step 5: Self-check Definition of Done** (PAUD dikecualikan cuma soal screenshot, tetap wajib dicoba — sudah di Step 2)

---

### Task 8: Bab 5 — Rekap Rapor (+ efek 2 fix sesi ini)

**Files:**
- Create: `docs/manual-book/akademik/05-rekap-rapor.md`

**Interfaces:**
- Consumes: `04-asesmen-nilai.md` (prasyarat: nilai sudah diisi).
- Produces: `05-rekap-rapor.md`, prasyarat Task 11 (Kenaikan Kelas biasanya di akhir semester setelah rapor selesai).

- [ ] **Step 1: Coba alur Catatan Wali Kelas & Ajukan Rapor, PASTIKAN peringatan kelengkapan nilai muncul**

Login guru wali kelas SD (yang kelasnya punya nilai lengkap) DAN `guru.tk@demo.test` (kelasnya PUNYA 1 siswa nilai kosong dari seeder Task 1). Buka `guru.rapor.catatan.index` untuk kedua kelas — konfirmasi banner peringatan kelengkapan nilai MUNCUL untuk kelas TK (1 siswa kosong) dan TIDAK MUNCUL untuk kelas SD (kalau memang lengkap). Isi Catatan Wali Kelas, coba Ajukan Rapor (perhatikan `confirm()` dialog muncul untuk kelas yang belum lengkap).

- [ ] **Step 2: Coba alur Verifikasi & Persetujuan, PASTIKAN tabel kelengkapan di halaman Waka**

Login Waka Kurikulum, buka `admin.rapor.persetujuan.show` untuk pengajuan kelas TK — konfirmasi tabel rincian kelengkapan nilai muncul, tombol Setujui/Tolak tetap aktif (bukan hard block). Login Kepala Sekolah untuk persetujuan akhir.

- [ ] **Step 3: Cetak PDF, PASTIKAN nama Wali Kelas & Kepala Sekolah benar**

Cetak rapor PDF (`admin.rapor.cetak` atau `admin.rapor.persetujuan.cetak`) untuk seorang siswa — konfirmasi kolom tanda tangan "Wali Kelas" menampilkan nama wali kelas ASLI (`Kelas.wali_kelas_guru_id`), BUKAN nama Waka Kurikulum yang verifikasi (bug lama, sudah diperbaiki sesi ini) — dan "Kepala Sekolah" menampilkan `Lembaga.nama_kepala_sekolah`.

- [ ] **Step 4: Screenshot**

Run: `node scripts/manual-book-screenshots.mjs --bab=05`

- [ ] **Step 5: Tulis prosa**

"Untuk siapa": Wali Kelas, Waka Kurikulum, Kepala Sekolah — jelaskan pembagian tahap (Ajukan → Verifikasi → Setujui). "Prasyarat": Bab 4. Tambahkan bagian baru untuk peringatan kelengkapan nilai (jelaskan sifatnya informative-only, bukan blocker) di kedua titik (wali kelas & Waka).

- [ ] **Step 6: Self-check Definition of Done**

---

### Task 9: Bab 6 — Kenaikan Kelas

**Files:**
- Create: `docs/manual-book/akademik/06-kenaikan-kelas.md`

**Interfaces:**
- Consumes: `05-rekap-rapor.md` (prasyarat: rapor semester genap sudah disetujui, secara konseptual).
- Produces: `06-kenaikan-kelas.md`.

- [ ] **Step 1: Coba fitur di browser — HATI-HATI, aksi ini mengubah data siswa secara permanen**

Login admin akademik, buka `admin.kenaikan-kelas.index`. **HANYA lakukan GET/lihat halaman dan opsi yang tersedia — JANGAN submit form kenaikan kelas sungguhan** kecuali di database seed yang memang boleh dikorbankan (disarankan: coba di seed data SD yang sudah ada, bukan seeder PAUD baru dari Task 1, supaya kelas PAUD tetap utuh untuk dipakai ulang kalau perlu re-screenshot bab lain nanti). Kalau perlu screenshot hasil submit, jalankan `migrate:fresh --seed` lagi setelahnya untuk mengembalikan data.

- [ ] **Step 2: Screenshot**

Run: `node scripts/manual-book-screenshots.mjs --bab=06`

- [ ] **Step 3: Tulis prosa**

"Untuk siapa": Admin Akademik. "Prasyarat": Bab 5. Tambahkan catatan tegas di "Kesalahan umum" bahwa aksi ini irreversible.

- [ ] **Step 4: Self-check Definition of Done**

---

### Task 10: Lampiran — Lintas Lembaga

**Files:**
- Create: `docs/manual-book/akademik/lampiran-lintas-lembaga.md`

**Interfaces:**
- Consumes: konteks dari bab-bab sebelumnya (khususnya Kalender Akademik di Bab 2).
- Produces: `lampiran-lintas-lembaga.md`.

- [ ] **Step 1: Coba fitur di browser**

Login aktor scope yayasan, coba fitur lintas-lembaga yang relevan (kalender akademik nasional, atau fitur lain yang didokumentasikan versi lama — cek isi Task 2 Step 1 untuk tahu topik persis lampiran lama sebelum dihapus).

- [ ] **Step 2: Screenshot**

Run: `node scripts/manual-book-screenshots.mjs --bab=lampiran`

- [ ] **Step 3: Tulis prosa**

"Untuk siapa": aktor scope yayasan. "Prasyarat": Bab 2.

- [ ] **Step 4: Self-check Definition of Done**

---

### Task 11: Bab 7 (BARU) — Ruang Orang Tua

**Files:**
- Create: `docs/manual-book/akademik/07-ruang-orang-tua.md`

**Interfaces:**
- Consumes: akun demo Orang Tua existing (5 pasang sudah terverifikasi bisa login — cari kredensial pastinya di `database/seeders/OrangTuaKaryawanSeeder.php`, JANGAN menebak).
- Produces: `07-ruang-orang-tua.md`.

- [ ] **Step 1: Cari kredensial demo Orang Tua**

Baca `database/seeders/OrangTuaKaryawanSeeder.php`, catat email+password salah satu akun Orang Tua yang linked ke siswa dengan data nilai/presensi.

- [ ] **Step 2: Coba fitur di browser**

Login sebagai Orang Tua, coba: Nilai Anak (`admin.nilai-anak.index`, termasuk unduh Rapor PDF kalau statusnya Disetujui), Jadwal Anak (`admin.jadwal-anak.index`), Riwayat Izin/Sakit Anak (`admin.riwayat-izin-sakit-anak.index`, dengan filter rentang tanggal).

- [ ] **Step 3: Screenshot**

Run: `node scripts/manual-book-screenshots.mjs --bab=07`
(Kalau script belum punya config untuk bab ini, tambahkan skenario baru mengikuti pola existing di file yang sama — perlu login sebagai akun Orang Tua, bukan Admin/Guru.)

- [ ] **Step 4: Tulis prosa**

"Untuk siapa": Orang Tua (self-service, baca-saja — TIDAK ada "Prasyarat" dalam arti urutan pengerjaan, tapi tetap jelaskan bahwa data yang tampil berasal dari apa yang guru/wali kelas isi di bab-bab sebelumnya). Format tetap 5-bagian, "Kesalahan umum" fokus ke kebingungan realistis (mis. "kenapa nilai anak saya belum muncul" → karena guru belum submit/rapor belum disetujui).

- [ ] **Step 5: Self-check Definition of Done**

---

### Task 12: Bab 8 (BARU) — Ruang Siswa

**Files:**
- Create: `docs/manual-book/akademik/08-ruang-siswa.md`

**Interfaces:**
- Consumes: akun demo Siswa existing (`siswa.sd@demo.test`, dikonfirmasi bisa login hari ini — cek password aslinya di `database/seeders/OrangTuaKaryawanSeeder.php` atau seeder terkait, JANGAN menebak).
- Produces: `08-ruang-siswa.md`.

- [ ] **Step 1: Coba fitur di browser**

Login sebagai Siswa, coba: Nilai & Rapor (`admin.nilai-rapor-saya.index`, termasuk unduh PDF), Jadwal Pelajaran (`admin.jadwal-pelajaran-saya.index`, termasuk toggle tampilan matriks/daftar), Presensi Saya (`admin.presensi-saya.index`, termasuk kartu ringkasan status kehadiran & filter tanggal).

- [ ] **Step 2: Screenshot**

Run: `node scripts/manual-book-screenshots.mjs --bab=08`

- [ ] **Step 3: Tulis prosa**

"Untuk siapa": Siswa (self-service, baca-saja). Format tetap 5-bagian.

- [ ] **Step 4: Self-check Definition of Done**

---

### Task 13: `README.md` — Peta Alur Kerja per Role

**Files:**
- Create: `docs/manual-book/akademik/README.md`

**Interfaces:**
- Consumes: SEMUA bab dari Task 3-12 harus sudah ada (link `bab.md#bagian` harus valid).
- Produces: `README.md`, titik masuk utama manual book.

- [ ] **Step 1: Verifikasi urutan checklist per role ke kode/UI aktual**

Draft urutan dari spec (§4) HARUS diverifikasi ulang, bukan disalin mentah:
- Admin Lembaga/Operator Akademik: Tahun Ajaran & Semester → Kurikulum Assignment → Kelas & Pola Jam → Jadwal Pelajaran → Kalender Akademik
- Guru: Komponen Penilaian (TP) mapel yang diajar → RPP → Jurnal KBM/Presensi harian → Asesmen & Input Nilai
- Wali Kelas (lanjutan Guru): Catatan Wali Kelas per siswa → Ajukan Rapor
- Waka Kurikulum: Verifikasi Rapor
- Kepala Sekolah: Persetujuan Akhir Rapor

Kalau ternyata ada urutan yang salah/kurang lengkap dari hasil menulis Task 3-9, perbaiki dan catat di laporan task kenapa berbeda dari draft ini.

- [ ] **Step 2: Tulis `README.md`**

Format (BUKAN 5-bagian, format tersendiri sesuai spec §4):
1. `# Manual Book Akademik — Peta Alur Kerja per Role`
2. Satu paragraf cara pakai
3. Kelompok "Role yang Mengisi Data" — checklist bernomor per role, tiap nomor `[Nama fitur](bab.md#bagian)`
4. Kelompok "Role Self-Service (Lihat Saja)" — daftar "cek di mana" untuk Orang Tua (link ke `07-ruang-orang-tua.md`) dan Siswa (link ke `08-ruang-siswa.md`)

- [ ] **Step 3: Verifikasi tiap link tidak mati**

Untuk tiap `[teks](file.md#anchor)` di `README.md`, buka file targetnya, pastikan section dengan judul yang cocok anchor-nya benar-benar ada (anchor Markdown = judul di-lowercase, spasi jadi dash).

---

### Task 14: Build, Republish, Commit, Handoff

**Files:**
- Read: seluruh `docs/manual-book/akademik/*.md` dan `images/`
- Modify: `PETA_PENGEMBANGAN.md`
- Create: `.agents/logs/2026-09-05-rombak-manual-book-akademik.md`

**Interfaces:**
- Consumes: seluruh output Task 1-13.

- [ ] **Step 1: Build Artifact**

Run: `node scripts/manual-book-artifact/build.mjs`
Expected: `scripts/manual-book-artifact/dist/manual-book-akademik.html` ter-generate tanpa error, ukuran wajar (embed semua screenshot base64).

- [ ] **Step 2: Republish ke URL yang SAMA**

Publish `scripts/manual-book-artifact/dist/manual-book-akademik.html` lewat Artifact tool dengan parameter `url` = `https://claude.ai/code/artifact/92e6b639-d846-48ad-9e42-2a270abc5e03` — JANGAN publish tanpa parameter `url` (itu akan bikin link baru, dilarang di Global Constraints).

- [ ] **Step 3: Verifikasi tidak ada perubahan kode yang belum ter-commit**

Run: `git status --short`
Expected: bersih kecuali `?? storage/debugbar/` (kalau ada) — seeder + `DatabaseSeeder.php` sudah di-commit di Task 1 Step 7, sisanya (`docs/**`) memang tidak akan muncul karena gitignored.

- [ ] **Step 4: Tulis handoff log**

Tulis `.agents/logs/2026-09-05-rombak-manual-book-akademik.md` — ringkas apa yang ditulis ulang, bug/temuan apa (kalau ada) yang muncul selama proses "coba fitur langsung" (Definition of Done §3.4 spec), keputusan yang berbeda dari spec/plan (kalau ada) beserta alasannya.

- [ ] **Step 5: Update `PETA_PENGEMBANGAN.md`**

Tambahkan entri baru merangkum rombak manual book ini, link ke handoff log, dan URL Artifact yang di-republish.

---

## Self-Review (dilakukan penulis plan sebelum menyerahkan ke kickoff)

**1. Cakupan spec:** §3.1 (hapus+tulis ulang) → Task 2. §3.2 (11 file) → Task 3-13. §3.3 (format 5-bagian) → Global Constraints + tiap task chapter. §3.4 (coba langsung) → Global Constraints + Step self-check tiap task. §4 (README) → Task 13. §5 (seeder) → Task 1. §6 (pengecualian PAUD) → Task 7 Step 3. §7 (proses, republish, no-worktree) → Global Constraints + Task 14. §8 (di luar cakupan) → tidak ada task yang menyentuh Keuangan atau bikin akun PAUD ekstra — sesuai.

**2. Placeholder scan:** Task 1 (seeder) ditulis lengkap, bukan deskripsi. Task 3-13 sengaja TIDAK berisi prosa manual book jadi (itu KONTEN yang bergantung hasil "coba fitur langsung" — tidak bisa ditentukan di muka tanpa melanggar Definition of Done spec §3.4 sendiri), tapi setiap task punya: file exact, route/URL exact untuk dicoba, command exact untuk screenshot, dan kriteria selesai exact (self-check). Ini bukan placeholder gaya "TBD" — ini pembagian kerja yang memang tidak bisa dipra-tulis untuk proyek dokumentasi (beda dari proyek kode).

**3. Konsistensi tipe:** nama file bab (`00-setup-lembaga.md` dst.) konsisten dipakai sebagai referensi Prasyarat lintas task. Route name yang dipakai (`admin.nilai-anak.index`, `admin.jadwal-pelajaran-saya.index`, dst.) sudah diverifikasi ke `routes/admin/*.php` sesi ini, bukan tebakan. `RaporCalculationService::kelengkapanNilaiKelas()` dipakai persis sama di Task 1 Step 5 dan disebut lagi di Task 8 — nama method konsisten.
