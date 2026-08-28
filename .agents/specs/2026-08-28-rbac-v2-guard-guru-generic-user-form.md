# RBAC v2 — Cegah Role `guru` Dibuat Lewat Form Pengguna Generik — Design Spec

**Tanggal**: 2026-08-28
**Branch**: `rbac-v2`
**Konteks**: Ditemukan saat menganalisis kebingungan 3-jalur pembuatan akun (`Admin → Pengguna`/`Guru`/`Karyawan`) setelah fix `kasus.view` selesai. `UserController::store()`/`update()` (form Pengguna generik) bisa meng-assign role `guru` ke `User` mana pun, tapi tidak pernah membuat record `Guru` yang menaut `user_id` — menghasilkan state invalid `User(role=guru)` tanpa `Guru` (disebut "profil belum tertaut").

---

## 1. Latar Belakang & Audit

### 1.1 — Masalah

`UserController::store()` (`app/Http/Controllers/Admin/UserController.php:189-232`) memvalidasi `roles.*` hanya menolak `siswa`, `orang_tua`, `pegawai_lembaga`, `pegawai_yayasan` (baris 199) — role `guru` LOLOS, dan `formRoleGroups()` (baris 313-321) memang menampilkan `guru` di grup checkbox "Staf". Kalau admin bikin akun lewat jalur ini dengan centang `guru`, `User` dapat role `guru` tapi **tidak pernah** ada `Guru::create(['user_id' => $user->id, ...])` — beda dengan `GuruController::store()` yang sudah benar (lihat §1.3).

### 1.2 — Audit matriks: role mana yang benar-benar butuh profil domain?

Diverifikasi langsung lewat grep kode (bukan tebak dari nama role) untuk SETIAP dari 18 role di `RoleSeeder.php`:

| Role | Butuh `Guru`? | Butuh `Karyawan`? | Bukti |
|---|---|---|---|
| `guru` | **Ya** | Tidak | `RppController::store()` L142 (`Guru::where('user_id',...)`), `Admin\DashboardController` L42-99 (semua fitur guru fallback null-check ke `$user->guru`), `EmployeeQrCodeController::resolvePegawai()` — semua gagal senyap (abort/empty) kalau `Guru` tidak ada |
| `pegawai_lembaga`/`pegawai_yayasan` | Tidak | **Ya** | Pola sama di `resolvePegawai()` — fallback ke `Karyawan::where('user_id',...)`. Sudah di-exclude dari `assignableRoles()`, jalur benar sudah `AkunKaryawanGenerator` |
| `guru_bk` | **Tidak** | **Tidak** | `grep -rn "hasRole('guru_bk')" app/` → 0 hasil. Permission-nya (`kasus.view`, `kasus.triase`) dan `KasusPolicy::isTriaseAdmin` tidak pernah cek `$user->guru`/`$user->karyawan`. Menjadi *konselor* pada kasus tertentu adalah fakta domain terpisah (`konselor_guru_id`/`konselor_karyawan_id`, diisi `AssignKonselorAction`, bisa nempel ke `Guru` ATAU `Karyawan`) — bukan hasil dari role ini |
| `wali_kelas` | **Tidak** (role itu sendiri) | Tidak | `grep -rn "hasRole('wali_kelas')" app/` → 0 hasil. Role ini tidak bawa permission tambahan sama sekali (dikonfirmasi komentar `RoleSeeder.php`). Kapasitas nyata "menjadi wali kelas" berasal dari `Kelas.wali_kelas_guru_id` (FK ke tabel `guru`) — fakta domain terpisah, ditetapkan lewat mekanisme lain, bukan lewat role assignment ini |
| `kepala_sekolah`, `wakasek_kurikulum`, `wakasek_kesiswaan`, `operator_akademik`, `admin_sdm`, `bendahara_lembaga`, `admin_sarpras`, `admin_administrasi` | **Tidak** | **Tidak** | `grep -rn "hasRole('<role>')" app/` untuk kedelapan role → 0 hasil semua. Murni permission-carrier Spatie |

**Kesimpulan**: TIDAK ada kebutuhan registry/mapping "role → profil". Cukup satu invariant:

> **`role = guru` ⇒ `Guru` profile (dengan `user_id` yang sesuai) wajib ada.**
> Satu-satunya jalur yang bisa melanggar invariant ini adalah `Admin → Pengguna` (`UserController`).

### 1.3 — Audit lifecycle: 2 jalur profile-creating sudah benar, tidak ada orphan existing

- **`GuruController::store()`** (`app/Http/Controllers/Admin/GuruController.php:98-115`) — sudah `DB::transaction`: `User::create()` → `$user->assignRole('guru')` → `Guru::create([..., 'user_id' => $user->id])`. Lengkap dan atomik, **tidak diubah** spec ini.
- **`AkunKaryawanGenerator::buat()`** (`app/Services/AkunKaryawanGenerator.php:21-46`) — sudah `DB::transaction`: `User::create()` → `assignRole('pegawai_lembaga'/'pegawai_yayasan')` → `Karyawan::create([..., 'user_id' => $user->id])`. Lengkap dan atomik, **tidak diubah** spec ini.
- **Orphan check** (query langsung ke database, bukan asumsi):
  ```sql
  SELECT u.id FROM users u
  INNER JOIN model_has_roles mhr ON mhr.model_id = u.id AND mhr.model_type = 'App\Models\User'
  INNER JOIN roles r ON r.id = mhr.role_id
  WHERE r.name = 'guru' AND NOT EXISTS (SELECT 1 FROM guru g WHERE g.user_id = u.id)
  ```
  → **0 hasil**. Tidak ada `User(role=guru)` tanpa `Guru` di data saat ini. Tidak perlu migrasi/remediasi data.
- 21 user dengan role `pegawai_lembaga` tanpa `Karyawan` sempat ditemukan, tapi setelah dicek semuanya adalah guru asli yang juga punya `Guru` profile (`has_guru=1`) — pola scope-carrier ganda yang memang didokumentasikan (`RoleSeeder.php` komentar: "guru selalu juga punya pegawai_lembaga"), **bukan** orphan.

## 2. Keputusan Desain

### 2.1 — Prinsip

Desain berdasarkan **operasi pembuatan entity yang butuh profil**, bukan berdasarkan "role yang kedengarannya berhubungan dengan guru". Hanya `guru` yang punya dependency itu di level role (§1.2). `guru_bk` dan `wali_kelas` TIDAK diperlakukan sama — keduanya tetap boleh dipilih bebas di form Pengguna generik, karena audit membuktikan keduanya murni capability-carrier tanpa prasyarat profil.

**Radius perubahan**: HANYA `UserController.php`. Tidak ada perubahan di `GuruController`, `AkunKaryawanGenerator`, `RoleSeeder`, model `Guru`/`Karyawan`, schema, atau data existing.

### 2.2 — Perubahan konkret

**(a) `UserController::assignableRoles()`** (baris 297-305) — keluarkan `guru` dari daftar role yang muncul di form (create/edit):
```php
private function assignableRoles(int $actingRank): \Illuminate\Support\Collection
{
    $excluded = ['siswa', 'orang_tua', 'pegawai_lembaga', 'pegawai_yayasan', 'guru'];

    return Role::all()
        ->reject(fn ($role) => in_array($role->name, $excluded, true))
        ->filter(fn ($role) => $this->scopeRank($role->scope_level) <= $actingRank)
        ->values();
}
```
Dampak: `create()` dan `edit()` (yang memanggil `groupRolesForForm($this->assignableRoles($actingRank))`) otomatis tidak lagi menampilkan checkbox `guru` — grup "Staf" di form akan tersisa `wali_kelas`/`guru_bk` saja (`wali_kelas`/`guru_bk` TIDAK dikeluarkan, sesuai §1.2).

**(b) `UserController::store()`** (baris 189-232) — tambah guard eksplisit SETELAH validasi dasar, SEBELUM guard scope-rank yang sudah ada (baris 208-213), dengan pesan yang menjelaskan jalur benar:
```php
public function store(Request $request): RedirectResponse
{
    $this->authorize('users.create');

    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'unique:users,email'],
        'password' => ['required', 'confirmed', 'min:8'],
        'lembaga_id' => ['nullable', 'exists:lembaga,id'],
        'roles' => ['required', 'array', 'min:1'],
        'roles.*' => ['exists:roles,name', Rule::notIn(['siswa', 'orang_tua', 'pegawai_lembaga', 'pegawai_yayasan'])],
    ]);

    if (in_array('guru', $data['roles'], true)) {
        return back()->withErrors(['roles' => 'Role Guru harus dibuat melalui Admin → Guru agar profil Guru dibuat dan tertaut dengan benar.'])->withInput();
    }

    $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
        ? ($data['lembaga_id'] ?? null)
        : $request->user()->lembaga_id;
    // ... sisa method TIDAK BERUBAH
```
**Kenapa bukan ditambahkan ke `Rule::notIn([...])` yang sudah ada**: 4 role yang sudah ada di situ (`siswa`/`orang_tua`/`pegawai_lembaga`/`pegawai_yayasan`) ditolak karena alasan yang SAMA (scope-carrier, tidak pernah dipilih manual) — pesan error validasi generik Laravel cukup untuknya. `guru` ditolak karena alasan BERBEDA (butuh profil) dan butuh pesan yang menjelaskan jalur alternatif — memisahkannya jadi guard sendiri menghindari pesan generik yang membingungkan ("kenapa guru masuk daftar yang sama dengan siswa/orang_tua?").

**(c) `UserController::update()`** (baris 247-277) — guard IDENTIK ditambahkan, SETELAH validasi, SEBELUM guard scope-rank yang sudah ada (baris 260-265):
```php
public function update(Request $request, User $user): RedirectResponse
{
    $this->authorize('users.edit');
    abort_if($user->hasRole(['siswa', 'orang_tua']), 404);

    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'unique:users,email,'.$user->id],
        'roles' => ['required', 'array', 'min:1'],
        'roles.*' => ['exists:roles,name', Rule::notIn(['siswa', 'orang_tua', 'pegawai_lembaga', 'pegawai_yayasan'])],
    ]);

    if (in_array('guru', $data['roles'], true)) {
        return back()->withErrors(['roles' => 'Role Guru harus dibuat melalui Admin → Guru agar profil Guru dibuat dan tertaut dengan benar.'])->withInput();
    }

    $selectedRoles = Role::whereIn('name', $data['roles'])->get();
    // ... sisa method TIDAK BERUBAH
```
Ini menutup jalur "transisi role" yang disebut eksplisit di review: user capability-only yang sudah ada, lalu admin mencoba menambahkan `guru` lewat edit — sekarang ditolak dengan pesan yang sama.

**(d) `formRoleGroups()`** (baris 313-321) — **TIDAK PERLU DIUBAH**. Grup "Staf" masih mendaftarkan `['guru', 'wali_kelas', 'guru_bk']` di definisi taksonomi, tapi karena `groupRolesForForm()` (baris 329-334) hanya menampilkan role yang ADA di collection `$roles` (hasil `assignableRoles()`, yang sekarang sudah tidak mengandung `guru`), maka `guru` otomatis hilang dari tampilan tanpa perlu menyentuh taksonomi grupnya. Mengedit `formRoleGroups()` untuk menghapus `guru` dari situ TIDAK SALAH tapi redundan — dibiarkan apa adanya supaya taksonomi tetap dokumentasi lengkap "role apa saja yang secara konsep termasuk grup Staf", terpisah dari "role apa yang boleh dipilih sekarang".

## 3. Non-Goals (eksplisit di luar scope)

- **Tidak** membuat registry/mapping role → profil generik. Satu-satunya kasus yang butuh guard adalah `guru`, ditangani inline.
- **Tidak** mengubah `GuruController` atau `AkunKaryawanGenerator` — keduanya sudah transactional dan lengkap (§1.3).
- **Tidak** ada migrasi/remediasi data — tidak ada orphan `User(role=guru)` di data existing (§1.3).
- **Tidak** memblokir/membatasi `guru_bk` atau `wali_kelas` di form Pengguna generik — audit membuktikan keduanya tidak butuh profil apa pun (§1.2). Ini bukan detail insidental, tapi **acceptance criterion eksplisit** (lihat §4.3-4.4).
- **Tidak** mengubah 8 role administratif lain (`kepala_sekolah`, `wakasek_*`, `operator_akademik`, `admin_sdm`, `bendahara_lembaga`, `admin_sarpras`, `admin_administrasi`) — sudah terbukti pure capability-carrier.
- **Tidak** menyentuh `visibleUsersQuery()`, `applyScopeGroup()`, `scopeGroups()` — stabil, hasil kerja sesi RBAC-pengguna-scope-filter sebelumnya.
- **Tidak** mengubah `Rule::notIn([...])` yang sudah ada untuk `siswa`/`orang_tua`/`pegawai_lembaga`/`pegawai_yayasan` — tetap seperti semula, guard baru untuk `guru` ditambahkan sebagai pengecekan terpisah, bukan menambah item ke array itu.

## 4. Testing (acceptance criteria wajib)

**4.1** — `UserController::create()` (dan `edit()`) TIDAK menampilkan `guru` sebagai opsi checkbox di `rolesByGroup` yang dikirim ke view, untuk actor dengan rank apa pun.

**4.2** — `POST /admin/users` (`store()`) dengan `roles` mengandung `guru` → redirect back dengan error di key `roles` berisi pesan yang menyebutkan "Admin → Guru" (atau teks persis yang ditulis di §2.2(b)), `User` TIDAK dibuat sama sekali (assert `assertDatabaseMissing` pada email yang dikirim).

**4.3** — `POST /admin/users` dengan `roles` mengandung `guru_bk` (SENDIRIAN, tanpa role lain) → **HARUS BERHASIL**, `User` dibuat, role `guru_bk` ter-assign, TANPA membuat `Guru`/`Karyawan` apa pun. Ini regression guard eksplisit membuktikan §2.2 tidak overreach ke role yang tidak seharusnya diblokir.

**4.4** — `POST /admin/users` dengan `roles` mengandung `wali_kelas` (SENDIRIAN) → **HARUS BERHASIL**, sama seperti 4.3.

**4.5** — `PATCH /admin/users/{user}` (`update()`) pada user existing yang sebelumnya cuma punya role `guru_bk`, mencoba mengubah `roles` jadi mengandung `guru` → ditolak dengan pesan yang sama seperti 4.2, role user TIDAK berubah (masih `guru_bk` saja, verifikasi lewat `fresh()->getRoleNames()`).

**4.6** — Regresi: `POST /admin/users` dengan kombinasi role administratif lain (mis. `kepala_sekolah` + `admin_sdm`) tetap berhasil seperti sebelumnya — tidak terpengaruh guard baru sama sekali.

**4.7** — Regresi: `GuruController::store()` (jalur `Admin → Guru`) tetap berhasil membuat `User` + `Guru` + role `guru` seperti sebelumnya — guard baru di `UserController` sama sekali tidak menyentuh controller ini.

## 5. Ringkasan Perubahan File

```text
app/Http/Controllers/Admin/UserController.php   [assignableRoles(): +'guru' ke $excluded; store(): +guard inline 'guru'; update(): +guard inline 'guru' identik]
tests/Feature/Admin/UserManagementTest.php      [+7 test sesuai §4.1-4.7 -- file test existing untuk UserController, konvensi penamaan/setup mengikuti test yang sudah ada di file ini]
```
