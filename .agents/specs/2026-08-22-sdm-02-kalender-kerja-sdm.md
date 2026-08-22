# Kehadiran SDM — Sub-project 2: Kalender Kerja SDM — Spec

## 1. Latar Belakang

Sub-project 1 (fondasi + admin manual + QR statis) sudah SELESAI diimplementasi, direview, dan diverifikasi manual di branch `sdm-v1` (lihat `.agents/specs/2026-08-22-sdm-01-fondasi-kehadiran-admin-qr.md`). Modul Kehadiran SDM saat ini bisa mencatat kehadiran (manual/QR) TANPA mengetahui apakah tanggal yang dicatat itu hari kerja atau hari libur bagi pegawai — spec Sub-project 1 §8 secara eksplisit menyatakan ini di luar cakupannya dan menunggu Sub-project 2.

Sub-project 2 membangun **kalender kerja khusus SDM** — SENGAJA independen dari kalender akademik yang sudah ada (`App\Domains\Akademik\Models\KalenderAkademik` + `KalenderAkademikResolver`, dipakai jadwal pelajaran murid). Keduanya menjawab pertanyaan berbeda: kalender akademik = "apakah hari ini hari belajar bagi siswa?"; kalender kerja SDM = "apakah hari ini hari kerja bagi pegawai (guru, TU, satpam, dst)?" — dua jawaban ini TIDAK selalu sama untuk tanggal yang sama (contoh: libur semester siswa, staf TU tetap masuk kerja normal).

## 2. Keputusan Desain (hasil brainstorming, ringkas)

| Topik | Keputusan | Alasan Singkat |
|---|---|---|
| Struktur kalender | Yayasan sediakan default nasional (`lembaga_id` null) + lembaga override | Konsisten pola `AttendanceMethodConfiguration` Sub-project 1 & `KalenderAkademik` yang sudah ada |
| Tenant isolation | `BelongsToTenant` (BUKAN scope manual seperti `KalenderAkademik`) | Proteksi otomatis `TenantScope` untuk semua query masa depan — riwayat proyek ini sering kebobolan gara-gara lupa scope manual (10x di modul Presensi & Asesmen Akademik) |
| Hari kerja mingguan | Kolom BARU `hari_libur_mingguan_sdm` di `Lembaga` (list negatif, isi hari LIBUR) — TERPISAH TOTAL dari `hari_libur_mingguan` akademik | Reuse kolom akademik akan mengubah jadwal pelajaran murid tanpa disengaja. List negatif dipilih (bukan `hari_kerja_sdm` list positif) karena fail-safe: kolom kosong = tidak ada libur = semua hari kerja (aman), bukan sebaliknya yang berisiko auto-alpa semua orang tiap hari kalau lupa fallback |
| Struktur kode resolver | `KalenderKerjaSdmResolver` ditulis terpisah TOTAL dari `KalenderAkademikResolver`, TIDAK ada base class/trait bersama | Domain Akademik & SDM harus tetap independen, tidak saling bergantung lewat kode bersama |
| Shift kerja / jadwal per-pegawai (satpam, dinas luar) | DITUNDA ke Sub-project 3 (Attendance Policy per jenis_ptk/jenis_karyawan_id) | Kalender = jawaban SERAGAM per lembaga; shift/jam-kerja per-peran adalah konsep Policy, bukan Calendar — di luar cakupan sub-project ini. Konsekuensi diketahui: lihat §8 |
| Efek ke input kehadiran | (a) Tolak input di hari libur (bisa di-override admin utk manual, TIDAK bisa di-override utk QR); (b) auto-tandai Alpa harian utk hari kerja tanpa event | Kedua efek disepakati eksplisit oleh user |
| Fitur salin tanggal | Tombol "Salin dari Kalender Akademik" (snapshot sekali klik, BUKAN live-sync), level nasional saja | Kurangi input berulang tanggal libur nasional resmi (Idul Fitri, Natal, dst) tanpa menggabung sistem |
| RBAC | Reuse permission `kehadiran-sdm.kelola-konfigurasi` dari Sub-project 1, TANPA permission baru | Gerbang entri nasional (`lembaga_id` null) berdasar SCOPE (`widestScopeLevel() === 'yayasan'`), BUKAN role tertentu yang di-hardcode |

## 3. Struktur Data

### 3.1 Kolom baru di `Lembaga`

`hari_libur_mingguan_sdm` — JSON array integer (0=Minggu ... 6=Sabtu, konvensi Carbon `dayOfWeek` yang sama dipakai `hari_libur_mingguan` akademik), default `JSON_ARRAY(0)` (Minggu libur), TERPISAH dari `hari_libur_mingguan` yang sudah ada. Migrasi WAJIB pakai `DB::raw('(JSON_ARRAY(0))')` sebagai default, persis pola migrasi `hari_libur_mingguan` (`database/migrations/2026_07_12_090702_create_lembaga_table.php:64`).

### 3.2 Tabel baru `kalender_kerja_sdm`

Replika struktural `kalender_akademik` (`app/Domains/Akademik/Models/KalenderAkademik.php`), domain `App\Domains\Sdm\Models\KalenderKerjaSdm`, DENGAN `BelongsToTenant` (beda dari `KalenderAkademik` yang scope manual — lihat §2 alasan).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `yayasan_id` | FK `yayasan` | selalu terisi |
| `lembaga_id` | FK `lembaga`, nullable | null = entri nasional/yayasan; terisi = entri khusus lembaga tsb |
| `tanggal` | date | |
| `tanggal_selesai` | date, nullable | null = entri 1 hari (tanggal itu saja) |
| `nama` | string | |
| `tipe` | string | enum `TipeKalenderKerjaSdm` (`libur`, `kerja` — `kerja` = override "tetap masuk" di tengah rentang libur, sama makna dengan `TipeKalenderAkademik::Kerja`) |
| `keterangan` | text, nullable | |
| `timestamps` | | |

### 3.3 Enum `App\Domains\Sdm\Enums\TipeKalenderKerjaSdm`

`Libur = 'libur'`, `Kerja = 'kerja'` — struktur identik `App\Enums\TipeKalenderAkademik` tapi namespace & class terpisah (§2).

### 3.4 Tambahan ke `App\Domains\Sdm\Enums\AttendanceMethod` (dari Sub-project 1)

Tambah 1 case baru: `System = 'system'` — dipakai HANYA oleh command auto-tandai-alpa (§5.2), merepresentasikan event yang dibuat sistem tanpa aktor manusia (`dicatat_oleh_user_id = null`). Method existing (`Admin`, `Qr`) TIDAK berubah.

## 4. `KalenderKerjaSdmResolver`

`App\Domains\Sdm\Services\KalenderKerjaSdmResolver::resolve(Lembaga $lembaga, CarbonInterface $tanggal): array{libur: bool, alasan: string}` — logika IDENTIK urutan `KalenderAkademikResolver` (lihat `app/Domains/Akademik/Services/KalenderAkademikResolver.php`) tapi terhadap tabel & kolom Sub-project 2:

1. Cek entri `KalenderKerjaSdm` milik lembaga (`lembaga_id = $lembaga->id`) yang rentang tanggalnya cocok dengan `$tanggal` → kalau ada, pakai `tipe`-nya.
2. Kalau tidak ada, cek entri nasional (`lembaga_id` null, `yayasan_id = $lembaga->yayasan_id`) yang cocok → pakai `tipe`-nya.
3. Kalau tidak ada juga, cek `in_array($tanggal->dayOfWeek, $lembaga->hari_libur_mingguan_sdm ?? [], true)` → libur mingguan.
4. Default: hari kerja efektif.

**Kedua query di resolver (langkah 1 DAN langkah 2) WAJIB `withoutGlobalScope(TenantScope::class)` secara eksplisit**, lalu andalkan filter manual (`lembaga_id`/`yayasan_id`) yang sudah ditulis di atas — JANGAN biarkan `TenantScope` otomatis ikut menambah filter. Alasannya ada 2 skenario pemanggilan resolver yang keduanya harus tetap benar:

1. **Dari command terjadwal** (`sdm:tandai-alpa-otomatis`, §5.2) — tidak ada aktor login, `TenantScope` no-op (tidak memfilter apapun), jadi bypass ini tidak berpengaruh — aman.
2. **Dari request HTTP langsung** (`RecordManualAttendanceAction`/`ScanQrAttendanceAction` memanggil resolver saat admin_sdm mencatat kehadiran, §5.1) — ADA aktor login dengan `scope_level: lembaga`. Tanpa bypass, query langkah 2 (`whereNull('lembaga_id')`) akan KETABRAK `TenantScope` yang memaksa tambahan `WHERE lembaga_id = actingUser->lembaga_id` — kombinasi `lembaga_id IS NULL AND lembaga_id = X` mustahil dipenuhi, SELALU nol baris. Akibatnya: entri libur nasional (mis. cuti bersama Idul Fitri) jadi TIDAK PERNAH terdeteksi resolver untuk admin_sdm biasa, dan sistem diam-diam mengizinkan pencatatan kehadiran di hari libur nasional tanpa penolakan sama sekali — bug tenant-scope class yang sama dengan Task 7 Sub-project 1, tapi kali ini bikin validasi libur nasional bocor total, bukan cuma UI yang tidak menampilkan baris.

## 5. Efek ke Attendance

### 5.1 Penolakan Input di Hari Libur

**`RecordManualAttendanceData`** (Sub-project 1) dapat property baru: `overrideHariLibur: bool = false`.

**`RecordManualAttendanceAction::execute()`**: sebelum membuat `AttendanceEvent`, panggil `KalenderKerjaSdmResolver::resolve($pegawai->lembaga, $data->waktu)`. Kalau `libur === true` DAN `$data->overrideHariLibur === false` → lempar `AttendanceOnHolidayException` (pesan berisi `alasan` dari resolver, mis. "Hari ini libur: Cuti Bersama Idul Fitri"). Controller (`AttendanceController::store`) menangkap exception ini, tampilkan error ke form DENGAN opsi baru: checkbox "Tetap catat meski hari libur" yang mengisi `overrideHariLibur = true` pada submit ulang.

**`ScanQrAttendanceAction::execute()`**: cek yang SAMA (resolver dipanggil dengan `now()`), TANPA parameter override — kalau libur, SELALU lempar `AttendanceOnHolidayException`, tidak ada jalur bypass lewat scan QR sama sekali (sesuai keputusan §2).

### 5.2 Command Terjadwal `sdm:tandai-alpa-otomatis`

Class baru `App\Console\Commands\TandaiAlpaOtomatisSdm`, signature `sdm:tandai-alpa-otomatis`, didaftarkan `Schedule::command('sdm:tandai-alpa-otomatis')->dailyAt('01:00')` di `routes/console.php` (pola identik `TandaiTugasTerlewat` yang sudah ada di baris yang sama).

Logika (jalan TANPA aktor login, `auth()->id()` null → `TenantScope` tidak memfilter apapun, command melihat semua data lintas tenant secara alami — SESUAI, karena ini job sistem, BUKAN request user):

1. `$tanggal = now()->subDay()->toImmutable()` (H-1).
2. Untuk setiap `Lembaga`: resolve `KalenderKerjaSdmResolver::resolve($lembaga, $tanggal)`. Kalau libur → lewati lembaga ini sepenuhnya.
3. Kalau hari kerja: iterasi `Guru::where('lembaga_id', $lembaga->id)->where('status_aktif', 'aktif')->get()` UNION `Karyawan::where('lembaga_id', $lembaga->id)->where('status_aktif', 'aktif')->get()`.
4. Untuk tiap pegawai: kalau `AttendanceRecord::where('pegawai_type', ...)->where('pegawai_id', ...)->whereDate('tanggal', $tanggal)->doesntExist()` → buat `AttendanceEvent` (`method: AttendanceMethod::System`, `arah: 'masuk'`, `status: AttendanceStatus::Alpa`, `waktu: $tanggal->setTime(23,59)`, `dicatat_oleh_user_id: null`, `catatan: 'Ditandai otomatis oleh sistem — tidak ada aktivitas kehadiran pada hari kerja ini.'`) lalu panggil `AttendanceRecordAggregator::sync($pegawai, $tanggal)`.
5. Idempotent: menjalankan command 2x untuk tanggal yang sama TIDAK membuat duplikat — cek `AttendanceRecord::...doesntExist()` di poin 4 sudah mencegahnya (begitu 1 event Alpa dibuat, record hari itu langsung ada, run kedua akan skip pegawai itu).

## 6. RBAC (Reuse Total, Tanpa Permission Baru)

Semua endpoint kelola kalender kerja SDM (toggle hari libur mingguan, CRUD entri kalender, salin dari akademik) memakai `$this->authorize('kehadiran-sdm.kelola-konfigurasi')` — permission yang SUDAH ADA dari Sub-project 1, sudah dipegang role `admin_sdm` dan otomatis `yayasan_super_admin`.

Gerbang tambahan KHUSUS untuk entri `lembaga_id = null` (nasional): controller WAJIB cek `$request->user()->widestScopeLevel() === 'yayasan'` secara eksplisit sebelum mengizinkan create/update/delete entri nasional — BUKAN cek nama role manapun (`hasRole(...)` dilarang total, konsisten temuan audit RBAC sebelumnya). Entri per-lembaga cukup permission `kehadiran-sdm.kelola-konfigurasi` + `resolveLembagaId()` mengembalikan lembaga non-null (pola sama `AttendanceConfigurationController` Sub-project 1).

## 7. UI

Tab baru **"Kalender Kerja"** ditambahkan ke halaman `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php` yang SUDAH ADA dari Sub-project 1 (di sana sudah ada tab implisit "Metode Absensi" + "Titik Absen" sebagai section berurutan — Sub-project 2 menjadikannya benar-benar bertab dengan Alpine `x-show`/`activeTab`, ATAU tetap section berurutan kalau lebih sederhana — keputusan detail struktur tab diserahkan ke tahap penulisan plan, bukan bagian keputusan arsitektur spec ini).

Isi tab:
- Checklist 7 hari (Minggu-Sabtu) untuk `hari_libur_mingguan_sdm` — pola UI sama seperti checklist hari aktif akademik (`resources/views/portals/lembaga/akademik/pengaturan/akademik.blade.php`, WAJIB dicek isinya sebelum implementasi untuk meniru pola checkbox yang sudah ada, jangan reka baru).
- Daftar entri kalender (nasional dahulu, lalu entri lembaga aktif), dengan badge tipe (Libur/Kerja override). Query controller untuk daftar ini WAJIB pakai pola bypass yang sama seperti `AttendanceConfigurationController::index()` Sub-project 1 (`withoutGlobalScope(TenantScope::class)` + filter manual `yayasan_id` dan `(lembaga_id = aktif OR lembaga_id null)`) — tanpa itu, baris nasional tidak akan pernah muncul untuk aktor `admin_sdm` (bug yang sama seperti §4).
- Tombol tambah entri manual (modal form: nama, tanggal, tanggal_selesai opsional, tipe, keterangan).
- Tombol **"Salin dari Kalender Akademik"** (khusus level nasional, hanya tampil kalau `widestScopeLevel() === 'yayasan'`): membuka modal daftar entri `KalenderAkademik::nasional()` yang tanggal+nama-nya BELUM ada padanan di `KalenderKerjaSdm::whereNull('lembaga_id')`, admin centang mana yang mau disalin, submit → membuat baris `KalenderKerjaSdm` independen per entri tercentang (snapshot nilai `tanggal`, `tanggal_selesai`, `nama`, `tipe`→dipetakan `TipeKalenderAkademik::Libur`→`TipeKalenderKerjaSdm::Libur` dst, `keterangan`). Setelah disalin, TIDAK ada relasi lanjutan ke entri akademik asal — mengubah salah satu tidak memengaruhi yang lain (§2).

## 8. Yang TIDAK Berubah / Di Luar Cakupan Sub-project 2

- Shift kerja, jam kerja per-jenis_ptk/jenis_karyawan_id, dinas luar — Sub-project 3 (Attendance Policy). **Keterbatasan yang diketahui dan diterima**: command `sdm:tandai-alpa-otomatis` menandai Alpa berdasar kalender SERAGAM per lembaga; pegawai bershift (mis. satpam) yang seharusnya masuk di hari yang menurut kalender lembaga "libur" TIDAK akan terdeteksi bolos oleh auto-alpa (karena lembaga dianggap libur untuk semua orang). Input manual kehadirannya tetap bisa dicatat admin lewat override checkbox (§5.1) — hanya deteksi OTOMATIS-nya yang punya celah ini sampai Sub-project 3 selesai.
- Deteksi keterlambatan (`is_late`, `late_minutes`) — tetap Sub-project 3, tidak berubah dari batasan Sub-project 1.
- Approval berjenjang izin/cuti — tetap Sub-project 4, tidak berubah.
- Tidak ada perubahan pada `KalenderAkademik`, `KalenderAkademikResolver`, `JadwalPelajaranController`, atau kolom `hari_libur_mingguan` akademik — hanya DIBACA (satu kali, saat "salin") oleh fitur salin, tidak pernah ditulis.
- Live-sync antara kalender akademik dan kalender SDM TIDAK dibangun — fitur salin murni snapshot sekali klik (§2, §7).

## 9. Testing

- Test `KalenderKerjaSdmResolver`: entri lembaga override entri nasional; entri nasional override fallback mingguan; fallback mingguan bekerja kalau tidak ada entri sama sekali; rentang `tanggal`-`tanggal_selesai` inklusif di kedua ujung (mirror test yang sudah ada untuk `KalenderAkademikResolverTest`, pola serupa).
- Test tenant isolation `KalenderKerjaSdm`: admin lembaga A tidak bisa lihat/ubah entri lembaga B; entri nasional tetap terlihat aktor `scope_level: lembaga` (regression guard sama seperti Sub-project 1 Task 7 — WAJIB ada test eksplisit untuk ini, sudah pernah jadi bug nyata).
- Test gerbang nasional: `admin_sdm` (scope_level lembaga) mencoba create entri `lembaga_id = null` → ditolak (403 atau redirect error, BUKAN dari cek `hasRole`); `yayasan_super_admin` (scope_level yayasan) berhasil.
- Test `RecordManualAttendanceAction` menolak input di hari libur tanpa `overrideHariLibur`; berhasil dengan `overrideHariLibur = true`.
- Test `ScanQrAttendanceAction` SELALU menolak di hari libur, tidak ada skenario yang berhasil.
- Test command `sdm:tandai-alpa-otomatis`: membuat Alpa untuk pegawai aktif tanpa event di hari kerja H-1; TIDAK membuat Alpa untuk lembaga yang H-1-nya libur; TIDAK membuat Alpa untuk pegawai yang sudah punya record (idempotency, termasuk test run command 2x berturut-turut hasilnya sama); TIDAK membuat Alpa untuk pegawai `status_aktif != aktif`.
- Test fitur salin: entri nasional akademik yang belum ada padanannya tampil di daftar; setelah disalin, entri SDM independen (ubah entri akademik asal tidak mengubah entri SDM hasil salinan).
- Full suite HANYA di task terakhir plan implementasi, minta izin user dulu sebelum dijalankan (kebijakan testing proyek ini, tidak berubah dari Sub-project 1).

## 10. Asumsi

- Baseline: commit `1741f3b` di branch `sdm-v1` (handoff log Sub-project 1) saat spec ini ditulis. Plan implementasi WAJIB verifikasi ulang isi `AttendanceMethodConfiguration.php`, `AttendanceConfigurationController.php`, `konfigurasi.blade.php`, `RecordManualAttendanceAction.php`, `ScanQrAttendanceAction.php`, `routes/console.php` kalau ada commit baru masuk sebelum eksekusi.
- Model `KalenderAkademik` HANYA dibaca (read-only) oleh fitur salin (§7) — plan implementasi WAJIB pastikan tidak ada write path apapun ke tabel `kalender_akademik` dari domain Sdm.
- `Guru`/`Karyawan` tetap dua model terpisah (keputusan final Sub-project 1, tidak berubah) — command auto-alpa WAJIB iterasi keduanya secara eksplisit terpisah (bukan 1 query gabungan), konsisten pola `AttendanceController::create()` yang sudah memisahkan `guruList`/`karyawanList`.
