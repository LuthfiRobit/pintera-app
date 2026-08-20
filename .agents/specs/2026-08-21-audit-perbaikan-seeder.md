# Audit & Perbaikan Seeder Pintera — Spec 1: Perbaikan Bug + Konvensi

## 1. Latar Belakang

Hasil audit menyeluruh (2026-08-20/21) terhadap seluruh 59 file `database/seeders/*.php` menemukan 18 temuan (4 Critical, 4 High, 3 Medium, 6 Low serta 1 dokumentasi menyesatkan ditemukan susulan saat spec ini ditulis — lihat §6.5). Spec ini menutup **seluruh temuan bug/struktur** dan menambahkan **dokumen konvensi permanen** supaya seeder modul baru di masa depan konsisten.

**Di luar cakupan spec ini** (lihat Spec 2 terpisah, `.agents/specs/2026-08-21-rebranding-data-demo-pintera.md`): pemendekan email dummy, rebranding nama yayasan/lembaga ke "Pintera", dan penggantian nama manusia (siswa/guru/karyawan/orang tua) ke pola netral. Dua spec ini sengaja dipisah karena tidak saling bergantung dan bisa dieksekusi/ditest independen — keputusan eksplisit user 2026-08-21.

Baseline kode: branch `rbac-v2`, `DatabaseSeeder.php` per commit terakhir yang dibaca saat spec ditulis (lihat isi lengkap §2 di bawah — plan implementasi WAJIB verifikasi ulang isi file kalau ada commit baru sebelum dieksekusi).

## 2. Urutan Eksekusi Saat Ini (`DatabaseSeeder::run()`)

```php
$this->call([PermissionSeeder::class]);
Artisan::call('permissions:sync');
$this->call([
    RoleSeeder::class, YayasanSeeder::class, JabatanTambahanMasterSeeder::class,
    JenisKaryawanMasterSeeder::class, WhatsAppTemplateSeeder::class, LembagaSeeder::class,
    EssentialUserSeeder::class, UserSeeder::class, TahunAjaranSeeder::class, SemesterSeeder::class,
    GuruSeeder::class, RiwayatPendidikanGuruSeeder::class, SertifikasiGuruSeeder::class,
    GuruJabatanTambahanSeeder::class, LembagaDataPeriodikSeeder::class, LayananKhususLembagaSeeder::class,
    ProgramInklusiLembagaSeeder::class, EkstrakurikulerLembagaSeeder::class, JenisTesMasterSeeder::class,
    GelombangPpdbSeeder::class, JalurPpdbSeeder::class, FormulirFieldSeeder::class,
    DokumenSyaratPpdbSeeder::class, SeleksiPpdbSeeder::class, JenisTagihanSeeder::class,
    NominalTagihanJalurSeeder::class, CalonMuridSeeder::class, PendaftaranSeeder::class,
    DokumenPendaftaranSeeder::class, HasilSeleksiSeeder::class, SkPpdbSeeder::class,
    TagihanSeeder::class, TagihanItemSeeder::class, SkemaCicilanSeeder::class, CicilanSeeder::class,
    PembayaranSeeder::class, AkunPendaftarSeeder::class, GelombangJalurSeeder::class,
    MataPelajaranSeeder::class, PolaJamSeeder::class, JamPelajaranSeeder::class, KelasSeeder::class,
    SiswaSeeder::class, JadwalPelajaranSeeder::class, SesiPembelajaranSeeder::class, PresensiSeeder::class,
    KomponenPenilaianSeeder::class, AsesmenSeeder::class, NilaiSiswaSeeder::class,
    OrangTuaKaryawanSeeder::class, PendampinganSeeder::class, KeuanganDemoSeeder::class,
    SarprasPermissionSeeder::class, PengadaanPermissionSeeder::class, WorkflowDefinitionSeeder::class,
    SarprasPengadaanDemoSeeder::class,
]);
```

**Bug akar (Critical):** `RoleSeeder` (posisi ~1) memanggil `$role->syncPermissions(Permission::pluck('name')->all())` untuk `yayasan_super_admin`, tapi `SarprasPermissionSeeder`/`PengadaanPermissionSeeder` (posisi ~52-53, membuat 21 permission Sarpras+Pengadaan) baru jalan di akhir. Snapshot permission super admin jadi tidak lengkap.

## 3. Restrukturisasi RBAC

### 3.1 Komponen

- **`PermissionSeeder.php`** (dimodifikasi) — satu-satunya pemilik tabel `permissions`. Ditambah 11 permission dari `SarprasPermissionSeeder` + 10 permission dari `PengadaanPermissionSeeder` (semua via `Permission::firstOrCreate(['name' => ...])`, digabung ke daftar yang sudah ada).
- **`RoleSeeder.php`** (dimodifikasi) — satu-satunya pemilik tabel `roles`. Ditambah role `bendahara_yayasan` dan `admin_sarpras` (dipindah dari 2 file yang dihapus) via `Role::firstOrCreate(['name' => ...])`. **Baris `$role->syncPermissions(...)` DIHAPUS dari file ini.**
- **`RolePermissionAssignmentSeeder.php`** (BARU) — satu-satunya pemilik pivot `role_has_permissions`. Isi:
  - Seluruh mapping role→permission yang sebelumnya ada di `SarprasPermissionSeeder`/`PengadaanPermissionSeeder`, dengan nama role `'super_admin'` DIPERBAIKI jadi `'yayasan_super_admin'` di semua kemunculan.
  - `Role::findByName('yayasan_super_admin')->syncPermissions(Permission::all());` — dipindah dari `RoleSeeder`, sekarang dijamin lengkap karena posisinya paling akhir.

### 3.2 Perubahan `DatabaseSeeder::run()`

- Hapus baris `SarprasPermissionSeeder::class` dan `PengadaanPermissionSeeder::class` dari array.
- Tambah `RolePermissionAssignmentSeeder::class` sebagai **entri PALING AKHIR** dari seluruh array (setelah `SarprasPengadaanDemoSeeder::class`).
- Urutan lain tidak berubah.

### 3.3 File Dihapus

`database/seeders/SarprasPermissionSeeder.php`, `database/seeders/PengadaanPermissionSeeder.php` — isinya sudah dipindah seluruhnya ke §3.1.

### 3.4 Akun Demo Baru

Tambah 1 akun di `EssentialUserSeeder.php` dengan role `admin_sarpras` (role ini sekarang ada tapi tidak pernah dipegang user manapun — High Finding 2.1). Pola email & password mengikuti akun demo lain di file yang sama (akan disesuaikan lagi oleh Spec 2 untuk pola email pendek).

## 4. Fix Idempotency `PendampinganSeeder.php`

7 pemanggilan `Kasus::firstOrCreate()` (fungsi `buatKasusMenungguConsent`, `buatKasusDitugaskan`, `buatKasusBerjalan`, `buatKasusEskalasi`, `buatKasusSelesai`, `buatKasusRinganSmp`, `buatKasusRinganSd`) memakai key `['siswa_id' => ..., 'status' => StatusKasus::X]` (kadang plus `kategori_masalah`). Karena `status` adalah kolom state-machine yang berubah oleh aplikasi (`Diajukan → MenungguConsent → Ditugaskan → Berjalan/Eskalasi → Selesai`), reseed pada data yang statusnya sudah berubah membuat baris `Kasus` duplikat.

**Perbaikan:** untuk SETIAP dari 7 pemanggilan, ganti key `firstOrCreate` menjadi `['siswa_id' => ..., 'kategori_masalah' => ...]` (kombinasi ini stabil — tidak berubah oleh alur bisnis). Field `status` dipindah ke array *values* kedua (`firstOrCreate($key, $values)`), tetap dipakai sebagai status awal saat baris pertama kali dibuat, tapi tidak lagi ikut menentukan pencocokan baris lama.

**Catatan:** karena `kategori_masalah` di dalam file ini kadang memakai deskripsi yang mirip antar skenario, plan implementasi WAJIB membaca isi 7 fungsi tersebut dan memastikan `kategori_masalah` yang dipakai sebagai key benar-benar unik per skenario demo (kalau ada 2 skenario dengan `siswa_id` + `kategori_masalah` sama persis, salah satunya harus diberi variasi kategori atau siswa berbeda).

## 5. Environment Guard untuk Password Demo

Tambahkan guard identik di awal method `run()` pada 6 file berikut: `EssentialUserSeeder.php`, `UserSeeder.php`, `OrangTuaKaryawanSeeder.php`, `SiswaSeeder.php`, `SarprasPengadaanDemoSeeder.php`, `AkunPendaftarSeeder.php`.

```php
public function run(): void
{
    if (! app()->environment(['local', 'testing'])) {
        $this->command?->warn(static::class.': dilewati, hanya boleh jalan di environment local/testing.');

        return;
    }

    // ... kode seeder yang sudah ada, tidak berubah
}
```

**Efek:** di `APP_ENV=local` (dev sehari-hari) maupun `APP_ENV=testing` (test suite), seeder berjalan normal seperti sekarang — password tetap `'password'`, tidak ada perubahan alur kerja developer. Guard ini murni pencegahan kalau `migrate:fresh --seed` tidak sengaja dijalankan di environment lain.

**Perhatian dependency:** beberapa seeder lain (`TagihanSeeder`, `SkemaCicilanSeeder`, `CicilanSeeder`, `PembayaranSeeder`, `KeuanganDemoSeeder`, dll.) query data yang dibuat oleh 6 file di atas (misal `Pendaftaran::where('email_pendaftaran', 'wali.cicilan-demo@...')`). Karena mereka sudah memakai pola *lookup-lalu-skip-kalau-tidak-ketemu* (`if (! $x) { continue; }` / `if (! $x) { return; }`), guard ini AMAN — seeder downstream akan otomatis skip juga (tidak error) kalau environment bukan local/testing dan datanya memang tidak ada. Plan implementasi WAJIB memverifikasi ini dengan menjalankan `migrate:fresh --seed` dengan `APP_ENV` di-override sementara ke `production` secara lokal dan memastikan TIDAK ADA error (hanya warning + skip berantai).

## 6. Kerapian Relasi & Cleanup

### 6.1 `RolePermissionSeeder.php` — dokumentasi tujuan

Tambah doc-comment di kepala class:

```php
/**
 * Fixture RBAC ringan khusus untuk test (dipanggil via $this->seed(RolePermissionSeeder::class)
 * di ~30 file test). Sengaja TIDAK dipanggil dari DatabaseSeeder::run() — hanya membuat
 * permission + role tanpa data domain lain, supaya test RBAC-only bisa jalan cepat.
 */
```

### 6.2 Konsolidasi assignment role `kepsek`/`adm`

`SarprasPengadaanDemoSeeder.php` saat ini re-fetch `kepsek@sistem.test`/`adm@sistem.test` (dibuat `EssentialUserSeeder`) dan memanggil `assignRole()`/`givePermissionTo()` lagi. Hapus baris-baris assignment ini dari `SarprasPengadaanDemoSeeder`; pastikan seluruh role/permission untuk kedua akun ini sudah lengkap di `EssentialUserSeeder.php` (assignment role Sarpras/Pengadaan untuk `kepsek`/`adm` dipindah ke sana). `SarprasPengadaanDemoSeeder` sesudahnya hanya boleh melakukan `User::where(...)->first()` untuk MEMAKAI akun ini (misal sebagai `$kasir` di seed Pembayaran), tidak lagi mengubah role/permission-nya.

### 6.3 `WorkflowDefinitionSeeder.php` — validasi role

Sebelum tiap `WorkflowStep::updateOrCreate(...)` yang `approver_value`-nya adalah nama role (`'bendahara_yayasan'`, `'kepala_sekolah'`, `'admin_akademik'`), tambahkan:

```php
abort_unless(
    \Spatie\Permission\Models\Role::where('name', $approverValue)->exists(),
    500,
    "WorkflowDefinitionSeeder: role '{$approverValue}' tidak ditemukan — cek RoleSeeder."
);
```

### 6.4 Cleanup Low-Priority

- **`PermissionSeeder.php`**: pindahkan blok `Permission::whereIn([...8 nama lama...])->delete()` (baris ~18-22) ke migration baru `database/migrations/xxxx_xx_xx_cleanup_legacy_flat_permissions.php` (`up()` isinya persis blok yang dipindah, `down()` no-op karena ini cleanup satu-arah). Hapus blok dari seeder.
- **`OrangTuaKaryawanSeeder.php`, `CalonMuridSeeder.php`, `KeuanganDemoSeeder.php`**: ganti NIK dummy (format `'3273...'` — prefix kode wilayah Bandung asli) ke prefix jelas-fiktif `'0000...'` (mis. `'0000019901850051'`), tetap 16 digit.
- **`SeleksiPpdbSeeder.php`**: ganti tanggal `jadwal` hardcoded absolut (`'2026-08-20 08:00:00'`, `'2025-02-20 08:00:00'`, dst) ke relatif memakai pola yang sama dengan `GelombangPpdbSeeder` (mis. `now()->addDays(N)->format('Y-m-d H:i:s')`, N disesuaikan per skenario supaya urutan tanggal antar jenis tes tetap masuk akal).
- **`SesiPembelajaranSeeder.php`**: tambah komentar di atas pemanggilan `Carbon::yesterday()` menjelaskan bahwa reseed di hari kalender berbeda akan menambah baris baru (bukan bug, karakteristik data). Tidak ada perubahan logic.

### 6.5 `KeuanganDemoSeeder.php` — perbaiki dokumentasi yang kontradiktif

Ditemukan saat spec ini ditulis: file terdaftar di `DatabaseSeeder::run()` (baris 78) TAPI doc-comment di kepala file `KeuanganDemoSeeder.php` menyatakan sebaliknya ("Standalone -- TIDAK dipanggil dari DatabaseSeeder::run()..., jalankan manual: php artisan db:seed --class=KeuanganDemoSeeder"). Keputusan (user, 2026-08-21): **pertahankan posisinya di `DatabaseSeeder`**, perbaiki komentarnya supaya sesuai kenyataan, DAN baca ulang seluruh isi file untuk memastikan tidak ada bagian lain yang masih berasumsi "standalone" (misal asumsi soal urutan data yang hanya valid kalau dijalankan setelah semua seeder lain selesai — perlu dicek apakah asumsi itu tetap valid mengingat posisinya sekarang memang di akhir `DatabaseSeeder`).

Ganti doc-comment jadi:

```php
/**
 * Data demo untuk mencoba Modul Keuangan lewat browser secara end-to-end.
 *
 * Dipanggil dari DatabaseSeeder::run() (posisi setelah OrangTuaKaryawanSeeder dan
 * PendampinganSeeder) untuk demo end-to-end otomatis setiap fresh seed. Bergantung pada
 * akun demo orang tua dari OrangTuaKaryawanSeeder + wallet otomatis dari StudentCreated event
 * — keduanya sudah terjamin ada di titik ini karena urutan DatabaseSeeder.
 */
```

Plan implementasi WAJIB membaca ulang isi `seedForSiswa()` dan `buatTagihan()` untuk memastikan tidak ada logic lain yang bergantung pada asumsi "dijalankan terpisah/manual" yang sekarang tidak berlaku lagi (misalnya asumsi bahwa tidak ada seeder lain yang berjalan sesudahnya).

## 7. Dokumen Konvensi — `.agents/skills/seeder-standard/SKILL.md` (BARU)

Mengikuti pola `.agents/skills/laravel-feature-standard/SKILL.md` yang sudah ada di repo (format skill/konvensi tim). Isi wajib:

1. **1-tabel-1-seeder untuk RBAC** — tabel `permissions`, `roles`, dan pivot `role_has_permissions` masing-masing HARUS punya tepat satu seeder pemilik (`PermissionSeeder`, `RoleSeeder`, `RolePermissionAssignmentSeeder`). Seeder modul baru yang butuh permission BARU harus menambahkannya ke `PermissionSeeder`, bukan membuat file permission sendiri.
2. **Pola key idempotency wajib** — `firstOrCreate`/`updateOrCreate` HARUS memakai kombinasi kolom yang stabil/identitas (mis. kode unik, kombinasi FK yang tidak berubah). DILARANG memakai kolom yang merupakan state-machine/status yang bisa berubah oleh alur aplikasi sebagai bagian dari key pencarian. Contoh salah (§4) dan contoh benar disertakan.
3. **Environment-guard wajib** — seeder yang membuat akun/kredensial dengan password yang diketahui publik (termasuk di source code) WAJIB guard `app()->environment(['local', 'testing'])` di awal `run()`.
4. **Urutan wajib di `DatabaseSeeder`** — parent sebelum child; `PermissionSeeder` sebelum `RoleSeeder`; `RoleSeeder` sebelum seeder domain apapun yang butuh role sudah ada; `RolePermissionAssignmentSeeder` SELALU jadi entri paling akhir di seluruh array `$this->call([...])`.
5. **Checklist wajib sebelum menambah seeder baru ke `DatabaseSeeder`** (daftar periksa singkat):
   - Idempotency key sudah pakai kolom stabil (bukan status/tanggal berjalan)?
   - Dependency ke tabel lain sudah di-assert (`firstOrFail()` atau early-return eksplisit), bukan diasumsikan ada?
   - Tidak ada password/kredensial hardcode tanpa environment-guard?
   - Posisi di `DatabaseSeeder::run()` sudah benar (parent dulu, RBAC pivot di akhir)?
   - Kalau menambah permission baru: masuk ke `PermissionSeeder`, bukan file terpisah?

## 8. Testing

- Test RBAC yang sudah ada (`PermissionConsistencyTest`, `SyncPermissionsTest`, dan seluruh test yang memakai `$this->seed(RolePermissionSeeder::class)`) HARUS tetap hijau.
- Test baru WAJIB: setelah `migrate:fresh --seed`, `Role::findByName('yayasan_super_admin')->permissions->count()` HARUS sama dengan `Permission::count()`.
- Test baru WAJIB: jalankan `php artisan db:seed` (bukan `migrate:fresh --seed`, jadi berjalan di atas data yang sudah ada) 2× berturut-turut pada DB test yang sama, assert `Kasus::count()` tidak bertambah di panggilan ke-2 (membuktikan fix §4).
- Test manual WAJIB (didokumentasikan di laporan implementasi, tidak harus jadi automated test): override `APP_ENV=production` sementara secara lokal, jalankan `migrate:fresh --seed`, pastikan TIDAK ADA exception (hanya warning skip berantai sesuai §5).
- Scoped test per task seperti biasa; full suite HANYA di task terakhir dan HARUS minta izin user dulu sebelum dijalankan.

## 9. Di Luar Cakupan

- Pemendekan email dummy, rebranding nama yayasan/lembaga, penggantian nama manusia — Spec 2 terpisah.
- Perubahan struktur tabel/migration APAPUN di luar 1 migration baru untuk cleanup permission lama (§6.4).
- Modul/domain baru di luar 59 seeder yang sudah diaudit.

## 10. Asumsi

- Baseline: isi `DatabaseSeeder.php` dan seluruh 59 file seeder sebagaimana dibaca saat spec ini ditulis (2026-08-21). Plan implementasi WAJIB verifikasi ulang isi file kalau ada commit baru di antara waktu spec ditulis dan plan dieksekusi.
- Tidak ada seeder lain (di luar yang disebutkan) yang diam-diam bergantung pada urutan `SarprasPermissionSeeder`/`PengadaanPermissionSeeder` di posisi lama — plan implementasi WAJIB grep ulang untuk memastikan.
