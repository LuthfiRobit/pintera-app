# Kehadiran SDM — Sub-project 3: Attendance Policy Dasar (Jam Kerja, Toleransi, Deteksi Terlambat) — Spec

## 1. Latar Belakang

Sub-project 1 (fondasi) dan Sub-project 2 (Kalender Kerja SDM) sudah SELESAI di branch `sdm-v1`. Modul saat ini bisa mencatat kehadiran dan tahu apakah suatu tanggal libur/kerja PER LEMBAGA (seragam untuk semua pegawai), tapi belum tahu apa pun soal **jam kerja per peran** — kapan seharusnya seorang pegawai masuk, berapa toleransi keterlambatan, dan apakah dia terlambat.

PRD awal modul ini eksplisit menyebut "Terlambat" SEBAIKNYA bukan status kehadiran terpisah, melainkan ATRIBUT (`is_late`, `late_minutes`) pada `AttendanceRecord` — spec Sub-project 1 §4.5 menahan penambahan kolom ini sampai ada Attendance Policy yang bisa jadi rujukan perhitungan. Sub-project ini yang membangunnya.

**Cakupan sengaja dipersempit** (hasil brainstorming): sub-project ini HANYA membangun Policy jam kerja SERAGAM per kategori pegawai (jenis_ptk/jenis_karyawan_id) — bukan shift bergilir per periode/minggu. Shift bergilir (satpam gantian pagi-malam per minggu) DITUNDA ke sub-project terpisah di masa depan (di luar 4 sub-project resmi yang sudah didekomposisi sebelumnya — kalau nanti dibutuhkan, itu jadi item roadmap baru, bukan "Sub-project 4" yang sudah dialokasikan untuk Izin/Cuti berjenjang).

## 2. Keputusan Desain (hasil brainstorming, ringkas)

| Topik | Keputusan |
|---|---|
| Cakupan | Policy jam kerja SERAGAM per kategori pegawai (bukan per-individu, bukan shift bergilir per periode) |
| Struktur target Policy | 2 kolom nullable terpisah (`jenis_ptk`, `jenis_karyawan_id`) dalam 1 tabel — meniru pola dual-FK `konselor_guru_id`/`konselor_karyawan_id` yang sudah ada di domain `Kasus`, BUKAN polymorphic (tipe kunci `jenis_ptk` string vs `jenis_karyawan_id` FK terlalu beda bentuk untuk dipaksa 1 kolom polymorphic) |
| Scope Policy | Yayasan sediakan default, lembaga bebas override — pola yang SAMA persis dengan `AttendanceMethodConfiguration` (Sub-project 1) dan `KalenderKerjaSdm` (Sub-project 2), TIDAK ditanya ulang karena sudah established |
| Override hari kerja | Policy BOLEH override hari kerja per kategori (mis. "Satpam" kerja 7 hari, override kalender lembaga yang bilang Minggu libur) — supaya sub-project ini juga menutup SEBAGIAN celah auto-alpa dari Sub-project 2 (untuk pola kerja tetap/seragam, bukan shift bergilir) |
| Fallback tanpa Policy | `is_late = false`, `late_minutes = null` — fail-safe, konsisten filosofi Sub-project 2 (kolom kosong tidak pernah menghukum siapa pun) |
| Integrasi ke kalender | Resolver BARU `AttendancePolicyResolver` yang MEMBUNGKUS `KalenderKerjaSdmResolver` TANPA mengubahnya sama sekali — menjaga Sub-project 2 tetap independen dan bebas risiko regresi |
| RBAC | Reuse total permission `kehadiran-sdm.kelola-konfigurasi`, gerbang baris default yayasan tetap by scope (`widestScopeLevel() === 'yayasan'`), TIDAK ADA permission baru |

## 3. Struktur Data

### 3.1 Tabel baru `attendance_policies`

Domain `App\Domains\Sdm\Models\AttendancePolicy`, DENGAN `BelongsToTenant` (konsisten pola `AttendanceMethodConfiguration`/`KalenderKerjaSdm`).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `yayasan_id` | FK `yayasan` | selalu terisi |
| `lembaga_id` | FK `lembaga`, nullable | null = default yayasan; terisi = override lembaga tsb |
| `jenis_ptk` | string, nullable | value `Guru.jenis_ptk` (`guru_kelas`, `guru_mapel`, `kepala_sekolah`, `tenaga_administrasi`, `guru_bk`) — TEPAT SATU dari `jenis_ptk`/`jenis_karyawan_id` terisi per baris |
| `jenis_karyawan_id` | FK `jenis_karyawan_master`, nullable | |
| `jam_masuk` | time | |
| `jam_pulang` | time, nullable | opsional, tidak dipakai untuk deteksi terlambat (cuma jam masuk yang relevan untuk itu), disediakan untuk referensi/tampilan saja |
| `toleransi_menit` | integer, default 0 | |
| `hari_kerja` | JSON, nullable | override hari kerja (list POSITIF hari 0-6, arah SAMA seperti DTO `HariKerjaSdmData` Sub-project 2 — BUKAN `hari_libur_mingguan_sdm` yang arahnya negatif) — null berarti TIDAK override, ikut kalender lembaga sepenuhnya |
| `timestamps` | | |

Unique constraint: `(yayasan_id, lembaga_id, jenis_ptk, jenis_karyawan_id)` — cegah 2 Policy untuk kategori & scope yang sama persis.

Validasi tingkat aplikasi (BUKAN DB constraint, mirip validasi `konselor_tipe` di `Kasus`): tepat satu dari `jenis_ptk`/`jenis_karyawan_id` terisi, tidak boleh keduanya null atau keduanya terisi.

### 3.2 Kolom baru di `AttendanceRecord` (Sub-project 1, `app/Domains/Sdm/Models/AttendanceRecord.php`)

`is_late` (boolean, default false), `late_minutes` (integer, nullable).

## 4. `AttendancePolicyResolver`

Domain `App\Domains\Sdm\Services\AttendancePolicyResolver`. Dua method:

**`resolvePolicy(Model $pegawai): ?AttendancePolicy`** — resolusi Policy efektif untuk pegawai ini (lembaga override → yayasan default → null kalau tidak ada sama sekali):

1. Tentukan kategori: `$pegawai instanceof Guru` → cari berdasar `jenis_ptk = $pegawai->jenis_ptk`; `$pegawai instanceof Karyawan` → cari berdasar `jenis_karyawan_id = $pegawai->jenis_karyawan_id`.
2. Cari baris `AttendancePolicy` dengan `lembaga_id = $pegawai->lembaga_id` DAN kategori cocok → kalau ada, pakai itu.
3. Kalau tidak ada, cari baris `lembaga_id = null` DAN `yayasan_id` cocok (via relasi lembaga pegawai) DAN kategori cocok → kalau ada, pakai itu.
4. Kalau tidak ada juga → return `null`.

**KEDUA query di atas WAJIB `withoutGlobalScope(TenantScope::class)`** — alasan IDENTIK dengan `KalenderKerjaSdmResolver` (spec Sub-project 2 §4): method ini dipanggil dari request HTTP nyata oleh aktor `scope_level: lembaga` (lewat `AttendanceRecordAggregator::sync()`), dan query `whereNull('lembaga_id')` akan selalu nol baris tanpa bypass eksplisit.

**`resolveLibur(Model $pegawai, CarbonInterface $tanggal): array{libur: bool, alasan: string}`**:

1. `$policy = $this->resolvePolicy($pegawai)`.
2. Kalau `$policy` ada DAN `$policy->hari_kerja` tidak null → hari kerja/libur diputuskan MURNI dari `$policy->hari_kerja` (kalender lembaga sama sekali tidak dikonsultasikan lagi untuk pegawai ini):
   - Kalau `$tanggal->dayOfWeek` ADA di `$policy->hari_kerja` → `['libur' => false, 'alasan' => 'Hari kerja sesuai kebijakan peran']`.
   - Kalau TIDAK ada → `['libur' => true, 'alasan' => 'Hari libur sesuai kebijakan peran']`.
3. Kalau `$policy` null ATAU `$policy->hari_kerja` null → delegasikan SEPENUHNYA ke `KalenderKerjaSdmResolver::resolve($pegawai->lembaga, $tanggal)` (di-inject via constructor, TIDAK diubah/disentuh sama sekali).

## 5. Integrasi ke Action & Command yang Sudah Ada

**`RecordManualAttendanceAction`, `ScanQrAttendanceAction` (Sub-project 1/2)**: ganti pemanggilan `KalenderKerjaSdmResolver::resolve($pegawai->lembaga, $tanggal)` menjadi `AttendancePolicyResolver::resolveLibur($pegawai, $tanggal)` — signature hasil SAMA (`array{libur, alasan}`), jadi logic exception `AttendanceOnHolidayException` di kedua Action itu TIDAK BERUBAH SAMA SEKALI, cuma sumber resolusinya diganti.

**`TandaiAlpaOtomatisSdm` (Sub-project 2)**: baris pengecekan `$this->resolver->resolve($lembaga, $tanggal)` (level lembaga, SERAGAM untuk semua pegawai) TIDAK BISA langsung diganti jadi per-pegawai di titik yang sama (saat ini command mengecek libur SEKALI per lembaga SEBELUM iterasi pegawai, demi efisiensi — kalau lembaga libur, lewati semua pegawai sekaligus). Perilaku baru: cek dulu level lembaga (`KalenderKerjaSdmResolver`, TIDAK BERUBAH) untuk fast-skip lembaga yang jelas-jelas libur TANPA pengecualian Policy; kalau lembaga TIDAK libur, lanjut seperti biasa. TAMBAHAN baru: untuk lembaga yang kalendernya bilang LIBUR, tambahkan 1 lapis pengecekan tambahan — iterasi pegawai aktif lembaga itu, untuk masing-masing panggil `AttendancePolicyResolver::resolveLibur($pegawai, $tanggal)`; kalau hasilnya TERNYATA `libur => false` (karena Policy override hari kerja-nya), pegawai itu TETAP diproses (bukan dilewati) — inilah yang menutup celah "satpam kerja pas hari libur lembaga tidak terdeteksi bolos".

## 6. Perhitungan `is_late`/`late_minutes`

Di `AttendanceRecordAggregator::sync()` (Sub-project 1, `app/Domains/Sdm/Services/AttendanceRecordAggregator.php`) — SETELAH `$waktuMasuk` dihitung seperti biasa (logic resolusi existing TIDAK BERUBAH), tambahkan:

```
$policy = $this->policyResolver->resolvePolicy($pegawai);

if ($policy && $waktuMasuk) {
    $batasWaktu = $tanggal->setTimeFromTimeString($policy->jam_masuk)->addMinutes($policy->toleransi_menit);
    $lateMinutes = $waktuMasuk->greaterThan($batasWaktu) ? $batasWaktu->diffInMinutes($waktuMasuk) : 0;
    $isLate = $lateMinutes > 0;
} else {
    $isLate = false;
    $lateMinutes = null;
}
```

`AttendanceRecordAggregator` inject `AttendancePolicyResolver` via constructor (tambahan dependency baru, TIDAK mengubah signature method publik `sync()`). Kolom `is_late`/`late_minutes` ikut disertakan di `updateOrCreate()` yang sudah ada.

**Catatan penting**: perhitungan ini HANYA relevan kalau event hari itu berasal dari `arah: 'masuk'` (waktu_masuk ada). Kalau pegawai cuma punya event `arah: 'pulang'` tanpa `masuk` sama sekali (`waktuMasuk` null), `is_late` otomatis `false`/`late_minutes` null — tidak ada dasar untuk menghitung terlambat tanpa jam masuk aktual.

## 7. RBAC

TIDAK ADA permission baru. Semua endpoint kelola Attendance Policy pakai `$this->authorize('kehadiran-sdm.kelola-konfigurasi')` yang sudah ada. Gerbang baris `lembaga_id = null` (default yayasan) tetap `$request->user()->widestScopeLevel() === 'yayasan'`, pola SAMA seperti `AttendanceConfigurationController` yang sudah ada.

## 8. UI

Tab baru "Attendance Policy" di halaman `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php` yang sudah ada (3 tab total sekarang: Metode & Titik Absen, Kalender Kerja, Attendance Policy). Isi: daftar Policy (nasional dahulu, lalu lembaga aktif) dengan badge kategori (Guru: [jenis_ptk] atau Karyawan: [nama jenis_karyawan_master]), jam masuk/pulang, toleransi, indikator override hari kerja kalau ada; modal tambah/edit Policy (pilih tipe kategori Guru/Karyawan dulu, lalu dropdown kategori spesifik, jam masuk, jam pulang opsional, toleransi menit, checklist hari kerja opsional dengan toggle "override kalender lembaga").

Query listing controller WAJIB pakai pola bypass yang SAMA seperti `AttendanceConfigurationController::index()`/`kalenderEntriList` (Sub-project 1/2) — `withoutGlobalScope(TenantScope::class)` + filter manual `yayasan_id` dan `(lembaga_id = aktif OR lembaga_id null)` — TANPA itu baris default yayasan tidak akan pernah muncul untuk aktor `admin_sdm`.

## 9. Yang TIDAK Berubah / Di Luar Cakupan

- Shift bergilir per periode/minggu (satpam gantian pagi-malam) — TIDAK dibangun, item roadmap terpisah di masa depan kalau dibutuhkan.
- Policy per-individu pegawai (kustomisasi di luar kategori) — TIDAK dibangun, Policy selalu per-kategori (jenis_ptk/jenis_karyawan_id) untuk seluruh sub-project ini.
- `KalenderKerjaSdmResolver`, `KalenderKerjaSdm`, tabel `kalender_kerja_sdm`, command `TandaiAlpaOtomatisSdm` bagian fast-skip level-lembaga — TIDAK diubah sama sekali (hanya DITAMBAH pengecekan lapis kedua di command, dijelaskan §5), tetap sepenuhnya berfungsi independen seperti sebelumnya untuk pegawai/kategori TANPA Policy.
- Izin/cuti berjenjang — tetap Sub-project 4 (Workflow domain reuse), tidak berubah.
- Payroll/perhitungan gaji berbasis `late_minutes` — kolom disediakan (sesuai rekomendasi PRD demi fleksibilitas masa depan), TIDAK ADA integrasi payroll aktual di sub-project ini.

## 10. Testing

- Test `AttendancePolicyResolver::resolvePolicy()`: lembaga override lebih diprioritaskan dari default yayasan; kategori `jenis_ptk` dan `jenis_karyawan_id` di-resolve terpisah dengan benar; return `null` kalau tidak ada Policy sama sekali untuk kategori itu.
- Test regresi tenant-isolation: SAMA polanya seperti `KalenderKerjaSdmTenantIsolationTest` (Sub-project 2) — resolver tetap benar walau dipanggil dalam konteks aktor `scope_level: lembaga` yang login.
- Test `AttendancePolicyResolver::resolveLibur()`: Policy dengan `hari_kerja` override menghasilkan jawaban BEDA dari `KalenderKerjaSdmResolver` murni (mis. Minggu jadi hari kerja); Policy TANPA `hari_kerja` override (null) mendelegasikan 100% ke `KalenderKerjaSdmResolver` tanpa modifikasi.
- Test `AttendanceRecordAggregator::sync()`: pegawai dengan Policy jam 07:00 toleransi 15 menit, masuk jam 07:20 → `is_late = true`, `late_minutes = 5`; masuk jam 07:10 (masih dalam toleransi) → `is_late = false`; pegawai TANPA Policy sama sekali → `is_late = false`, `late_minutes = null` walau masuk jam berapa pun; pegawai yang cuma punya event `arah: pulang` tanpa `masuk` → `is_late = false`.
- Test `TandaiAlpaOtomatisSdm` versi baru: lembaga libur + pegawai TANPA Policy override → tetap dilewati (perilaku Sub-project 2 tidak berubah); lembaga libur + pegawai DENGAN Policy override hari kerja mencakup hari itu + tidak ada AttendanceRecord → tetap ditandai Alpa (celah tertutup); lembaga TIDAK libur → perilaku existing tidak berubah sama sekali.
- Test RBAC: aktor `scope_level: lembaga` mencoba buat Policy `lembaga_id = null` → ditolak (bukan dari cek `hasRole`); aktor yayasan berhasil.
- Full suite HANYA di task terakhir plan implementasi, minta izin user dulu sebelum dijalankan.

## 11. Asumsi

- Baseline: commit `ece853d` di branch `sdm-v1` (handoff log Sub-project 2) saat spec ini ditulis. Plan implementasi WAJIB verifikasi ulang isi `AttendanceRecordAggregator.php`, `RecordManualAttendanceAction.php`, `ScanQrAttendanceAction.php`, `TandaiAlpaOtomatisSdm.php`, `AttendanceConfigurationController.php`, `konfigurasi.blade.php` kalau ada commit baru masuk sebelum eksekusi.
- `Guru.jenis_ptk` tetap 5 value tetap (`guru_kelas`, `guru_mapel`, `kepala_sekolah`, `tenaga_administrasi`, `guru_bk`) — TIDAK berubah dari yang sudah ada, dikonfirmasi lewat `GuruController::JENIS_PTK_OPTIONS`.
- `jenis_karyawan_master` TIDAK punya kolom `yayasan_id`/`lembaga_id` (tabel global lintas seluruh sistem, dikonfirmasi lewat migrasi `create_jenis_karyawan_master_table`) — inilah kenapa Policy WAJIB tetap dikombinasikan dengan `yayasan_id`/`lembaga_id` sendiri di tabel `attendance_policies`, TIDAK bisa mengandalkan scoping dari `jenis_karyawan_master` itu sendiri (yang tidak ada).
