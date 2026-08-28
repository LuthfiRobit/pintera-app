# Fix: Semester-TahunAjaran Mismatch di Guru\RaporController & Kenaikan Kelas Mundur — Design Spec

**Tanggal**: 2026-08-28
**Branch**: `akademik-v2`
**Konteks**: 2 temuan dari audit mendalam lanjutan (area Rapor & Kenaikan Kelas), digabung dalam satu siklus fix karena area yang berdekatan.

---

## 1. Latar Belakang & Masalah

### Temuan 1 — `Guru\RaporController` tidak cross-check semester vs tahun ajaran kelas

`Admin\RaporController::cetak()` (`app/Http/Controllers/Admin/RaporController.php:107`) sudah benar: `abort_if($selectedSemester->tahun_ajaran_id !== $selectedKelas->tahun_ajaran_id, 404)`.

Tapi `Guru\RaporController` (`app/Http/Controllers/Guru/RaporController.php`) TIDAK punya guard yang sama di 4 method-nya, padahal semuanya menerima `semester_id` dari request dan menggabungkannya dengan `$siswa->kelas`/`$kelas` tanpa memverifikasi keduanya berada di tahun ajaran yang sama:

- `edit()` — baris 129-132: `$semester = Semester::find($semesterId)` lalu langsung dipakai bersama `$siswa` tanpa cek `$semester->tahun_ajaran_id === $siswa->kelas->tahun_ajaran_id`.
- `generateNarasi()` — baris 186-189: sama.
- `ajukan()` — baris 199-206: `$kelas` dan `$semester` diambil terpisah dari request, tidak pernah dicross-check satu sama lain.
- `cetak()` — baris 217-224: sama seperti `edit()`.

**Reachability**: guru wali kelas (permission `rapor.input-wali`/`rapor.ajukan`) dengan mengubah parameter `semester_id` di URL/form. `Semester` tetap ter-`TenantScope`, jadi ini BUKAN celah lintas-lembaga — guru tidak bisa memakai semester lembaga lain. Tapi guru BISA memakai semester yang benar-benar ada di lembaganya sendiri, namun milik tahun ajaran yang berbeda dari tahun ajaran kelasnya. Contoh: kelas 6A ada di TA 2025/2026, tapi guru mengirim `semester_id` milik TA 2026/2027 — sistem tetap memproses `PengajuanRapor`, `CatatanWaliKelas`, atau cetak PDF dengan kombinasi yang salah, mencemari data rapor resmi.

### Temuan 2 — Kenaikan kelas tidak validasi arah waktu tahun ajaran

`ProsesKenaikanKelasAction::execute()` (`app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php:49-51`) hanya menolak jika `$kelasBaru->tahun_ajaran_id === $kelasLama->tahun_ajaran_id` (tahun ajaran SAMA). Tidak ada pengecekan bahwa tahun ajaran tujuan benar-benar LEBIH BARU (bukan lebih lama/mundur) dari tahun ajaran asal.

**Reachability**: admin dengan permission `kenaikan-kelas.kelola` (lembaga biasa atau yayasan) — bukan celah lintas-tenant (guard `$kelasBaru->lembaga_id !== $kelasLama->lembaga_id` di baris 47 sudah benar dan tidak diubah), murni kesalahan input yang bisa merusak integritas riwayat akademik siswa (siswa "naik" ke kelas yang secara kronologis ada di masa lalu).

## 2. Keputusan Desain

### Fix Temuan 1 — mirror pola `Admin\RaporController::cetak()`

Tambahkan 1 baris `abort_if(...)` di keempat method `Guru\RaporController`, tepat setelah `$semester` diambil dan dipastikan tidak null, membandingkan terhadap tahun ajaran kelas yang relevan:

```php
// edit(), generateNarasi(), cetak() — pembandingnya $siswa->kelas->tahun_ajaran_id
abort_if($semester->tahun_ajaran_id !== $siswa->kelas->tahun_ajaran_id, 404);

// ajukan() — pembandingnya $kelas->tahun_ajaran_id (variabel lokal yang sudah ada)
abort_if($semester->tahun_ajaran_id !== $kelas->tahun_ajaran_id, 404);
```

Ditempatkan tepat setelah baris `abort_if($semester === null, 404);` yang sudah ada di masing-masing method (sebelum kode lain yang memakai `$semester`).

### Fix Temuan 2 — validasi arah waktu via `tanggal_mulai`

Di `ProsesKenaikanKelasAction::execute()`, setelah guard lembaga (baris 47) dan sebelum guard tahun-ajaran-sama (baris 49-51), tambahkan pengecekan `tanggal_mulai`:

```php
$tahunAjaranLama = TahunAjaran::findOrFail($kelasLama->tahun_ajaran_id);
$tahunAjaranBaru = TahunAjaran::findOrFail($kelasBaru->tahun_ajaran_id);

if ($tahunAjaranBaru->tanggal_mulai < $tahunAjaranLama->tanggal_mulai) {
    throw new \DomainException("Kelas tujuan \"{$kelasBaru->nama}\" berada di tahun ajaran \"{$tahunAjaranBaru->nama}\" yang lebih lama dari tahun ajaran kelas asal \"{$tahunAjaranLama->nama}\". Pilih kelas tujuan dari tahun ajaran berikutnya.");
}
```

**PENTING — pakai `<` (strict), BUKAN `<=`**: `tahun_ajaran.tanggal_mulai` adalah kolom `date` (`database/migrations/2026_07_12_100820_create_tahun_ajaran_table.php:18`), dan `TahunAjaranFactory` (`database/factories/TahunAjaranFactory.php:20`) selalu default ke `now()` untuk SEMUA baris tanpa variasi — artinya 2 test existing di `ProsesKenaikanKelasActionTest.php` (`promotes siswa to the destination kelas...`, `skips a jadwal row that clashes...`) membuat `$tahunLama` dan `$tahunBaru` dengan `tanggal_mulai` yang IDENTIK (tanggal hari ini, kolom `date` membulatkan waktu). Kalau memakai `<=`, kedua test existing itu akan pecah (dianggap "tidak lebih baru" padahal cuma kebetulan sama hari). `<` (ketat) hanya menolak kasus yang BENAR-BENAR mundur (tanggal lebih awal), dan membiarkan kasus "tanggal sama" tetap lolos — cukup untuk menutup celah "mundur" yang jadi concern utama tanpa memaksa perubahan fixture pada test-test lain yang tidak terkait.

Pengecekan `tahun_ajaran_id` SAMA (baris 49-51 existing) TETAP DIPERTAHANKAN apa adanya (bukan dihapus/digabung) — kasusnya berbeda dari pengecekan `tanggal_mulai` baru: "ID sama persis" vs "ID beda tapi tanggal mundur", masing-masing dengan pesan error sendiri. Urutan pengecekan: guard lembaga (baris 47, existing) → guard ID sama (baris 49-51, existing, TIDAK diubah) → guard tanggal mundur (BARU, ditambahkan setelahnya).

Import `App\Models\TahunAjaran` perlu ditambahkan ke `ProsesKenaikanKelasAction.php`.

## 3. Non-Goals (eksplisit di luar scope)

- Tidak mengubah `Admin\RaporController` (sudah benar).
- Tidak mengubah guard cross-lembaga yang sudah ada di `ProsesKenaikanKelasAction` (baris 47, 57) — tetap dipertahankan.
- Tidak menambah lock/idempotency-guard untuk race-condition concurrent request pada kenaikan kelas — audit menyimpulkan operasi ini self-correcting secara alami (siswa yang sudah pindah kelas tidak ter-update lagi di eksekusi kedua) dan tidak masuk scope bug-fix ini.
- Tidak mengubah `RaporPdfDataBuilder`, `GenerateNarasiPerkembanganAction`, `SubmitPengajuanRaporAction`, `SimpanCatatanWaliKelasAction` — semuanya menerima `$semester`/`$kelas` yang sudah tervalidasi dari controller, tidak perlu duplikasi validasi di layer itu.
- Tidak menambahkan window/cut-off waktu edit presensi (Temuan 3 dari audit — severity Low, integrity bukan security, TIDAK termasuk dalam scope "1 dan 2" yang disetujui user).

## 4. Testing (acceptance criteria wajib)

**4.1 — Regresi wajib**: seluruh test existing terkait `Guru\RaporController` (kemungkinan `tests/Feature/Guru/RaporControllerTest.php` atau nama serupa — dikonfirmasi ulang saat plan) dan `ProsesKenaikanKelasAction`/`KenaikanKelasController` (kemungkinan `tests/Feature/Admin/KenaikanKelasControllerTest.php` atau `tests/Unit/.../ProsesKenaikanKelasActionTest.php`) HARUS tetap PASS tanpa modifikasi assertion apapun.

**4.2 — Bug reproduction Temuan 1**: guru wali kelas mengirim `semester_id` yang valid (milik lembaganya sendiri) tapi `tahun_ajaran_id`-nya BEDA dari `tahun_ajaran_id` kelas siswa/kelas yang bersangkutan → keempat method (`edit`, `generateNarasi`, `ajukan`, `cetak`) HARUS mengembalikan 404. Minimal 2 skenario test (satu untuk method yang pakai `$siswa`, satu untuk `ajukan()` yang pakai `$kelas` langsung) sudah representatif — tidak perlu 4 test terpisah identik kalau pola guard-nya sama persis, tapi PASTIKAN keempat method benar-benar tersentuh oleh minimal 1 test masing-masing.

**4.3 — Kasus tidak berubah (regresi negatif Temuan 1)**: `semester_id` yang valid DAN satu tahun ajaran dengan kelas → tetap sukses seperti sebelumnya (tidak ada regresi jalur normal).

**4.4 — Bug reproduction Temuan 2**: admin memilih kelas tujuan kenaikan kelas yang `tahun_ajaran_id`-nya BEDA dari kelas asal TAPI `tanggal_mulai`-nya LEBIH AWAL (mundur, strictly lebih kecil) → `ProsesKenaikanKelasAction::execute()` HARUS throw `\DomainException` dengan pesan yang menyebut "lebih lama". Siswa TIDAK boleh berpindah kelas (rollback via `DB::transaction` yang sudah ada). Test HARUS eksplisit membuat `$tahunAjaranLama` dan `$tahunAjaranBaru` dengan `tanggal_mulai` yang secara eksplisit berbeda (bukan mengandalkan default factory `now()` yang bisa sama), agar skenario "mundur" benar-benar teruji.

**4.5 — Kasus tidak berubah (regresi negatif Temuan 2)**: kenaikan kelas normal (tahun ajaran tujuan benar-benar lebih baru, `tanggal_mulai` lebih besar) → tetap sukses seperti sebelumnya. Kasus tahun ajaran SAMA PERSIS (ID sama) → tetap dapat pesan error existing ("masih berada di tahun ajaran yang sama"), BUKAN pesan baru. Kasus 2 tahun ajaran BEDA ID tapi `tanggal_mulai` SAMA (skenario 2 test existing di `ProsesKenaikanKelasActionTest.php` yang memakai default factory) → HARUS tetap sukses (tidak throw), karena validasi baru pakai `<` (strict), bukan `<=`.

## 5. Ringkasan Perubahan File

```text
app/Http/Controllers/Guru/RaporController.php                                        [+4 abort_if cross-check semester vs tahun ajaran kelas]
app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php              [+validasi tanggal_mulai tahun ajaran tujuan > asal, +import TahunAjaran]
tests/Feature/Guru/RaporControllerTest.php                                            [+test reproduksi & regresi Temuan 1]
tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php   [+test reproduksi & regresi Temuan 2]
```

File test dikonfirmasi ada di repo: `tests/Feature/Guru/RaporControllerTest.php` dan `tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php` (juga ada `tests/Feature/Admin/KenaikanKelasControllerTest.php` dan `tests/Feature/Akademik/KenaikanKelasControllerUxTest.php` yang harus tetap PASS sebagai regresi, meski test baru Temuan 2 ditempatkan di file Unit Action-nya langsung, lebih dekat dengan logic yang diubah).
