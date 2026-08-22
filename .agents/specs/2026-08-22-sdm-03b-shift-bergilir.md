# Kehadiran SDM — Item Tertunda Sub-project 3: Penugasan Shift per Periode — Spec

## 1. Latar Belakang

Sub-project 1, 2, dan 3 SUDAH SELESAI di branch `sdm-v1`. Saat brainstorming Sub-project 3 (Attendance Policy), user memutuskan memecah cakupan: Policy jam kerja SERAGAM per kategori pegawai dikerjakan duluan, sementara **shift bergilir** (satpam gantian shift pagi/malam per minggu, bukan pola tetap) DITUNDA sebagai item terpisah.

**Catatan penomoran**: item ini BUKAN "Sub-project 4" resmi — Sub-project 4 (Izin/Cuti berjenjang via `App\Domains\Workflow`) tetap dialokasikan terpisah dan belum dikerjakan. Ini adalah item roadmap yang tertunda dari Sub-project 3, dikerjakan sekarang atas permintaan eksplisit user, sebelum Sub-project 4.

**Cakupan sengaja dipersempit lagi** (hasil brainstorming): item ini HANYA mencakup **penugasan shift manual per periode** — admin input eksplisit "pegawai X pakai shift Y dari tanggal A sampai B". **Rotasi otomatis** (sistem sendiri yang gonta-ganti shift tanpa input ulang tiap periode, mis. pola "shift A minggu ganjil, shift B minggu genap") DITUNDA LAGI ke item terpisah berikutnya kalau nanti dibutuhkan.

## 2. Keputusan Desain (hasil brainstorming, ringkas)

| Topik | Keputusan |
|---|---|
| Cakupan | Penugasan shift manual per periode saja — TIDAK ADA rotasi otomatis |
| Target penugasan | Per INDIVIDU pegawai (bukan per kategori) — 2 pegawai kategori sama bisa punya shift berbeda di waktu yang sama, supaya gantian sungguhan antar-orang bisa direpresentasikan. Berlaku untuk pegawai/kategori APAPUN (Guru atau Karyawan, kategori manapun) — TIDAK ADA hardcode nama kategori (mis. "satpam") di kode manapun, itu cuma contoh ilustrasi |
| Template shift | Tabel `jenis_shift` terpisah (nama, jam masuk, jam pulang) + tabel `penugasan_shift` yang menugaskan template itu ke pegawai per rentang tanggal — BUKAN jam ditulis langsung di tiap penugasan |
| Relasi ke `AttendancePolicy` (Sub-project 3) | Shift aktif MENANG untuk jam kerja & hari kerja pada tanggal itu; toleransi keterlambatan TETAP dari `AttendancePolicy` pegawai itu kalau ada, fallback 0 menit kalau tidak ada Policy sama sekali (beda dari perilaku dasar Sub-project 3 — di bawah shift aktif, keterlambatan TETAP dicek dengan toleransi default 0, karena shift menyiratkan jadwal ketat, bukan opsional) |
| Tumpang tindih tanggal | Ditolak saat input — validasi eksplisit sebelum simpan, mencegah ambiguitas |
| Struktur resolver | `ShiftAwareAttendanceResolver` BARU yang MEMBUNGKUS `AttendancePolicyResolver` (Sub-project 3) TANPA mengubahnya — pola SAMA persis yang sudah 2x dipraktikkan (`AttendancePolicyResolver` membungkus `KalenderKerjaSdmResolver` tanpa mengubahnya) |
| Scope & RBAC | Yayasan-default + lembaga-override untuk `jenis_shift` (pola established), reuse total permission `kehadiran-sdm.kelola-konfigurasi`, gerbang nasional by scope — TIDAK ADA permission baru |

## 3. Struktur Data

### 3.1 Tabel baru `jenis_shift`

Domain `App\Domains\Sdm\Models\JenisShift`, DENGAN `BelongsToTenant`.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `yayasan_id` | FK `yayasan` | selalu terisi |
| `lembaga_id` | FK `lembaga`, nullable | null = template nasional/yayasan; terisi = khusus lembaga tsb |
| `nama` | string | mis. "Shift Pagi", "Shift Malam" |
| `jam_masuk` | time | |
| `jam_pulang` | time | WAJIB terisi (beda dari `AttendancePolicy.jam_pulang` yang opsional — shift secara definisi punya rentang jam kerja jelas, sering melewati tengah malam mis. 22:00-06:00, jadi `jam_pulang` penting untuk konteks walau TIDAK dipakai untuk kalkulasi apapun di item ini, cuma ditampilkan) |
| `timestamps` | | |

### 3.2 Tabel baru `penugasan_shift`

Domain `App\Domains\Sdm\Models\PenugasanShift`, DENGAN `BelongsToTenant` — BEDA dari asumsi awal draf (`EmployeeQrCode` Sub-project 1 tanpa `lembaga_id`), karena UI §7 butuh query "daftar penugasan aktif untuk lembaga ini", yang tidak praktis lewat relasi polymorphic tanpa kolom `lembaga_id` sendiri (query polymorphic lintas 2 tabel target — `Guru`/`Karyawan` — tidak bisa di-`JOIN` sederhana). `lembaga_id` di sini SELALU terisi (bukan nullable — tidak ada konsep "penugasan nasional", satu penugasan selalu milik satu pegawai di satu lembaga spesifik), jadi TIDAK ADA komplikasi bypass `TenantScope` seperti tabel-tabel config lain (tidak ada baris `lembaga_id = null` untuk disembunyikan).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `lembaga_id` | FK `lembaga` | WAJIB terisi, disalin dari `$pegawai->lembaga_id` saat dibuat (bukan diinput manual) — murni untuk keperluan query/listing per lembaga, BUKAN sumber kebenaran (kebenaran tetap `$pegawai->lembaga_id`) |
| `pegawai_type` | string | morph type (`Guru`/`Karyawan`) |
| `pegawai_id` | bigint | morph id |
| `jenis_shift_id` | FK `jenis_shift` | |
| `tanggal_mulai` | date | |
| `tanggal_selesai` | date, nullable | null = tanpa batas waktu (berlaku terus sampai diganti/dihapus) |
| `hari_kerja` | JSON, nullable | null = SEMUA hari dalam rentang dianggap hari kerja shift ini; terisi = cuma hari itu (list positif 0-6) yang dianggap kerja, hari lain dalam rentang otomatis LIBUR (TIDAK fallback ke Policy/Kalender lagi — sama filosofi override `AttendancePolicy.hari_kerja` Sub-project 1/3, begitu override aktif dia final) |
| `timestamps` | | |

Validasi tingkat aplikasi WAJIB: sebelum create, cek TIDAK ADA `penugasan_shift` lain untuk `pegawai_type`+`pegawai_id` yang sama dengan rentang tanggal tumpang tindih. Overlap-check WAJIB menangani `tanggal_selesai` nullable di KEDUA sisi (baris existing maupun baris baru) sebagai "tak terhingga ke depan".

## 4. `ShiftAwareAttendanceResolver`

Domain `App\Domains\Sdm\Services\ShiftAwareAttendanceResolver`. MEMBUNGKUS `AttendancePolicyResolver` (constructor injection) TANPA mengubahnya sama sekali (spec Sub-project 3 §4 pola yang identik diterapkan lagi di sini).

**Catatan `TenantScope` — TIDAK butuh bypass di sini** (beda dari `KalenderKerjaSdmResolver`/`AttendancePolicyResolver` yang WAJIB bypass): query `PenugasanShift` di bawah selalu dilakukan terhadap `$pegawai` SPESIFIK yang sudah tervalidasi berada di lembaga aktor (Action pemanggil sudah memverifikasi ini sebelum memanggil resolver — lihat `RecordManualAttendanceAction`/`ScanQrAttendanceAction` existing). `TenantScope` yang memfilter `lembaga_id = actingUser->lembaga_id` karena itu SELALU cocok dengan `lembaga_id` milik `$pegawai` itu sendiri (§3.2) — tidak ada baris "nasional" yang bisa disembunyikan secara keliru seperti kasus `whereNull('lembaga_id')` di resolver-resolver sebelumnya. Untuk pemanggilan dari `TandaiAlpaOtomatisSdm` (tanpa aktor login), `TenantScope` otomatis no-op, juga aman.

**`resolveLibur(Model $pegawai, CarbonInterface $tanggal): array{libur: bool, alasan: string}`**:

1. Cari `PenugasanShift` aktif untuk `$pegawai` pada `$tanggal` (`tanggal_mulai <= tanggal` DAN (`tanggal_selesai >= tanggal` ATAU `tanggal_selesai` null)).
2. Kalau TIDAK ada → delegasikan SEPENUHNYA ke `AttendancePolicyResolver::resolveLibur($pegawai, $tanggal)` (rantai fallback Policy→Kalender Sub-project 2/3 TIDAK berubah).
3. Kalau ADA:
   - `hari_kerja` shift null → `['libur' => false, 'alasan' => 'Hari kerja sesuai jadwal shift '.$jenisShift->nama]`.
   - `hari_kerja` shift terisi DAN `$tanggal->dayOfWeek` ada di dalamnya → `['libur' => false, 'alasan' => 'Hari kerja sesuai jadwal shift '.$jenisShift->nama]`.
   - `hari_kerja` shift terisi TAPI hari itu TIDAK ada di dalamnya → `['libur' => true, 'alasan' => 'Libur sesuai jadwal shift '.$jenisShift->nama]`.

**`resolveJamKerjaEfektif(Model $pegawai, CarbonInterface $tanggal): ?array{jam_masuk: string, toleransi_menit: int}`** (dipakai `AttendanceRecordAggregator`, gantikan pemanggilan langsung `AttendancePolicyResolver::resolvePolicy()` untuk kalkulasi keterlambatan):

1. Cari `PenugasanShift` aktif SAMA seperti di atas.
2. Kalau ADA → `return ['jam_masuk' => $jenisShift->jam_masuk, 'toleransi_menit' => $this->policyResolver->resolvePolicy($pegawai)?->toleransi_menit ?? 0]` (toleransi dari Policy KALAU ADA, fallback 0 — bukan `null`/skip, karena shift menyiratkan jadwal ketat, beda filosofi dari Policy dasar yang fail-safe total).
3. Kalau TIDAK ADA → `$policy = $this->policyResolver->resolvePolicy($pegawai)`; kalau `$policy` null → `return null` (fail-safe, TIDAK BERUBAH dari Sub-project 3); kalau ada → `return ['jam_masuk' => $policy->jam_masuk, 'toleransi_menit' => $policy->toleransi_menit]`.

## 5. Integrasi ke Kode yang Sudah Ada

**`RecordManualAttendanceAction`, `ScanQrAttendanceAction`** (Sub-project 1/2/3): ganti dependency constructor dari `AttendancePolicyResolver` jadi `ShiftAwareAttendanceResolver`, panggil `resolveLibur()` — signature hasil SAMA PERSIS (`array{libur, alasan}`), jadi logic exception `AttendanceOnHolidayException` di kedua Action TIDAK BERUBAH SAMA SEKALI.

**`AttendanceRecordAggregator`** (Sub-project 1/3): ganti dependency constructor dari `AttendancePolicyResolver` jadi `ShiftAwareAttendanceResolver`. Method privat `hitungKeterlambatan()` ganti pemanggilan `$this->policyResolver->resolvePolicy($pegawai)` jadi `$this->resolver->resolveJamKerjaEfektif($pegawai, $tanggal)`, lalu pakai `$hasil['jam_masuk']`/`$hasil['toleransi_menit']` (bukan `$policy->jam_masuk`/`$policy->toleransi_menit`) — logic pembanding waktu & pembulatan menit TIDAK berubah.

**`TandaiAlpaOtomatisSdm`** (Sub-project 2/3): ganti dependency constructor dari `AttendancePolicyResolver` jadi `ShiftAwareAttendanceResolver`, panggil `resolveLibur()` per pegawai (perilaku "cek per-pegawai tanpa kecuali" dari Sub-project 3 TIDAK berubah, cuma sumber resolusinya nambah 1 lapis di depan).

`AttendancePolicyResolver.php` (Sub-project 3) dan `KalenderKerjaSdmResolver.php` (Sub-project 2) **TIDAK BOLEH diubah sama sekali** oleh item ini — sama seperti aturan yang sudah diterapkan 2x sebelumnya.

## 6. RBAC

TIDAK ADA permission baru. Semua endpoint kelola `jenis_shift`/`penugasan_shift` pakai `$this->authorize('kehadiran-sdm.kelola-konfigurasi')`. Gerbang baris `jenis_shift.lembaga_id = null` (default yayasan) tetap `$request->user()->widestScopeLevel() === 'yayasan'`, pola SAMA seperti seluruh domain Sdm.

## 7. UI

Tab ke-4 "Shift Bergilir" di halaman `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php` yang sudah ada (4 tab total: Metode & Titik Absen, Kalender Kerja, Attendance Policy, Shift Bergilir). Isi: daftar `jenis_shift` (nasional dahulu, lalu lembaga aktif) dengan CRUD sederhana; daftar `penugasan_shift` aktif/mendatang untuk lembaga aktif (pilih pegawai via dropdown searchable — pegawai bisa banyak, WAJIB Tom Select mengikuti pola pemilih pegawai yang sudah ada di form input manual Sub-project 1, BUKAN native select polos), pilih `jenis_shift`, tanggal mulai, tanggal selesai opsional, hari kerja opsional (checklist).

Query listing `jenis_shift` WAJIB pola bypass yang SAMA (`withoutGlobalScope(TenantScope::class)`, baca nasional+lembaga bareng — pola established 3x). Query listing `penugasan_shift` TIDAK butuh bypass — `lembaga_id` selalu terisi (§3.2), `TenantScope` otomatis bekerja benar tanpa pengecualian apapun.

## 8. Yang TIDAK Berubah / Di Luar Cakupan

- Rotasi otomatis (sistem gonta-ganti shift sendiri tanpa input ulang admin) — item terpisah berikutnya kalau dibutuhkan, TIDAK dibangun di sini.
- `KalenderKerjaSdmResolver.php`, `AttendancePolicyResolver.php` — TIDAK disentuh sama sekali (§5).
- Izin/cuti berjenjang — tetap Sub-project 4, urutan alokasi tidak berubah, belum dikerjakan.
- Payroll berbasis shift (lembur malam, dst) — kolom/fondasi cukup untuk kebutuhan attendance, TIDAK ADA integrasi payroll aktual.
- Validasi "pegawai ini kompeten/berwenang pakai jenis_shift tertentu" (mis. cuma satpam yang boleh dapat shift malam) — TIDAK dibangun, admin bebas menugaskan `jenis_shift` manapun ke pegawai manapun (kepercayaan penuh ke admin, konsisten filosofi modul ini yang tidak membatasi kombinasi berdasarkan kategori).

## 9. Testing

- Test `PenugasanShift` validasi overlap: 2 penugasan tumpang tindih untuk pegawai sama ditolak (termasuk kasus `tanggal_selesai` null di salah satu/kedua sisi); penugasan untuk pegawai BEDA dengan rentang sama tidak masalah; penugasan berurutan tanpa tumpang tindih (`tanggal_selesai` A = H-1 dari `tanggal_mulai` B) diterima.
- Test `ShiftAwareAttendanceResolver::resolveLibur()`: shift aktif tanpa `hari_kerja` override → semua hari dalam rentang kerja; shift aktif DENGAN `hari_kerja` override → hari di luar list jadi libur; tidak ada shift aktif → delegasi penuh ke `AttendancePolicyResolver` (hasil identik seolah resolver baru ini tidak ada).
- Test `ShiftAwareAttendanceResolver::resolveJamKerjaEfektif()`: shift aktif + Policy ada → toleransi dari Policy; shift aktif TANPA Policy sama sekali → toleransi 0 (BUKAN null/skip); tidak ada shift, Policy ada → sama seperti Sub-project 3; tidak ada shift, Policy tidak ada → `null` (fail-safe tidak berubah).
- Test integrasi ke `AttendanceRecordAggregator`: pegawai dengan shift + tanpa Policy, masuk telat 10 menit dari jam shift → `is_late = true`, `late_minutes = 10` (toleransi 0 default terbukti dipakai).
- Test regresi: SEMUA test existing `RecordManualAttendanceActionTest`, `ScanQrAttendanceActionTest`, `AttendanceRecordAggregatorLateDetectionTest`, `TandaiAlpaOtomatisSdmTest` (Sub-project 1/2/3) tetap hijau tanpa perubahan hasil untuk pegawai TANPA penugasan shift.
- Test regresi tenant-isolation untuk `jenis_shift` (pola sama seperti `KalenderKerjaSdmTenantIsolationTest`/`AttendancePolicyTenantIsolationTest`).
- Test RBAC gerbang nasional `jenis_shift`.
- Full suite HANYA di task terakhir plan implementasi, minta izin user dulu.

## 10. Asumsi

- Baseline: commit `69a96e2` di branch `sdm-v1` (handoff log Sub-project 3) saat spec ini ditulis. Plan implementasi WAJIB verifikasi ulang isi `AttendancePolicyResolver.php`, `RecordManualAttendanceAction.php`, `ScanQrAttendanceAction.php`, `AttendanceRecordAggregator.php`, `TandaiAlpaOtomatisSdm.php`, `AttendanceConfigurationController.php`, `konfigurasi.blade.php` kalau ada commit baru masuk sebelum eksekusi.
- Tidak ada hardcode nama kategori pegawai apapun (mis. "satpam") di kode manapun — `penugasan_shift` berlaku generik untuk `Guru`/`Karyawan` manapun via relasi polymorphic, konsisten prinsip yang sudah dipegang sejak Sub-project 1.
