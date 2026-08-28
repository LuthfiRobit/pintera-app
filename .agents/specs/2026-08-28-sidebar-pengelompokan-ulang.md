# Pengelompokan Ulang Sidebar — Personal Context Precedence — Design Spec

**Tanggal**: 2026-08-28
**Branch**: `rbac-v2` (atau branch baru — ditentukan saat writing-plan)
**Konteks**: `resources/views/layouts/sidebar.blade.php` dipakai SATU file untuk SEMUA role lewat array `$navGroups` yang difilter `Auth::user()->can(...)` per item. Analisis menemukan dua prinsip pengelompokan tercampur dalam satu struktur, menyebabkan beberapa item personal (milik user sendiri) nyasar ke grup yang terasa seperti menu admin, dan satu gap gate yang sudah ditemukan sesi RBAC v2 sebelumnya belum ikut diperbaiki.

---

## 1. Latar Belakang & Audit

### 1.1 — Masalah: dua prinsip pengelompokan tercampur

Sebagian grup berbasis **audiens** (`Ruang Guru`, `Kehadiran Saya` = "kepunyaan saya sendiri"), sebagian berbasis **domain** (`Akademik`, `Keuangan`, `Data Induk`, `Sarana & Prasarana` = "kelola untuk institusi"). Item yang permission-nya dipegang bersama oleh identitas personal DAN role administratif jadi salah tempat:

- **RPP**: `rpp.view` dipegang 4 role sekaligus (`kepala_sekolah`, `guru`, `wakasek_kurikulum`, `operator_akademik`, dikonfirmasi lewat `RoleSeeder.php`) dengan kombinasi `rpp.kelola`/`rpp.verify` berbeda-beda per role. Link "Perangkat Ajar (RPP)" (`sidebar.blade.php:37`) SELALU muncul di grup `Akademik` untuk siapa pun yang punya `rpp.view` — termasuk guru, yang RPP-nya sendiri jadi terpisah dari item "Ruang Guru" lain miliknya (Jurnal, Komponen Penilaian, Asesmen, Rapor Wali).
- **Kasus Pendampingan** (`sidebar.blade.php:49`, guard `can('kasus.view')`): dipegang baseline `guru`, `siswa`, `orang_tua` (self-service) SEKALIGUS oleh role administratif (`guru_bk`, `wakasek_kesiswaan`, `operator_akademik`) — nempel di grup `Pendampingan` yang isinya kebanyakan link staf-triase (`admin.kasus.*`).
- **Keuangan Saya** (`sidebar.blade.php:73-75`, guard `keuangan.akses && orangTua !== null`): item self-service orang tua (Dompet, Tagihan, Riwayat) dicampur definisi dalam array yang sama dengan item billing staf (Virtual Account, Jenis Tagihan, Verifikasi Pembayaran) di bawah 1 label `Keuangan`. Aman secara runtime (guard membuat mutually exclusive), tapi tercampur secara definisi kode.

### 1.2 — Temuan: Ruang Siswa & Ruang Orang Tua tidak punya grup sendiri, dengan status data-flow yang BERBEDA

**Siswa** — audit `route:list` penuh untuk kata kunci nilai/jadwal/rapor/presensi: **nol route** `siswa.*`. Baseline role `siswa` (`RoleSeederTest.php`, dikonfirmasi ulang) cuma punya 1 permission (`kasus.view`). Tidak ada backend/data-flow apa pun untuk siswa melihat nilai/jadwal/presensi miliknya sendiri — fiturnya memang belum pernah dibangun, bukan cuma belum di-link ke sidebar.

**Orang tua** — situasinya BERBEDA, harus dibedakan secara eksplisit di spec ini: `DashboardController::index()` (baris 173-256, cabang `hasRole('orang_tua')`) SUDAH membangun query lengkap untuk 3 hal —
- `$nilaiTerbaru` (nilai anak, join `NilaiSiswa`/`KomponenPenilaian`/`Asesmen`, dibatasi 5 terbaru)
- `$riwayatIzinSakit` (riwayat izin/sakit anak dari `Presensi`, dibatasi 5 terbaru)
- `$jadwalAnakHariIni` (jadwal pelajaran anak HARI INI dari `JadwalPelajaran`)

— dan menampilkannya sebagai widget ringkas di `admin/dashboard/orang-tua.blade.php`. **Data-flow/backend-nya SUDAH ADA**, hanya belum ada halaman dedicated (route/controller/view terpisah) untuk melihat riwayat lengkap (bukan cuma "5 terbaru"/"hari ini"). Audit `route:list` mengonfirmasi nol route `orang-tua.nilai`/`orang-tua.jadwal`/dst — yang ada cuma route admin manajemen data (`admin.orang-tua.*`).

**Keputusan produk (dikunci sesi ini)**: untuk fase ini, KEDUA kelompok (Siswa dan Orang Tua) sama-sama diarahkan ke halaman **"Dalam Pengembangan"** yang sama — TAPI alasannya beda dan HARUS ditulis beda di kode/dokumentasi (lihat §2.3), supaya siapa pun yang membaca plan/kode nanti tidak salah simpul bahwa Orang Tua butuh controller baru dibangun dari nol (padahal cuma butuh ekstraksi dari query yang sudah ada — pekerjaan itu SENGAJA ditunda ke siklus terpisah, bukan bagian scope spec ini).

### 1.3 — Gap yang sudah ditemukan sesi RBAC v2 sebelumnya, ikut ditutup di sini

Item "Kasus Pendampingan" (`sidebar.blade.php:49`) mengecek `Auth::user()->can('kasus.view')` langsung — BUKAN `KasusPolicy::viewAny()` yang dibuat sesi sebelumnya (`.agents/specs/2026-08-28-rbac-v2-baseline-wewenang-vs-selfservice.md`). Akibatnya konselor pool karyawan yang HANYA dapat akses lewat fakta domain (`konselor_karyawan_id`, TANPA `kasus.view` eksplisit) tidak melihat menu ini walau halaman `/kasus` bisa diakses langsung lewat URL.

**Verifikasi bahwa fix ini AMAN untuk baseline existing** (poin krusial dari review): `guru`/`siswa`/`orang_tua` semuanya punya `kasus.view` di baseline (`RoleSeederTest.php`, dikonfirmasi ulang), jadi cabang PERTAMA `viewAny()` (`$user->can('kasus.view')`) SELALU `true` untuk ketiganya — mengganti guard ke `can('viewAny', Kasus::class)` **TIDAK MENGUBAH PERILAKU SAMA SEKALI** untuk guru/siswa/orang_tua, mereka tetap selalu melihat menu ini persis seperti sekarang. Yang berubah HANYA menambahkan visibility untuk konselor pool karyawan (cabang KEDUA `viewAny()`, fakta domain) yang sebelumnya tidak terlihat sama sekali. **Tidak ada perubahan `KasusPolicy` yang dibutuhkan** — method `viewAny()` sudah benar dari sesi sebelumnya, spec ini hanya mengoreksi TITIK PEMANGGILANNYA di sidebar.

## 2. Keputusan Desain

### 2.1 — Prinsip: Personal Context Precedence (aturan navigasi UI, BUKAN hierarki RBAC)

> **Presedensi navigasi**: ketika satu akun memiliki lebih dari satu identitas domain yang relevan untuk navigasi (mis. seorang guru yang juga membawa role administratif), sidebar memilih SATU konteks personal utama untuk menampilkan item personal miliknya, berdasarkan urutan yang ditentukan khusus untuk kebutuhan tampilan navigasi. Urutan ini **TIDAK merepresentasikan dan TIDAK boleh dibaca sebagai** hierarki otorisasi RBAC (`hasRole('guru')` bukan berarti guru "lebih berwenang" dari siswa/orang_tua/karyawan — itu murni urutan pengecekan identitas untuk memilih grup tampilan).

Urutan pengecekan (identitas → grup tampilan personal):
1. `Auth::user()->hasRole('guru')` → `Ruang Guru`
2. `Auth::user()->hasRole('siswa')` → `Ruang Siswa`
3. `Auth::user()->orangTua !== null` → `Ruang Orang Tua`
4. Selain ketiganya (karyawan/pegawai pool tanpa identitas guru/siswa/orang tua) → `Kehadiran Saya` (grup fallback, tetap ada)

**Batasan eksplisit yang dikunci (mencegah salah tafsir)**:
- Role `guru_bk`/`wali_kelas` (assignment-only, terbukti TIDAK butuh profil `Guru`/`Karyawan` — audit sesi RBAC v2 sebelumnya) **TIDAK** ikut menentukan konteks personal. Seorang karyawan yang diberi role `guru_bk` TANPA punya role `guru` TETAP masuk fallback `Kehadiran Saya` (poin 4), BUKAN `Ruang Guru` — karena `hasRole('guru')` di poin 1 mengecek keberadaan role `guru` secara harfiah, bukan permission `kasus.triase`/`kasus.view` yang kebetulan sama-sama dipegang `guru_bk`.
- `pegawai_lembaga`/`pegawai_yayasan` (scope-carrier, selalu menyertai `guru` sesuai invariant RBAC v2) TIDAK pernah jadi penentu konteks sendiri — kalau user juga py `guru`, poin 1 menang duluan; kalau tidak, jatuh ke poin 4.
- Precedence ini HANYA mengatur DI GRUP MANA item personal ditampilkan — TIDAK mengubah permission/gate APA PUN yang menentukan APAKAH item itu boleh dilihat. Guard `can(...)` per item tetap sama seperti sebelumnya (kecuali Kasus Pendampingan, lihat §2.2).

### 2.2 — Perubahan konkret struktur `$navGroups`

**(a) Grup `Ruang Guru`** — tambah 3 item pindahan (guard TIDAK berubah, cuma pindah lokasi + tambah kondisi `hasRole('guru')` implisit lewat precedence):
```php
[
    'label' => 'Ruang Guru',
    'group_icon' => 'graduation-cap',
    'items' => array_filter([
        Auth::user()->can('presensi.isi') ? [...'Jurnal & Presensi'...] : null,
        Auth::user()->can('presensi.isi') ? [...'Rekap Kehadiran'...] : null,
        Auth::user()->can('komponen-penilaian.kelola-sendiri') ? [...'Komponen Penilaian (TP)'...] : null,
        Auth::user()->can('asesmen.kelola') ? [...'Asesmen & Nilai'...] : null,
        Auth::user()->can('rapor.input-wali') ? [...'Rapor Wali Kelas'...] : null,
        // BARU — pindah dari 'Akademik', HANYA muncul untuk identitas guru:
        Auth::user()->hasRole('guru') && Auth::user()->can('rpp.view') ? ['route' => 'admin.rpp.index', 'pattern' => 'admin.rpp.*', 'label' => 'Perangkat Ajar (RPP)', 'icon' => 'file-text'] : null,
        // BARU — pindah dari 'Kehadiran Saya', HANYA muncul untuk identitas guru:
        Auth::user()->hasRole('guru') && Auth::user()->can('kehadiran-sdm.lihat-qr-sendiri') ? ['route' => 'sdm.qr-saya', 'pattern' => 'sdm.qr-saya', 'label' => 'QR Kehadiran Saya', 'icon' => 'qr-code'] : null,
        Auth::user()->hasRole('guru') && Auth::user()->can('kehadiran-sdm.izin.lihat-sendiri') ? ['route' => 'sdm.izin-cuti.index', 'pattern' => 'sdm.izin-cuti.*', 'label' => 'Izin/Cuti Saya', 'icon' => 'calendar-days'] : null,
        // BARU — Kasus Pendampingan self-service, HANYA untuk identitas guru:
        Auth::user()->hasRole('guru') && Auth::user()->can('viewAny', Kasus::class) ? ['route' => 'kasus.index', 'pattern' => 'kasus.*', 'label' => 'Kasus Pendampingan', 'icon' => 'stethoscope'] : null,
    ]),
],
```

**(b) Grup `Ruang Siswa`** (BARU):
```php
[
    'label' => 'Ruang Siswa',
    'group_icon' => 'backpack',
    'items' => array_filter([
        Auth::user()->hasRole('siswa') ? ['route' => 'dalam-pengembangan', 'pattern' => 'dalam-pengembangan', 'params' => ['fitur' => 'nilai-rapor'], 'label' => 'Nilai & Rapor', 'icon' => 'award'] : null,
        Auth::user()->hasRole('siswa') ? ['route' => 'dalam-pengembangan', 'pattern' => 'dalam-pengembangan', 'params' => ['fitur' => 'jadwal-pelajaran'], 'label' => 'Jadwal Pelajaran', 'icon' => 'calendar-clock'] : null,
        Auth::user()->hasRole('siswa') ? ['route' => 'dalam-pengembangan', 'pattern' => 'dalam-pengembangan', 'params' => ['fitur' => 'presensi-saya'], 'label' => 'Presensi Saya', 'icon' => 'clipboard-check'] : null,
        Auth::user()->hasRole('siswa') && Auth::user()->can('viewAny', Kasus::class) ? ['route' => 'kasus.index', 'pattern' => 'kasus.*', 'label' => 'Kasus Pendampingan', 'icon' => 'stethoscope'] : null,
    ]),
],
```
**Catatan wajib** (mencegah salah baca saat writing-plan/implementasi): 3 item pertama grup ini murni **navigasi ke halaman "Dalam Pengembangan"** — TIDAK ADA backend/data-flow siswa yang perlu dibangun sebagai bagian spec ini. Ini genuinely "belum ada apa-apa", bukan "sudah ada tapi ditunda".

**(c) Grup `Ruang Orang Tua`** (BARU):
```php
[
    'label' => 'Ruang Orang Tua',
    'group_icon' => 'users',
    'items' => array_filter([
        Auth::user()->orangTua !== null ? ['route' => 'dalam-pengembangan', 'pattern' => 'dalam-pengembangan', 'params' => ['fitur' => 'nilai-anak'], 'label' => 'Nilai Anak', 'icon' => 'award'] : null,
        Auth::user()->orangTua !== null ? ['route' => 'dalam-pengembangan', 'pattern' => 'dalam-pengembangan', 'params' => ['fitur' => 'jadwal-anak'], 'label' => 'Jadwal Anak', 'icon' => 'calendar-clock'] : null,
        Auth::user()->orangTua !== null ? ['route' => 'dalam-pengembangan', 'pattern' => 'dalam-pengembangan', 'params' => ['fitur' => 'riwayat-izin-sakit-anak'], 'label' => 'Riwayat Izin/Sakit Anak', 'icon' => 'clipboard-check'] : null,
        // Pindah dari 'Keuangan' (domain), guard TIDAK berubah:
        Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.dashboard', 'pattern' => 'keuangan.dashboard', 'label' => 'Dompet & Tagihan Saya', 'icon' => 'wallet'] : null,
        Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.tagihan.index', 'pattern' => 'keuangan.tagihan.*', 'label' => 'Tagihan', 'icon' => 'receipt'] : null,
        Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.riwayat.index', 'pattern' => 'keuangan.riwayat.*', 'label' => 'Riwayat', 'icon' => 'history'] : null,
        // Kasus Pendampingan self-service, HANYA untuk identitas orang tua (guru/siswa sudah dicek duluan di precedence):
        Auth::user()->orangTua !== null && Auth::user()->can('viewAny', Kasus::class) ? ['route' => 'kasus.index', 'pattern' => 'kasus.*', 'label' => 'Kasus Pendampingan', 'icon' => 'stethoscope'] : null,
    ]),
],
```
**Catatan wajib**: 3 item pertama grup ini juga menuju halaman "Dalam Pengembangan" yang SAMA (route `dalam-pengembangan`, dibedakan parameter `fitur` untuk judul dinamis) — TAPI dengan alasan berbeda dari Siswa: data-flow-nya SUDAH ADA (`DashboardController.php:173-256`), cuma belum diekstrak jadi halaman dedicated. Keputusan produk (dikunci sesi ini): ekstraksi itu SENGAJA ditunda ke siklus terpisah, bukan scope spec ini — spec ini murni menambahkan ENTRY POINT navigasi yang konsisten dengan pola Siswa, bukan membangun controller baru.

**(d) Grup `Kehadiran Saya`** — jadi fallback, guard tiap item ditambah pengecualian precedence supaya tidak duplikat dengan `Ruang Guru`:
```php
[
    'label' => 'Kehadiran Saya',
    'group_icon' => 'clock',
    'items' => array_filter([
        ! Auth::user()->hasRole('guru') && Auth::user()->can('kehadiran-sdm.lihat-qr-sendiri') ? ['route' => 'sdm.qr-saya', ...'QR Kehadiran Saya'...] : null,
        ! Auth::user()->hasRole('guru') && Auth::user()->can('kehadiran-sdm.izin.lihat-sendiri') ? ['route' => 'sdm.izin-cuti.index', ...'Izin/Cuti Saya'...] : null,
        // Kasus Pendampingan self-service untuk karyawan pool (bukan guru/siswa/orang tua):
        ! Auth::user()->hasRole('guru') && ! Auth::user()->hasRole('siswa') && Auth::user()->orangTua === null && Auth::user()->can('viewAny', Kasus::class) ? ['route' => 'kasus.index', ...'Kasus Pendampingan'...] : null,
    ]),
],
```

**(e) Grup `Akademik`** — hapus definisi RPP lama yang unconditional (`Auth::user()->can('rpp.view') ? [...] : null` di baris 37), GANTI dengan definisi baru berguard `! Auth::user()->hasRole('guru') && Auth::user()->can('rpp.view')`. TIDAK ADA perubahan lain di grup ini. Hasilnya: kepsek/wakasek_kurikulum/operator_akademik (yang tidak `hasRole('guru')`) tetap melihat RPP di `Akademik` seperti sebelumnya; guru (yang RPP-nya sudah dipindah ke `Ruang Guru` di §2.2(a)) tidak lagi melihatnya di sini — mencegah duplikat.

**(f) Grup `Pendampingan`** — hapus item Kasus Pendampingan self-service (baris 49 lama), sisakan HANYA item staf (`admin.kasus.*`): Triase Kasus, Log Akses Klinis, Kasus Terhapus. TIDAK ADA perubahan guard pada ketiganya.

**(g) Grup `Keuangan`** — hapus 3 item self-service orang tua (baris 73-75 lama, pindah ke `Ruang Orang Tua`), sisakan HANYA item billing staf (Virtual Account, Jenis Tagihan, Tagihan, Verifikasi Pembayaran, Verifikasi Transfer Manual).

### 2.3 — Halaman "Dalam Pengembangan"

Satu route + satu view generik dipakai bersama Siswa dan Orang Tua:
```php
// routes/web.php (dalam middleware auth)
Route::get('/dalam-pengembangan', function (\Illuminate\Http\Request $request) {
    return view('shared.dalam-pengembangan', ['fitur' => $request->query('fitur', 'Fitur ini')]);
})->name('dalam-pengembangan')->middleware('auth');
```
View `resources/views/shared/dalam-pengembangan.blade.php` — tampilan sederhana pakai `layouts.app`, judul dinamis dari parameter `fitur` (map ke label manusiawi, mis. `nilai-anak` → "Nilai Anak"), pesan "Fitur ini sedang dalam pengembangan." Tidak ada logic tambahan apa pun — halaman statis parametrized.

## 3. Non-Goals (eksplisit di luar scope)

- **Tidak** membangun controller/route/halaman nyata untuk Nilai/Jadwal/Presensi Siswa ATAU Nilai/Jadwal/Riwayat Anak Orang Tua — keduanya cuma dapat entry point ke halaman "Dalam Pengembangan". Ekstraksi query `DashboardController` orang tua jadi halaman sungguhan adalah pekerjaan TERPISAH, dicatat di `PETA_PENGEMBANGAN.md`, TIDAK dikerjakan di sini.
- **Tidak** mengubah `KasusPolicy::viewAny()` — method-nya sudah benar dari sesi RBAC v2 sebelumnya (§1.3 membuktikan cabang capability-nya `true` untuk guru/siswa/orang_tua tanpa perubahan apa pun). Spec ini HANYA mengubah titik pemanggilan di sidebar.
- **Tidak** mengubah gate/permission APA PUN selain guard "Kasus Pendampingan" (dari `can('kasus.view')` ke `can('viewAny', Kasus::class)`). Semua item lain pindah lokasi TANPA perubahan guard.
- **Tidak** mengubah `visibleUsersQuery()`/`applyScopeGroup()`/`scopeGroups()` di `UserController.php` — itu taksonomi terpisah untuk halaman Pengguna, tidak terkait sidebar.
- **Tidak** membahas/merancang bottom nav mobile/tablet — sudah dicatat terpisah sebagai backlog di `PETA_PENGEMBANGAN.md`, di luar scope sesi ini.
- **Tidak** ada perubahan skema database/migration.

## 4. Testing (acceptance criteria wajib)

**4.1** — Guru (`hasRole('guru')`, TANPA role lain) melihat: `Perangkat Ajar (RPP)`, `QR Kehadiran Saya`, `Izin/Cuti Saya` di grup `Ruang Guru` — BUKAN di `Akademik`/`Kehadiran Saya`.

**4.2** — Guru yang JUGA punya role administratif (mis. `wakasek_kurikulum`) — item personalnya (RPP, QR, Izin) TETAP di `Ruang Guru` (bukan pindah/duplikat ke grup lain), item administratif `wakasek_kurikulum` (Komponen Penilaian kelola, dst) tetap tampil normal di `Akademik`. Membuktikan precedence tidak menyembunyikan kapabilitas administratif, hanya menentukan lokasi item PERSONAL.

**4.3** — Kepala Sekolah/Wakasek Kurikulum/Operator Akademik (TANPA `hasRole('guru')`) tetap melihat `Perangkat Ajar (RPP)` di grup `Akademik` seperti sebelumnya.

**4.4** — Karyawan dengan role `guru_bk` TAPI TANPA role `guru` (skenario eksplisit dari review: role assignment-only tidak mengubah konteks) — Kasus Pendampingan-nya (kalau relevan) muncul di grup `Kehadiran Saya`, BUKAN `Ruang Guru`. `guru_bk` tidak pernah mengubah `hasRole('guru')` jadi `true`.

**4.5** — Siswa (`hasRole('siswa')`) melihat grup `Ruang Siswa` dengan 3 item mengarah ke `route('dalam-pengembangan', ['fitur' => ...])`, masing-masing menampilkan judul fitur yang sesuai di halaman tujuan.

**4.6** — Orang tua (`orangTua !== null`) melihat grup `Ruang Orang Tua` dengan 3 item "Dalam Pengembangan" + 3 item keuangan (dipindah dari grup `Keuangan` lama). Item self-service orang tua (Dompet & Tagihan Saya/Tagihan/Riwayat) TIDAK LAGI DIDEFINISIKAN di grup `Keuangan` (domain) sama sekali — ditegaskan sebagai fakta struktur kode (definisi itemnya sudah dipindah, bukan diduplikasi), bukan klaim runtime "tidak pernah muncul untuk kombinasi role apa pun di masa depan".

**4.7 — Regresi kritis (Kasus Pendampingan, guard `viewAny`)**:
- Guru/siswa/orang_tua baseline (dari `RoleSeeder` sungguhan, TANPA pernah jadi konselor) → TETAP melihat menu Kasus Pendampingan di grup masing-masing (cabang `can('kasus.view')` di `viewAny()` tetap `true`, TIDAK BERUBAH dari sebelumnya).
- Karyawan pool (`pegawai_lembaga`/`pegawai_yayasan`, dari `RoleSeeder` sungguhan, TANPA `kasus.view` — sesuai fix RBAC v2 sebelumnya) yang DITUGASKAN sebagai konselor (`konselor_karyawan_id` ter-set) → SEKARANG melihat menu Kasus Pendampingan di grup `Kehadiran Saya` (sebelumnya TIDAK terlihat sama sekali — ini bukti gap §1.3 tertutup).
- Karyawan pool TANPA `kasus.view` DAN TANPA riwayat konselor apa pun → TETAP TIDAK melihat menu ini (baik sebelum maupun sesudah fix).

**4.8** — Dikonfirmasi lewat `grep -rl "navGroups\|sidebar\.blade" tests/`: **belum ada test sidebar existing** (2 hasil grep awal untuk "Ruang Guru" adalah kebetulan string tak terkait, dikonfirmasi bukan test sidebar). Jadi tidak ada assertion lama yang perlu diupdate — semua test §4.1-4.7 murni baru, ditulis di `tests/Feature/SidebarPengelompokanTest.php` (file baru).

## 5. Ringkasan Perubahan File

```text
resources/views/layouts/sidebar.blade.php   [restrukturisasi $navGroups sesuai §2.2(a)-(g)]
routes/web.php                              [+1 route 'dalam-pengembangan']
resources/views/shared/dalam-pengembangan.blade.php   [BARU, view statis parametrized]
tests/Feature/SidebarPengelompokanTest.php  [BARU, +test sesuai §4.1-4.7]
```
