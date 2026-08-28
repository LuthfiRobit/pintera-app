# RBAC v2 — Pemisahan Capability (Permission) vs Resource Authorization (Policy) vs Assignment (Relationship) — Design Spec

**Tanggal**: 2026-08-28
**Branch**: `rbac-v2`
**Konteks**: Ditemukan saat menguji coba pembuatan akun Karyawan (jenis "satpam") — akun itu otomatis punya akses ke menu Kasus Pendampingan meski tidak pernah ditugaskan sebagai konselor. Draft awal spec ini ("hapus `kasus.view` dari baseline karyawan lalu selesai") DIBATALKAN setelah ditemukan bahwa itu akan merusak alur "Konselor Pool Karyawan" yang sudah ada (`KonselorAllocationResolver`). Spec ini adalah versi final setelah trace menyeluruh membuktikan akar masalah sebenarnya: gate permission yang redundan dengan Policy di 8 endpoint, dan gate collection-level yang tidak membedakan capability vs assignment.

---

## 1. Latar Belakang & Masalah

### 1.1 — Temuan awal

`AkunKaryawanGenerator::buat()` (`app/Services/AkunKaryawanGenerator.php:33`) meng-assign role `pegawai_lembaga`/`pegawai_yayasan` ke SETIAP Karyawan baru tanpa percabangan `jenis_karyawan_id`. Role itu (`RoleSeeder.php:170-172`) memberi `kasus.view` ke semua karyawan — termasuk yang tidak pernah dan mungkin tidak akan pernah ditugaskan menangani kasus siswa (satpam, cleaning service, sopir, dst).

### 1.2 — Audit menyeluruh baseline roles (hasil, sudah diverifikasi)

Diperiksa **seluruh** permission di **seluruh 5 role baseline** (role otomatis, bukan assignment manual) yang ada di sistem: `guru`, `pegawai_lembaga`, `pegawai_yayasan`, `siswa`, `orang_tua`. 9 role lain (`kepala_sekolah`, `wakasek_kurikulum`, `wakasek_kesiswaan`, `operator_akademik`, `admin_sdm`, `bendahara_lembaga`, `admin_sarpras`, `admin_administrasi`, `guru_bk`) sudah 100% assignment-only — tidak ada jalur kode yang meng-assign-nya otomatis, tidak disentuh spec ini.

- **`guru`**: `presensi.isi`, `asesmen.kelola`, `rpp.view/kelola`, `rapor.input-wali/ajukan`, `komponen-penilaian.kelola-sendiri` — self-scoped, sudah diverifikasi tuntas di audit Akademik sesi sebelumnya. `kasus.ajukan`+`kasus.view` — aman, berpasangan (guru butuh lihat status kasus yang dia laporkan sendiri, `ListKasusUntukUserAction` scope ke `diajukan_oleh_guru_id`/`konselor_guru_id` = diri sendiri). `kehadiran-sdm.*` — aman, `resolvePegawai()` selalu dari `auth()->user()->id`.
- **`siswa`**: `kasus.view` — scoped `siswa_id` = diri sendiri.
- **`orang_tua`**: `kasus.ajukan/view/consent`, `keuangan.akses` — `keuangan.akses` diverifikasi lewat `AuthorizesPembayaran` trait (`app/Domains/Keuangan/Concerns/AuthorizesPembayaran.php:14-18`), `siswa_id` harus anak kandung, dipakai konsisten di `CheckoutController` (4 method) + `RiwayatController::kwitansi()`.
- **`pegawai_lembaga`/`pegawai_yayasan`**: `kehadiran-sdm.*` aman. **`kasus.view` — SATU-SATUNYA temuan nyata**: karyawan tidak punya `kasus.ajukan`, tidak ada alasan self-service, cuma berguna kalau ditugaskan konselor.

**Kesimpulan audit**: bukan gejala arsitektur RBAC dinamis yang rusak. Arsitekturnya (multi-role per user, assignment bebas tanpa hardcode dari jenis_ptk/jenis_karyawan) sudah benar dan **tidak diubah** spec ini.

### 1.3 — Kenapa "hapus kasus.view saja" tidak cukup: alur Konselor Pool Karyawan

`Admin\KasusController::assignKonselor()` → `AssignKonselorAction::execute()` (`app/Domains/Kasus/Actions/Manajemen/AssignKonselorAction.php:19-25`) **hanya** menyimpan `konselor_karyawan_id`/`konselor_guru_id` di record `Kasus` — tidak pernah menyentuh role/permission. Alur ini mengandalkan penuh bahwa Karyawan yang ditugaskan **sudah otomatis** punya `kasus.view` dari baseline. Infrastruktur pendukungnya sudah ada dan nyata dipakai:
- `JenisKaryawanMaster.is_konselor` (flag data master)
- `KonselorAllocationResolver::kandidatUntuk()` — mencari kandidat dari **pool karyawan level-yayasan** (`Karyawan::whereNull('lembaga_id')->whereHas('jenisKaryawan', fn($q) => $q->where('is_konselor', true))`)

Kalau `kasus.view` dihapus dari baseline tanpa perubahan lain, Karyawan pool yang ditugaskan konselor akan **403 begitu mencoba buka kasus yang justru jadi tanggung jawabnya** — regresi nyata. Test `KasusKonselorAksesTest.php` tidak menangkap ini karena test itu meng-assign `kasus.view` secara manual di dalam test-nya sendiri (`Role::firstOrCreate(...)->givePermissionTo(['kasus.view'])`), bukan lewat `RoleSeeder::run()` — jadi tidak merepresentasikan kondisi produksi nyata.

### 1.4 — Trace menyeluruh: `kasus.view` ternyata gate yang 100% redundan di 8 dari 10 titik pemakaiannya

| Endpoint | Ada `$kasus`? | Authorization SETELAH gate `kasus.view` | Referensikan `kasus.view`? |
|---|---|---|---|
| `KasusController::index()` | ❌ (collection) | `ListKasusUntukUserAction` | — |
| `KasusController::show()` | ✅ | `KasusPolicy::view()` (isSubmitter/isKontakUtama/isTriaseAdmin `kasus.triase`/isKonselor/isSiswaTerkait) | Tidak |
| `KasusSesiController::store()`/`updateStatus()` | ✅ | `kelolaSesiTugas` (= `isKonselor`) | Tidak |
| `KasusTugasController::store()`/`markSelesai()` | ✅ | `kelolaSesiTugas` | Tidak |
| `KasusTugasBatchPreviewController::preview()` | ✅ | `kelolaSesiTugas` | Tidak |
| `KasusTugasSubmissionController::store()` | ✅ | inline `isSiswaTerkait \|\| isKontakUtama` | Tidak |
| `KasusTugasSubmissionController::review()` | ✅ | `kelolaSesiTugas` | Tidak |
| `KasusTugasSubmissionController::download()` | ✅ | `KasusPolicy::downloadLampiran()` | Tidak |
| `KasusEvaluasiController::store()` | ✅ | `isKonselor` ATAU `kasus.triase` (permission lain) | Tidak |
| `Admin\KasusController::index()` | ❌ (triase inbox, area ADMIN terpisah) | — | — |

**8 dari 10 titik**: `kasus.view` adalah pre-gate yang tidak pernah dirujuk oleh authorization sesungguhnya yang berjalan setelahnya (Policy/inline check). Menghapusnya dari 8 endpoint ini **tidak mengubah hasil otorisasi sama sekali** — Policy sudah memutuskan semuanya sendiri berdasarkan relasi data, bukan permission global.

**2 titik sisanya** (`KasusController::index()` dan `Admin\KasusController::index()`) genuinely butuh gate karena tidak ada `$kasus` spesifik untuk dilempar ke Policy.

### 1.5 — Definisi "assigned konselor" yang dipakai domain saat ini (diverifikasi, bukan diasumsikan)

- `StatusKasus` enum (6 status: Diajukan/MenungguConsent/Ditugaskan/Berjalan/Eskalasi/Selesai) — **tidak ada** status yang berarti "konselor dicabut".
- Grep menyeluruh `app/` untuk pola set `konselor_karyawan_id` jadi `null` — **nol hasil**. Tidak ada mekanisme unassign di domain ini sama sekali, sekarang.
- `ListKasusUntukUserAction` (perilaku existing, dipakai SEKARANG) — `where('konselor_karyawan_id', $karyawanId)` **tanpa filter status apa pun**. Karyawan yang pernah ditugaskan tetap melihat kasusnya di list selamanya, termasuk yang sudah `Selesai`.

**Kontrak yang dikunci untuk spec ini**: *"Seorang karyawan dianggap memiliki akses sebagai konselor apabila terdapat minimal satu record `Kasus` dengan `konselor_karyawan_id` = karyawan tersebut."* Tidak ada filter status, tidak ada konsep "aktif", tidak ada asumsi expiry. Assignment konselor **bersifat historis/persisten** mengikuti model domain yang sudah ada — bukan istilah "aktif" yang dikarang di layer RBAC. Membangun mekanisme unassign adalah domain feature baru, eksplisit **di luar scope** spec ini.

## 2. Keputusan Desain

### 2.1 — Prinsip yang dikunci (kontrak RBAC v2)

```text
Permission (capability)  = kapabilitas yang melekat pada identitas/status user
Policy (resource auth)   = otorisasi terhadap satu resource spesifik, berbasis relasi data
Assignment (relationship)= hubungan user-ke-resource yang SUDAH ADA di data (FK), dipakai
                            LANGSUNG sebagai dasar otorisasi collection-level TANPA
                            direpresentasikan ulang sebagai role/permission global.
```

> Jangan memberikan global role hanya untuk merepresentasikan hubungan user dengan satu resource. Jika authorization dapat diturunkan langsung dari relationship resource tersebut, gunakan relationship + Policy — bukan role otomatis.

**Dilarang eksplisit**: aturan berbasis pola nama permission (mis. "semua yang berakhiran `.view` harus assignment-only") — audit ini sendiri membuktikan itu keliru (`kasus.view` valid untuk guru, tidak valid untuk karyawan, permission yang SAMA). **Dilarang eksplisit**: hardcode role-check (`hasRole('pegawai_lembaga')`) di `KasusPolicy` — source of truth harus `konselor_karyawan_id`, bukan jenis role/jenis_karyawan.

### 2.2 — Perubahan konkret

**(a) `RoleSeeder.php:170-172`** — keluarkan `kasus.view` dari baseline karyawan:
```php
if (in_array($name, ['pegawai_lembaga', 'pegawai_yayasan'], true)) {
    $role->givePermissionTo(['kehadiran-sdm.lihat-qr-sendiri', 'kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri']);
}
```
Baseline `guru`, `siswa`, `orang_tua` **tidak diubah**.

**(b) Hapus gate `$this->authorize('kasus.view')` yang redundan** dari 8 endpoint di §1.4 (baris pertama di tiap method yang terdaftar redundan). Baris `$this->authorize('kelolaSesiTugas', $kasus)` / `KasusPolicy::view()` / `downloadLampiran()` / inline check yang SUDAH ADA di method yang sama **tidak diubah sama sekali** — itu tetap satu-satunya sumber otorisasi, terbukti sudah benar di audit §1.4.

**(c) Tambah method baru `KasusPolicy::viewAny(User $user): bool`**:
```php
public function viewAny(User $user): bool
{
    if ($user->can('kasus.view')) {
        return true;
    }

    $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;

    return $karyawanId !== null && Kasus::withoutGlobalScope(TenantScope::class)
        ->where('konselor_karyawan_id', $karyawanId)
        ->exists();
}
```
`$user->can('kasus.view')` tetap `true` untuk `guru`/`siswa`/`orang_tua` (baseline mereka tidak berubah). Untuk `pegawai_lembaga`/`pegawai_yayasan` (yang setelah §2.2(a) tidak lagi punya `kasus.view`), fallback ke pengecekan `exists()` murni berbasis assignment data — TANPA filter status, sesuai kontrak §1.5.

**(d) `KasusController::index()`** — ganti gate:
```php
// Sebelum: $this->authorize('kasus.view');
$this->authorize('viewAny', Kasus::class);
```
`ListKasusUntukUserAction` (query scoping di dalam list) **tidak diubah** — sudah benar dan sudah jadi acuan definisi "assigned" di §1.5.

**(e) `Admin\KasusController::index()`** — **TIDAK DIUBAH SAMA SEKALI**. Tetap `$this->authorize('kasus.view')` dalam bentuk string permission biasa (bukan Policy `viewAny`). Ini area triase administratif terpisah, tidak boleh ikut terseret mekanisme "assignment konselor" milik alur user biasa. `kasus.view` di sini tetap dipegang oleh role assignment-only (`operator_akademik`, dst — cek `RoleSeeder.php` sebelum implementasi untuk daftar pasti siapa saja yang pegang `kasus.view` selain 3 baseline role yang tersisa).

## 3. Non-Goals (eksplisit di luar scope)

- **Tidak** membuat role baru "Konselor"/"konselor_karyawan" apa pun — otorisasi konselor karyawan sepenuhnya derivasi dari `konselor_karyawan_id`, tidak butuh role tambahan.
- **Tidak** ada `assignRole()`/`removeRole()` di `AssignKonselorAction` atau action manapun — tidak ada state role yang perlu disinkronkan.
- **Tidak** membangun mekanisme "unassign konselor" — domain belum punya, dan spec ini tidak menciptakannya. Konsekuensi (assignment bersifat permanen/historis) diterima sebagai perilaku existing yang disengaja, didokumentasikan apa adanya.
- **Tidak** membuat mapping otomatis `jenis_karyawan_id`/`jenis_ptk` → role/permission apa pun.
- **Tidak** mengubah `Admin\KasusController` (index, triase, assignKonselor, destroy, restore) — semuanya tetap permission-gate biasa seperti sekarang, tidak disentuh sama sekali.
- **Tidak** mengubah 3 jalur pembuatan akun (`Admin → Pengguna`/`Guru`/`Karyawan`) atau UX form-nya — direkomendasikan terpisah sebagai P2 (lihat §6), tidak dikerjakan di siklus ini.
- **Tidak** menyentuh `keuangan.akses` atau permission lain yang sudah diverifikasi aman di §1.2 — sudah dibuktikan tidak perlu diubah.
- **Tidak** ada perubahan skema/migration.

## 4. Testing (acceptance criteria wajib)

**4.1 — Regresi wajib**: seluruh test existing di `tests/Unit/RoleSeederTest.php`, `tests/Feature/KasusKonselorAksesTest.php`, `tests/Feature/KasusTugasReviewTest.php`, `tests/Feature/KasusEvaluasiTest.php`, `tests/Feature/DashboardKasusTest.php` HARUS tetap PASS tanpa modifikasi assertion. Perhatian khusus: test-test di `KasusKonselorAksesTest.php` yang meng-assign `kasus.view` manual di dalam test-nya sendiri (bukan lewat `RoleSeeder`) — perilaku itu TIDAK PERLU diubah (tetap valid, cuma tidak lagi merepresentasikan kondisi produksi nyata untuk kasus TANPA assignment — lihat 4.3).

**4.2 — Bug reproduction (regresi yang HAMPIR terjadi kalau P0 naif dijalankan)**: karyawan pool (`lembaga_id = null`, `jenis_karyawan.is_konselor = true`) di-assign lewat `AssignKonselorAction` sungguhan (bukan manual role grant) sebagai `konselor_karyawan_id` pada sebuah `Kasus` → HARUS bisa buka `kasus.index`, `kasus.show`, dan endpoint sesi/tugas/evaluasi kasus itu — TANPA pernah diberi role/permission tambahan apa pun, HANYA lewat `RoleSeeder` baseline (yang sudah tidak punya `kasus.view`) + `konselor_karyawan_id` ter-set.

**4.3 — Regresi negatif — karyawan biasa tanpa assignment**: karyawan (`pegawai_lembaga`/`pegawai_yayasan`, role dari `RoleSeeder` sungguhan) yang TIDAK PERNAH jadi `konselor_karyawan_id` di `Kasus` manapun → `kasus.index` HARUS 403 (via `viewAny` gate).

**4.4 — Regresi negatif — assignment historis tetap terlihat**: karyawan yang jadi konselor di sebuah `Kasus` berstatus `Selesai` (dan TIDAK punya kasus lain yang belum selesai) → `kasus.index` HARUS tetap bisa dibuka (bukti bahwa tidak ada filter status yang diam-diam ditambahkan).

**4.5 — Regresi negatif — baseline lain tidak berubah**: `guru`/`siswa`/`orang_tua` (role dari `RoleSeeder` sungguhan, TANPA pernah jadi konselor apa pun) tetap bisa buka `kasus.index` seperti sebelumnya (lewat `$user->can('kasus.view')` di `viewAny()`).

**4.6 — Regresi negatif — Admin tidak terpengaruh**: `Admin\KasusController::index()` tetap berperilaku identik untuk semua kombinasi aktor yang sudah ada test-nya sebelumnya — dibuktikan test existing di area admin tetap hijau tanpa modifikasi.

**4.7 — Test guardrail permanen (allowlist baseline)**: test baru di `tests/Unit/RoleSeederTest.php` yang mengecek EXACT permission list (independen dari `RoleSeeder.php`, ditulis ulang manual di test) untuk kelima baseline role — `guru`, `pegawai_lembaga`+`pegawai_yayasan` (digabung 1 test karena identik), `siswa`, `orang_tua`. Test ini harus gagal kalau ada developer masa depan menambah permission baru ke salah satu baseline role tanpa update allowlist test secara sengaja.

## 5. Ringkasan Perubahan File

```text
database/seeders/RoleSeeder.php                                    [keluarkan 'kasus.view' dari baseline pegawai_lembaga/pegawai_yayasan]
app/Domains/Kasus/Policies/KasusPolicy.php                          [+method viewAny(User $user): bool]
app/Http/Controllers/KasusController.php                            [index(): ganti authorize('kasus.view') → authorize('viewAny', Kasus::class)]
app/Http/Controllers/KasusSesiController.php                        [hapus 2x authorize('kasus.view') yang redundan]
app/Http/Controllers/KasusTugasController.php                       [hapus 2x authorize('kasus.view') yang redundan]
app/Http/Controllers/KasusTugasBatchPreviewController.php           [hapus 1x authorize('kasus.view') yang redundan]
app/Http/Controllers/KasusTugasSubmissionController.php             [hapus 3x authorize('kasus.view') yang redundan]
app/Http/Controllers/KasusEvaluasiController.php                    [hapus 1x authorize('kasus.view') yang redundan]
app/Http/Controllers/KasusController.php (show())                   [hapus 1x authorize('kasus.view') yang redundan]
tests/Unit/RoleSeederTest.php                                        [+4 test allowlist baseline permission]
tests/Feature/KasusKonselorAksesTest.php ATAU file baru              [+test reproduksi karyawan-pool-via-RoleSeeder-asli, lihat 4.2-4.4]
```

## 6. Rekomendasi Terpisah (bukan bagian scope — dicatat untuk referensi masa depan)

**P2 — UX pembuatan akun** (tidak dikerjakan sekarang): evaluasi ulang form `Admin → Pengguna`/`Guru`/`Karyawan` supaya admin berpikir dalam bahasa "Wewenang/Tugas Tambahan" (checkbox: assign role fungsional) alih-alih nama permission teknis — TANPA pernah menyimpulkan wewenang dari `jenis_karyawan`/`jenis_ptk`. Ini pekerjaan UI/UX terpisah, butuh sesi brainstorming sendiri.

**Catatan clarity (bukan pekerjaan)**: nama permission `keuangan.akses` generik padahal scope-nya sempit ("akses keuangan anak sendiri"). Tidak berisiko sekarang (cuma 1 role yang pernah pegang), tapi kalau suatu saat ada role baru yang butuh permission serupa, pertimbangkan nama yang lebih eksplisit (`keuangan.akses-anak-sendiri`) untuk mencegah kebingungan yang sama seperti `kasus.view`.
