# Spec: Migrasi Domain Keuangan — Sub-project 2: Alur Tagihan Inti + Portal Tampilan

**Tanggal:** 24 Agustus 2026
**Branch:** `refactor-v1`
**Terkait:** `.agents/specs/2026-08-24-refactor-02-keuangan-sp1-konfigurasi-tagihan.md` (SP1, SELESAI + direview mendalam), `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md`, `.agents/skills/laravel-feature-standard/SKILL.md`

## 1. Latar Belakang

SP1 (Konfigurasi & Generasi Tagihan) sudah selesai dan direview mendalam: 8 model, 2 service, `JenisTagihanController` sudah di `App\Domains\Keuangan\*`. Review independen SP1 menemukan dan memperbaiki 5 celah (1 di antaranya HIGH — guard tenant-isolation hilang di refactor Action, sempat lolos full test suite karena tidak ada test yang menguji jalur itu). **Pelajaran ini mengikat untuk SP2**: setiap guard keamanan/tenant-isolation di kode asli WAJIB dicatat eksplisit sebagai "harus dipertahankan persis" dan diverifikasi ulang line-by-line saat implementasi, bukan cuma dipercaya lolos dari full suite.

Estimasi cakupan SP2 di spec SP1 (`Tagihan`, `JenisTagihanMonitoringController`, `TagihanBillingGenerator`, `TagihanController`) hanya estimasi awal. Eksplorasi ulang (24 Agustus 2026) menemukan cakupan nyata lebih detail: ada 6 controller di `app/Http/Controllers/Keuangan/` yang tidak disebut spec SP1 sama sekali, dengan subjek data campur (`Tagihan`/`Pembayaran`/`Wallet`/generic), dan 1 controller (`Portal\TagihanController`) yang ternyata bukan portal siswa/ortu melainkan portal PENDAFTAR PPDB (guard `auth:portal`, satu paket rute dengan wizard SPMB) — SPMB sengaja ditunda migrasinya per keputusan sebelumnya, jadi controller itu **tetap tidak disentuh** di SP2 ini.

## 2. Tujuan

Memindahkan model `Tagihan` (aggregate root) dan `BillingJobLog`, service `TagihanBillingGenerator`, event `BillTypeActivated`, 3 listener terkait generasi tagihan, dan 4 controller (2 admin + 2 portal siswa/ortu aktif) ke `app/Domains/Keuangan/*`, mengikuti rigor SP1: model+controller (Action/DTO)+view sekaligus, zero-behavior-change, grep konsumen nyata scope `app database tests`, deteksi eksplisit guard keamanan yang wajib dipertahankan.

## 3. Cakupan

### 3.1 Model (2, ke `app/Domains/Keuangan/Models/`)
- `Tagihan` (108 baris, tabel `tagihan`) — aggregate root. Relasi ke `Pendaftaran`, `tagihable` (morphTo — `Siswa`/`Pendaftaran`), `jenisTagihan`/`item`/`skemaCicilan`/`cicilan` (sudah FQCN ke `Domains\Keuangan\Models\*` dari SP1), `pembayaran`/`pembayaranTagihan` (tetap `App\Models\*`, milik SP3, dijadikan FQCN inline). Pakai `LogsActivity` (Spatie), `logOnly(['status','total_tagihan'])`.
- `BillingJobLog` (29 baris, tabel `billing_job_logs`) — log hasil proses generate billing. Relasi `jenisTagihan()` sudah FQCN. Blast radius kecil: hanya `TagihanBillingGenerator` + 3 file test.

**Business logic yang diekstrak dari model `Tagihan`** (keputusan brainstorming, lihat §6.1): method `bisaDicicil()` dan `maksCicilan()` — murni query+kalkulasi read-only tanpa efek samping, tapi tetap melanggar aturan model-purity SKILL.md — diekstrak ke `Domains\Keuangan\Services\TagihanCicilanEligibilityService`.

### 3.2 Service (2, ke `app/Domains/Keuangan/Services/`)
- `TagihanBillingGenerator` (164 baris) — sudah pakai namespace domain baru untuk `JenisTagihan`/`JenisTagihanSasaranMatcher`/`TagihanNominalResolver` (hasil SP1), tinggal `Tagihan`/`BillingJobLog` yang ikut pindah sekarang.
- `TagihanCicilanEligibilityService` (BARU) — method `bisaDicicil(Tagihan $tagihan): bool` dan `maksCicilan(Tagihan $tagihan): ?int`, isi persis method yang diekstrak dari model.

### 3.3 Event & Listener (ke `app/Domains/Keuangan/Events/` dan `.../Listeners/`)
- `BillTypeActivated` (event, milik Keuangan sendiri) — pindah.
- `GenerateTagihanForActivatedBillType` (listener, reaksi ke `BillTypeActivated` yang JUGA pindah) — pindah.
- `GenerateTagihanForNewStudent` (listener, reaksi ke `StudentCreated` — event ini TETAP di `app/Events/`, bukan milik Keuangan) — pindah (subjek reaksinya = generate Tagihan).
- `GenerateTagihanForUpdatedClass` (listener, reaksi ke `StudentUpdatedClass` — event ini TETAP di `app/Events/`) — pindah.
- Registrasi listener pakai auto-discovery Laravel (type-hint `handle(EventType $event)`, tidak ada `EventServiceProvider::$listen` manual, dikonfirmasi grep di `app/` dan `bootstrap/`) — **tidak ada file registrasi terpisah yang perlu diupdate.**

### 3.4 Controller (4, direfactor Action/DTO + pindah namespace)

| Controller lama | Baris | Controller baru |
|---|---|---|
| `Admin\JenisTagihanMonitoringController` | 81 | `Lembaga\Keuangan\JenisTagihanMonitoringController` |
| `Admin\TagihanController` (minus `buatSusulan`, lihat §3.5) | 187→~155 | `Lembaga\Keuangan\TagihanController` |
| `Portal\TagihanController` (portal PENDAFTAR PPDB) | 96 | **TIDAK dimigrasi** — lihat §4 |
| `Keuangan\TagihanController` (portal siswa/ortu aktif) | 39 | `Portal\Keuangan\TagihanController` |

### 3.5 Diekstrak keluar domain Keuangan (BARU, keputusan brainstorming §6.3)

`Admin\TagihanController::buatSusulan()` (baris 36-54) — murni proses PPDB susulan lewat `TagihanGenerator` (bukan `TagihanBillingGenerator`), terdaftar di `routes/admin/spmb.php` bukan `routes/admin/keuangan.php`. Secara bisnis milik SPMB, bukan Keuangan. **Diekstrak ke controller baru `App\Http\Controllers\Admin\TagihanSusulanController`** (tetap flat di bawah `Admin\`, konsisten dengan konvensi SPMB-admin yang sengaja belum dimigrasi — lihat `GelombangPpdbController`, `JalurPpdbController`, dst yang juga flat di `Admin\`). Zero-behavior-change: method dan route dipindah apa adanya, TIDAK diekstrak jadi Action baru (business logic-nya bukan milik Keuangan).

### 3.6 View

| View lama | View baru |
|---|---|
| `resources/views/admin/jenis-tagihan/monitoring/index.blade.php` | `resources/views/portals/lembaga/keuangan/jenis-tagihan/monitoring/index.blade.php` |
| `resources/views/admin/tagihan/index.blade.php` | `resources/views/portals/lembaga/keuangan/tagihan/index.blade.php` |
| `resources/views/keuangan/tagihan/index.blade.php` | `resources/views/portals/portal/keuangan/tagihan/index.blade.php` |

Konvensi `portals/<scope-lowercase>/<domain>/` sudah established dari SP1 dan migrasi SDM/Akademik sebelumnya (`portals/lembaga/`, `portals/guru/`, `portals/yayasan/`) — `portals/portal/` adalah perluasan mekanis pola yang sama untuk scope `Portal`.

`resources/views/keuangan/tanpa-anak.blade.php` — dipakai bersama oleh `Keuangan\TagihanController` (pindah) DAN `Keuangan\DashboardController`/`RiwayatController`/`CheckoutController` (TIDAK pindah, SP3/SP4) — **TIDAK dipindah** di SP2 (shared fallback view lintas-SP, dipindah nanti saat SP terakhir yang menyentuhnya selesai, sama seperti keputusan `Keuangan\DashboardController` sendiri).

### 3.7 Command (TETAP di `app/Console/Commands/`, hanya `use` diupdate)

Console Command bukan bagian struktur `Domains/` per SKILL.md (§ Prinsip Utama: Domain harus reusable dari Web/API/CLI/Queue/Scheduler, bukan CLI yang pindah ke Domain). `GenerateTagihanHarian.php`, `KirimDueReminderTagihan.php`, `ProsesTagihan.php` tetap di lokasi asal, hanya `use` statement ke `Tagihan`/`BillingJobLog`/`TagihanBillingGenerator` yang diupdate ke namespace baru.

## 4. Yang TIDAK Disentuh (Di Luar Cakupan SP2)

- **`Portal\TagihanController`** (96 baris) — dikonfirmasi ini portal PENDAFTAR PPDB (guard `auth:portal`, terikat `pendaftaran_id`, satu grup rute dengan wizard SPMB di `routes/portal.php`), BUKAN portal siswa/ortu aktif. Keputusan brainstorming eksplisit: dianggap bagian alur SPMB yang sengaja ditunda, tetap di lokasi/namespace asal apa adanya. **TAPI** baris 47 (`$tagihan->maksCicilan()`) tetap perlu disentuh sebagai cross-scope touch (lihat §5) karena method itu diekstrak dari model yang IKUT pindah.
- `Keuangan\DashboardController` (66 baris) — campur 3 domain (Tagihan/SP2, Wallet/SP4, VA via `PaymentService`/SP3), ditunda ke SP4 (SP terakhir yang menyentuhnya). Cross-scope touch: `use App\Models\Tagihan;` (baris 9) diupdate.
- `Keuangan\RiwayatController` (93 baris), `Keuangan\CheckoutController` (269 baris) — subjek data utama `Pembayaran`, bukan `Tagihan` → SP3. `CheckoutController` (baris 12) punya `use App\Models\Tagihan;` — cross-scope touch. `RiwayatController` hanya referensi implisit via string relasi (`pembayaranTagihan.tagihan.jenisTagihan`), TIDAK perlu disentuh (relasi resolve lewat method di model `PembayaranTagihan`/`Pembayaran`, bukan referensi langsung ke class `Tagihan`).
- `Keuangan\NotifikasiController` (41 baris) — generic inbox notifikasi, tidak menyentuh `Tagihan`/`Pembayaran` sama sekali. Di luar SP manapun.
- `PembayaranService`, `TagihanGenerator` (PPDB), `Cicilan`, `Pembayaran`, `PembayaranTagihan`, `SystemSetting` — tetap di `app/Services`/`app/Models`, TIDAK pindah. Hanya `use App\Models\Tagihan;`/`use App\Models\BillingJobLog;`-nya yang diupdate kalau ada (lihat §5, WAJIB grep ulang saat plan-writing — daftar 91 file hasil grep `use App\Models\Tagihan;` per 24 Agustus 2026 sudah termasuk file-file ini, TAPI hanya `use`-nya yang berubah, bukan isinya).
- 5 Notification lain di `app/Notifications/Finance/` selain `TagihanDiterbitkanNotification`/`DueReminderNotification` — subjeknya `Pembayaran`/wallet, SP3/SP4.
- `app/Notifications/Finance/TagihanDiterbitkanNotification.php`, `DueReminderNotification.php` — **TIDAK pindah** meski subjeknya `Tagihan` murni (keputusan brainstorming §6.4: SKILL.md tidak menyediakan folder `Notifications/` resmi di struktur Domain, folder `app/Notifications/Finance/` sudah jadi konvensi lintas-SP untuk semua notifikasi Keuangan). Isinya (constructor type-hint `Tagihan $tagihan`) otomatis resolve ke namespace baru tanpa perlu disentuh KECUALI ada `use App\Models\Tagihan;` eksplisit — WAJIB dicek saat implementasi.
- `TagihanGenerator` (73 baris, PPDB/SPMB) — dikonfirmasi tidak overlap `TagihanBillingGenerator`, tetap terpisah, tidak disentuh (kecuali `use`-nya ke `Tagihan`/`TagihanItem` kalau ada — cross-scope touch).
- `admin/spmb-pendaftaran/show.blade.php` (baris 301, `$tagihan->bisaDicicil()`) — cross-scope touch (lihat §5), controller/view SPMB lainnya TIDAK disentuh.

## 5. Cross-Scope Touch (file TIDAK migrasi tapi WAJIB disentuh minimal)

| File | Alasan | Perubahan |
|---|---|---|
| `app/Http/Controllers/Keuangan/DashboardController.php:9` | `use App\Models\Tagihan;` | → `use App\Domains\Keuangan\Models\Tagihan;` |
| `app/Http/Controllers/Keuangan/CheckoutController.php:12` | `use App\Models\Tagihan;` | → `use App\Domains\Keuangan\Models\Tagihan;` |
| `app/Http/Controllers/Portal/TagihanController.php:47` | `$tagihan->maksCicilan()` dihapus dari model | → `app(\App\Domains\Keuangan\Services\TagihanCicilanEligibilityService::class)->maksCicilan($tagihan)` |
| `resources/views/admin/spmb-pendaftaran/show.blade.php:301` | `$tagihan->bisaDicicil()` dihapus dari model | → `app(\App\Domains\Keuangan\Services\TagihanCicilanEligibilityService::class)->bisaDicicil($tagihan)` |
| `resources/views/portal/tagihan/index.blade.php:59` | `$tagihan->bisaDicicil()` dihapus dari model | → sama seperti di atas (view ini ikut `Portal\TagihanController` yang TIDAK pindah, tapi tetap perlu disentuh karena manggil method yang dihapus dari model) |
| Seluruh 91 file hasil grep `use App\Models\Tagihan;` (WAJIB grep ulang saat plan-writing, scope `app database tests`) | Model pindah namespace | `use App\Models\Tagihan;` → `use App\Domains\Keuangan\Models\Tagihan;` — **hanya baris `use`, isi method TIDAK disentuh** kecuali file itu memang bagian §3 (migrasi controller/service) |
| 5 file hasil grep `BillingJobLog` (3 test + `TagihanBillingGenerator` + model itu sendiri) | Model pindah namespace | idem |

## 6. Keputusan Desain (hasil brainstorming)

1. **Batas folder `app/Http/Controllers/Keuangan/`**: ikuti subjek data per-controller, bukan "satu folder = satu paket". `TagihanController` (Tagihan-only) → SP2. `RiwayatController`/`CheckoutController` (Pembayaran-centric) → SP3. `DashboardController` (campur 3 domain) → ditunda ke SP4. `NotifikasiController` (generic) → di luar SP manapun.
2. **`Tagihan::bisaDicicil()`/`maksCicilan()`** — meski read-only tanpa efek samping (beda kategori dari `booted()` SP1 yang men-dispatch event), tetap diekstrak ke `TagihanCicilanEligibilityService` supaya model 100% murni sesuai SKILL.md, tanpa pengecualian.
3. **`Portal\TagihanController` dikoreksi dari estimasi awal** — bukan portal siswa/ortu, tapi portal pendaftar PPDB (guard `auth:portal`). Dianggap bagian SPMB yang ditunda, TIDAK dimigrasi. Hanya 1 baris cross-scope touch (§5) yang disentuh.
4. **`buatSusulan()` diekstrak ke controller terpisah** (`Admin\TagihanSusulanController`, flat, di luar domain Keuangan) supaya `TagihanController` hasil migrasi 100% murni Keuangan tanpa jejak SPMB.
5. **Namespace portal siswa/ortu aktif**: `Portal\Keuangan\TagihanController` (Scope=`Portal`, Domain=`Keuangan`, konsisten pola `Lembaga\Keuangan\` dari SP1).
6. **Event+Listener milik Keuangan pindah ke `Domains\Keuangan\Events`/`Listeners`, Notification tetap** di `app/Notifications/Finance/` (SKILL.md tidak menyediakan folder Notifications/ resmi, folder itu sudah jadi konvensi lintas-SP).
7. **Console Command tetap di `app/Console/Commands/`** — bukan bagian struktur Domain per SKILL.md, cukup update `use` statement.
8. **View portal siswa/ortu**: `resources/views/portals/portal/keuangan/tagihan/` — perluasan mekanis pola `portals/<scope>/<domain>/` yang sudah established.

## 7. Guard Keamanan yang WAJIB Dipertahankan Persis (pelajaran SP1)

1. **`JenisTagihanMonitoringController::batalTagihan()`** — urutan cek: kepemilikan (`$tagihan->jenis_tagihan_id !== $jenisTagihan->id` → 403) SEBELUM cek status bisnis (`$tagihan->status !== 'belum_bayar'` → 422). Komentar asli eksplisit menjelaskan alasan: mencegah kebocoran info status tagihan cross-tenant lewat perbedaan kode status response. **Urutan ini TIDAK BOLEH dibalik** saat diekstrak ke Action.
2. **`Admin\TagihanController::lembagaId()`** — dipakai di 4 method (`buatSkemaCicilan`, `simpanNominalCicilan`, `catatManualTagihan`, `catatManualCicilan`) via `abort_unless($tagihan->pendaftaran->lembaga_id === $this->lembagaId($request), 404)` atau variannya lewat relasi (`skemaCicilan->tagihan`/`cicilan->skemaCicilan->tagihan`). `Tagihan` TIDAK punya `lembaga_id` langsung (diturunkan transitif via `pendaftaran_id`) — pola ini WAJIB dipertahankan persis di tiap Action yang diekstrak, TIDAK boleh disederhanakan/dihilangkan dengan asumsi keliru bahwa `Tagihan` bisa di-scope otomatis.
3. **`Portal\TagihanController::pastikanMilikSendiri()`** — meski controller ini TIDAK dimigrasi, dicatat di sini sebagai referensi pola yang sama (`abort_unless($tagihan->pendaftaran->akun_pendaftar_id === Auth::guard('portal')->id(), 404)`) untuk perbandingan kalau nanti SPMB akhirnya dimigrasi.

## 8. Zero-Behavior-Change Lain

- `Keuangan\TagihanController::index()` — `->withoutGlobalScope(TenantScope::class)` pada relasi `jenisTagihan` WAJIB dipertahankan persis.
- `Admin\TagihanController::data()` — logic sorting/filtering/pagination DataTables-style dipindah apa adanya, termasuk whitelist kolom sortable (`created_at`, `total_tagihan`) dan clamp `per_page` (1-100).
- `TagihanBillingGenerator::generateForSiswa()` — pengiriman notifikasi dibungkus try/catch dengan `Log::error()` (kegagalan kirim notif TIDAK boleh menggagalkan transaksi generate tagihan) — dipertahankan persis.
- `Tagihan::maksCicilan()` — logic ambil `maks_cicilan` TERKECIL di antara item yang `bisa_dicicil=true` (bukan rata-rata/pertama) — dipertahankan persis saat diekstrak ke service.

## 9. Testing

- Test scoped per task, full suite HANYA di task terakhir dengan izin eksplisit user (sama seperti SP1).
- Grep verifikasi WAJIB scope `app database tests` (bukan cuma `app/Models`) — pelajaran Data Induk Sempit yang berulang relevan.
- Test yang WAJIB tetap lulus (nama pasti dikonfirmasi ulang saat plan-writing): seluruh test yang namanya mengandung `Tagihan`, `BillingJobLog`, `JenisTagihanMonitoring`, `TagihanBillingGenerator`, `TagihanSusulan`, `GenerateTagihanHarian`, `KirimDueReminderTagihan`, `ProsesTagihan`, `StudentBillingEvents`, `TagihanPolymorphic`, `TagihanController`.
- **Test baru wajib ditambahkan** (pelajaran langsung dari celah HIGH SP1 yang lolos full suite): minimal 1 test yang secara eksplisit memverifikasi guard §7.1 (403 untuk tagihan milik jenis tagihan lain) dan §7.2 (404 untuk tagihan lintas-lembaga) TETAP ada setelah diekstrak ke Action — bukan cuma "test lama masih lulus", tapi test yang secara eksplisit menyerang jalur itu, supaya kalau guard hilang lagi, test langsung merah.

## 10. Di Luar Cakupan (Sub-project Lain)

- `Pembayaran`, `PembayaranTagihan`, gateway (`Services/Finance/*`), webhook BRI SNAP, `Keuangan\RiwayatController`, `Keuangan\CheckoutController` — SP3.
- `Wallet`, `Cicilan`, `SkemaCicilan`'s child `cicilan()` relation consumer, rekonsiliasi, `Keuangan\DashboardController` — SP4.
- SPMB/PPDB (`TagihanGenerator`, `Portal\TagihanController`, `Admin\TagihanSusulanController` baru, wizard SPMB) — ditunda indefinitely per keputusan sebelumnya, di luar migrasi domain Keuangan manapun.
