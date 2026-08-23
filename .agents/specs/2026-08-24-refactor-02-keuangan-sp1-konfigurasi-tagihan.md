# Spec: Migrasi Domain Keuangan — Sub-project 1: Konfigurasi & Generasi Tagihan

**Tanggal:** 24 Agustus 2026
**Branch:** `refactor-v1`
**Terkait:** `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` (roadmap induk, Keuangan prioritas #3), `.agents/skills/laravel-feature-standard/SKILL.md`, `.agents/specs/2026-08-23-refactor-01-data-induk-sempit.md` (preseden pola & rigor yang diikuti)

## 1. Latar Belakang

Modul Keuangan (billing, pembayaran, cicilan, virtual account) belum pernah dimigrasi ke `app/Domains/*`. Audit skala nyata (24 Agustus 2026) menemukan modul ini jauh lebih besar dari perkiraan roadmap awal ("8 controller, 1386 baris") — sebenarnya **13 controller (1.892 baris)**, **17 model**, dengan model inti (`Tagihan`, `Pembayaran`, `JenisTagihan`) masing-masing 15-20 konsumen — terlalu besar untuk dimigrasi sekaligus seperti Sub-project "Data Induk Sempit" sebelumnya.

**Keputusan user:** migrasi Keuangan TETAP dikerjakan (tidak ditunda seperti SPMB), tapi dipecah jadi 4 sub-project bertahap, dari risiko paling rendah ke paling tinggi:
1. **Konfigurasi & Generasi Tagihan** (spec ini) — model konfigurasi billing, belum menyentuh uang bergerak.
2. Alur Tagihan Inti + Portal Tampilan (`Tagihan` aggregate root).
3. Pembayaran & Gateway — **termasuk webhook BRI SNAP** (user eksplisit: tidak ada yang dikecualikan, semua ikut standar `laravel-feature-standard/SKILL.md` backend+frontend).
4. Wallet & Cicilan + Rekonsiliasi.

Setiap sub-project dapat spec/plan/kickoff/review sendiri-sendiri, mengikuti disiplin yang sama seperti sub-project SDM dan Data Induk Sempit sebelumnya.

## 2. Tujuan Sub-project 1

Memindahkan 8 model konfigurasi billing + controller terkait ke `app/Domains/Keuangan/*`, mengikuti persis rigor Sub-project "Data Induk Sempit": model + controller (Action/DTO) + view sekaligus, namespace controller pindah ke scope resmi, zero-behavior-change, grep konsumen nyata, deteksi gotcha referensi implisit.

## 3. Cakupan

### 3.1 Model (8, ke `app/Domains/Keuangan/Models/`)
`JenisTagihan`, `NominalTagihanJalur`, `NominalTagihanSiswa`, `JenisTagihanKeringanan`, `JenisTagihanSasaranGrup`, `JenisTagihanSasaranKriteria`, `TagihanItem`, `SkemaCicilan`.

### 3.2 Controller (1, ke `app/Http/Controllers/Lembaga/Keuangan/`)
`JenisTagihanController` (445 baris) — direfactor penuh jadi Action/DTO, sama seperti keputusan Data Induk Sempit (paket penuh: model+controller+view sekaligus, BUKAN model-dulu-baru-controller).

### 3.3 Service (2, ke `app/Domains/Keuangan/Services/`)
`JenisTagihanSasaranMatcher`, `TagihanNominalResolver` — subjek datanya model cakupan sub-project ini (§3.2 master roadmap: kepemilikan domain ditentukan subjek data, bukan siapa pemanggilnya), meski satu-satunya pemanggil nyata (`TagihanBillingGenerator`) ada di wilayah Sub-project 2. Sub-project 2 nanti import dari `Domains\Keuangan\Services` seperti pola lintas-domain yang sudah biasa.

### 3.4 Business logic tersembunyi yang diekstrak
`JenisTagihan::booted()` men-dispatch event `BillTypeActivated` saat `is_active` berubah jadi `true` — **diverifikasi hanya 1 call site nyata**: `JenisTagihanController::update()` (via `$jenisTagihan->update($data)` generik). `store()` selalu `create()`, tidak pernah memicu event `updated`. Aman diekstrak ke Action baru (`SetJenisTagihanAktifAction` atau nama serupa yang diputuskan di plan) yang melakukan update + dispatch event eksplisit — model sesudahnya HANYA `$fillable`/`casts()`/relasi, TIDAK ADA `booted()` lagi.

## 4. Yang DITUNDA ke Sub-project 2 (Bukan Cakupan Spec Ini)

- **`JenisTagihanMonitoringController`** (81 baris) — murni query/mutasi ke `Tagihan` (aggregate root), bukan ke 8 model cakupan sub-project ini. Keputusan user: ditunda ke Sub-project 2 supaya konsisten dengan subjek datanya.
- **`TagihanBillingGenerator`** (service) — subjek utamanya adalah pembuatan `Tagihan`, jelas wilayah Sub-project 2. TIDAK dipindah di sini, hanya konsumsi `JenisTagihanSasaranMatcher`/`TagihanNominalResolver` dari domain baru.
- **`TagihanGenerator`** (service BEDA nama mirip, dipakai jalur PPDB/SPMB) — sama sekali di luar cakupan Keuangan Sub-project manapun untuk saat ini, tidak disentuh.
- **`TagihanController`** (`app/Http/Controllers/Admin/TagihanController.php`) — wilayah Sub-project 2/4. TAPI file ini mengimpor `SkemaCicilan` (model cakupan sub-project ini) — perlu update `use` statement-nya sebagai bagian dari Task "update consumer" spec ini, MESKI controllernya sendiri tidak dimigrasi sekarang. Ini murni ganti 1 baris import, bukan migrasi controller itu.

## 5. Gotcha Referensi Implisit Same-Namespace (WAJIB Diperbaiki)

Ditemukan lewat grep manual `{Model}::class` di `app/Models/*.php` (metode yang sudah diperbaiki di plan Data Induk Sempit — grep di `app database tests`, bukan cuma `app/Models`). 4 file **DI LUAR cakupan sub-project ini** (model-nya tetap di `app/Models/`) mereferensikan model YANG DIPINDAH secara implisit (tanpa `use` statement) — ini WAJIB diperbaiki jadi FQCN inline:

| File (tetap di `app/Models/`) | Referensi implisit ke model yang pindah |
|---|---|
| `app/Models/BillingJobLog.php` | `JenisTagihan::class` |
| `app/Models/Tagihan.php` | `JenisTagihan::class`, `TagihanItem::class`, `SkemaCicilan::class` (3 referensi berbeda dalam 1 file) |
| `app/Models/KategoriKeringanan.php` | `JenisTagihanKeringanan::class` |
| `app/Models/Cicilan.php` | `SkemaCicilan::class` |

**Catatan penting (beda dari preseden):** ada JUGA referensi implisit ANTAR model yang SAMA-SAMA pindah ke `Domains\Keuangan\Models` sekaligus (mis. `JenisTagihanKeringanan.php` → `JenisTagihan::class`) — untuk kasus ini, referensi implisit TETAP BERFUNGSI BENAR tanpa perlu diubah, karena kedua file akan berbagi namespace baru yang sama. Plan WAJIB membedakan kasus ini secara eksplisit (verifikasi per-kasus, bukan blanket-convert semua ke FQCN) supaya tidak menambah kerja yang tidak perlu.

## 6. Keputusan Desain (hasil brainstorming)

1. **Paket penuh** — model + controller (Action/DTO) + view dipindah sekaligus, sama seperti Data Induk Sempit.
2. **Namespace controller pindah ke scope resmi**: `App\Http\Controllers\Lembaga\Keuangan\JenisTagihanController`.
3. **View pindah ke `resources/views/portals/lembaga/keuangan/jenis-tagihan/`** — 6 file (`index`, `_daftar`, `_modal-kategori-baru`, `form`, `nominal`, `monitoring/index`... **kecuali `monitoring/index.blade.php` yang ikut `JenisTagihanMonitoringController` DITUNDA ke Sub-project 2**, jadi hanya 5 file yang pindah di sub-project ini).
4. **Route name/path TIDAK berubah** — cuma `use` statement controller di `routes/admin/keuangan.php` yang diganti.
5. **Service pindah sekaligus** (`JenisTagihanSasaranMatcher`, `TagihanNominalResolver`) ke `Domains\Keuangan\Services\`.
6. **Event `BillTypeActivated` diekstrak ke Action** (bukan dibiarkan di model `booted()`), diverifikasi hanya 1 call site nyata jadi aman untuk zero-behavior-change.
7. **`newFactory()` WAJIB** untuk model yang pakai `HasFactory`: `JenisTagihan`, `NominalTagihanJalur`, `TagihanItem`, `SkemaCicilan`. **TIDAK ditambahkan** untuk yang tidak pakai: `NominalTagihanSiswa`, `JenisTagihanKeringanan`, `JenisTagihanSasaranGrup`, `JenisTagihanSasaranKriteria`.
8. **Verifikasi grep WAJIB menyisir `app database tests`** (bukan cuma `app/Models`) — pelajaran eksplisit dari review Data Induk Sempit sebelumnya, supaya tidak ada file test dengan referensi FQCN inline lama yang lolos tak terdeteksi.

## 7. Zero-Behavior-Change

Default mengikat. `resolveLembagaIdOrFail()`, `referenceData()` (termasuk `withoutGlobalScope(TenantScope::class)` yang sudah ada di query `kelasList` — dipertahankan APA ADANYA, BUKAN "diperbaiki"), `hasBillingPayload()`, `baseRules()`/`billingRules()` (termasuk validator closure pembatas 100% untuk `tipe_potongan === 'persen'`), `findDuplicateKeringanan()`, `syncBillingConfig()`, `errorResponse()` — semua logic ini dipindah APA ADANYA ke Action, TIDAK ADA perubahan perilaku, urutan validasi, atau pesan error. Kalau ditemukan celah/inkonsistensi nyata saat investigasi plan-writing, dilaporkan sebagai keputusan terpisah, TIDAK diperbaiki diam-diam.

Urutan keamanan eksplisit di `JenisTagihanMonitoringController::batalTagihan()` (cek kepemilikan SEBELUM cek status bisnis, untuk cegah kebocoran info cross-tenant) — TIDAK relevan untuk spec ini karena controller itu ditunda ke Sub-project 2, tapi dicatat di sini supaya tidak terlupa saat Sub-project 2 ditulis.

## 8. Testing

- Test scoped per task (model, service, controller) — TIDAK full suite kecuali di task terakhir dan izin eksplisit user.
- Test yang HARUS tetap lulus (nama pasti akan dikonfirmasi ulang saat plan ditulis, karena daftar di atas berbasis grep 24 Agustus 2026): seluruh test yang namanya mengandung `JenisTagihan`, `NominalTagihanJalur`, `NominalTagihanSiswa`, `JenisTagihanKeringanan`, `JenisTagihanSasaranGrup/Kriteria`, `TagihanItem`, `SkemaCicilan`, `TagihanNominalResolver`, `JenisTagihanSasaranMatcher`, `BillTypeActivated`, `Keringanan`.

## 9. Di Luar Cakupan

- `Tagihan` (aggregate root), `JenisTagihanMonitoringController`, `TagihanBillingGenerator`, `TagihanController` (migrasi penuhnya) — Sub-project 2.
- `Pembayaran`, gateway, webhook BRI SNAP — Sub-project 3.
- `Wallet`, `Cicilan`, rekonsiliasi — Sub-project 4.
- `TagihanGenerator` (service PPDB/SPMB, model berbeda) — tidak terkait migrasi domain Keuangan manapun untuk saat ini.
