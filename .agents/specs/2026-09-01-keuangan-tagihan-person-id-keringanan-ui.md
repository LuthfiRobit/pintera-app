# Spec: Keuangan — `tagihan.person_id`, UI Assignment Keringanan, Sentralisasi PPDB_KATEGORI

- **Branch**: `keuangan-v2` (fast-forward setara `identity-v1` per 2026-09-01, commit `1efa02dd`)
- **Tanggal**: 1 September 2026
- **Konteks**: kelanjutan audit modul Keuangan yang dimulai sebelum identity-v1, dilanjutkan setelah identity-v1 selesai total. Paket ini berisi 3 perbaikan independen tapi saling melengkapi, dipilih karena semuanya sudah punya arah desain final hasil diskusi berlapis, dan #2+#3 menyentuh file yang sama sehingga efisien dikerjakan bersamaan.

---

## 1. Ringkasan 3 Perbaikan

1. **UI Assignment Keringanan** — admin sekolah saat ini TIDAK BISA menetapkan siswa mana yang punya kategori keringanan (diskon) lewat aplikasi sama sekali. Data model sudah benar; UI-nya nol.
2. **`tagihan.person_id`** — ledger keuangan satu orang terpecah antara masa "calon siswa/pendaftar" dan masa "siswa aktif" karena keduanya memakai `tagihable` polymorphic yang berbeda tanpa penghubung identitas permanen.
3. **Sentralisasi `PPDB_KATEGORI`** — konstanta yang sama didefinisikan independen di 4 tempat, plus 3 titik lain yang melakukan pengecekan serupa tanpa konstanta sama sekali — rawan drift kalau kategori PPDB baru ditambah.

## 2. Non-Goals (eksplisit di luar scope paket ini)

Semua ini SUDAH DIBAHAS dan SENGAJA ditunda — jangan dikerjakan sebagai bagian paket ini:

1. Generalisasi `JenisTagihanSasaranMatcher`/kriteria sasaran ke tipe selain Siswa (event berbayar, dst) — tunggu modul event benar-benar mulai.
2. Konversi `siswa_keringanan.siswa_id`/`nominal_tagihan_siswa.siswa_id` menjadi `person_id` — idem, tunggu kebutuhan lintas-peran nyata.
3. Penyatuan `TagihanGenerator` + `TagihanBillingGenerator` jadi satu kelas — filosofi header keduanya berbeda (satu menggabung banyak `JenisTagihan` jadi 1 `Tagihan` header, satu bikin 1 header per `JenisTagihan`); keputusan penyatuan butuh diskusi terpisah.
4. Refund/reversal (`Wallet::refund()`, `PembayaranAdjustment`) — inisiatif terpisah, gap financial integrity yang sudah dicatat tapi tidak digarap di sini.
5. Keputusan bisnis "keringanan berlaku surut atau tidak" dan "potongan digabung atau cuma terbesar yang menang" — BELUM dikonfirmasi user. Paket ini HANYA membangun UI assignment; perilaku snapshot/best-only yang sudah ada di `TagihanNominalResolver` TIDAK diubah.

## 3. Perbaikan #1 — UI Assignment Keringanan

### 3.1 Kondisi saat ini (terverifikasi)

- Route yang ada: `POST admin/kategori-keringanan` (`Lembaga\Keuangan\KategoriKeringananController`) — cuma bikin kategori (mis. "Anak Pegawai").
- **Nol** route/controller untuk assign kategori ke siswa spesifik. Satu-satunya cara membuat baris `SiswaKeringanan` adalah lewat test factory — tidak ada jalur produksi sama sekali.

### 3.2 Yang dibangun

- **`SiswaKeringananController`** (baru, `app/Http/Controllers/Lembaga/Keuangan/`), dengan method:
  - `index(Siswa $siswa)` — tampilkan daftar keringanan yang dimiliki siswa ini (aktif dan sudah kedaluwarsa).
  - `store(Request $request, Siswa $siswa)` — assign kategori keringanan baru: `kategori_keringanan_id` (required, harus milik lembaga yang sama dengan siswa — tenant check), `berlaku_dari` (required, date), `berlaku_sampai` (nullable, date, harus >= `berlaku_dari` kalau diisi).
  - `destroy(SiswaKeringanan $siswaKeringanan)` — cabut keringanan (hard delete baris `SiswaKeringanan` — bukan soft-delete, karena ini murni assignment record, bukan data finansial yang perlu audit trail permanen seperti `Tagihan`).
- **Permission baru**: `siswa-keringanan.kelola` (mengikuti konvensi `modul.aksi` yang sudah ada di seluruh app), digabung ke role yang sudah relevan (`bendahara_lembaga`, `operator_akademik` — putuskan final saat implementasi berdasarkan siapa yang sudah pegang `orang-tua.edit`/`siswa.edit` sebagai referensi kewenangan serupa).
- **View**: tab baru di halaman detail siswa (`resources/views/admin/siswa/tabs/`, mengikuti pola tab yang sudah ada untuk Guru/Karyawan/OrangTua — cek `resources/views/admin/guru/tabs/profil.blade.php` sebagai referensi struktur) — daftar keringanan aktif + form assign baru.

### 3.3 Test Requirements

- Admin bisa assign kategori keringanan ke siswa (dengan/tanpa `berlaku_sampai`).
- Validasi tenant: kategori keringanan dari lembaga LAIN tidak bisa di-assign ke siswa lembaga ini (403/422).
- Validasi tanggal: `berlaku_sampai` < `berlaku_dari` ditolak.
- Admin bisa mencabut keringanan yang sudah di-assign.
- Regression: `TagihanNominalResolver::resolveDiscount()` (sudah ada, tidak diubah) tetap benar menghitung potongan begitu `SiswaKeringanan` dibuat lewat UI baru ini (bukan cuma lewat factory seperti test lama).

## 4. Perbaikan #2 — `tagihan.person_id`

### 4.1 Latar belakang masalah (lihat §1 untuk detail penuh, ringkasan di sini)

`tagihan` menyasar entitas lewat `tagihable` polymorphic: `Pendaftaran::class` (masa calon siswa) atau `Siswa::class` (masa siswa aktif). Karena `Pendaftaran` dan `Siswa` adalah baris database yang BERBEDA untuk orang yang SAMA (dihubungkan lewat `person_id` sejak identity-v1), ledger keuangan satu orang terpecah jadi dua "sisi" tanpa penghubung langsung. Ini memaksa `DashboardController` melakukan OR manual di 2 titik untuk menggabungkan tampilan.

### 4.2 Keputusan desain terkunci

**Opsi C dipilih, MENGGANTIKAN sepenuhnya "Opsi B" (re-parenting `tagihable`) yang sempat dibahas di analisis redesign Tagihan sebelumnya — Opsi B TIDAK dikerjakan.**

Mekanisme: tambah kolom `tagihan.person_id`, diisi dari resolusi `tagihable → person_id` di titik penciptaan tagihan. **`tagihable_type`/`tagihable_id` TIDAK PERNAH diubah nilainya** — tetap akurat secara historis. `person_id` murni kolom tambahan untuk agregasi ledger lintas jenis `tagihable`.

Alasan Opsi C dipilih dibanding Opsi B: Opsi B mensyaratkan MEMUTASI baris `tagihan` lama (mengubah `tagihable_type`/`id` dari `Pendaftaran` ke `Siswa` saat konversi) — berisiko kalau ada baris gagal ter-backfill (riwayat hilang dari ledger tanpa jejak). Opsi C murni ADDITIF: kolom baru, tidak pernah memutasi data existing. Kalau backfill satu baris gagal, baris itu sekadar `person_id NULL` sementara (mudah dideteksi & diperbaiki), tanpa kehilangan data `tagihable` aslinya.

### 4.3 DDL

```sql
-- Tahap 1: kolom nullable
ALTER TABLE tagihan ADD COLUMN person_id BIGINT UNSIGNED NULL AFTER tagihable_id;

-- Tahap akhir (setelah backfill + verifikasi, migration TERPISAH):
ALTER TABLE tagihan
    MODIFY COLUMN person_id BIGINT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_tagihan_person FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE RESTRICT,
    ADD INDEX idx_tagihan_person (person_id);
```

**`ON DELETE RESTRICT`** — dikonfirmasi konsisten dengan pola yang SUDAH dipakai di 4 tabel role identity-v1 (`guru`, `karyawan`, `orang_tua`, `siswa` — semua `fk_*_person ... ON DELETE RESTRICT`, lihat `.agents/specs/2026-08-29-identity-v1-person-master-entity.md` §3). Alasan sama: `persons` tidak boleh terhapus kalau masih ada data finansial terkait — mencegah hilangnya riwayat keuangan secara tidak sengaja.

### 4.4 Titik pengisian `person_id` — SEMUA 6 titik penulis (terverifikasi lengkap via grep, tidak ada titik lain)

| # | File:baris | Jalur | Resolusi `person_id` |
|---|---|---|---|
| 1 | `app/Services/TagihanGenerator.php:56` | Pendaftaran (produksi) | `$pendaftaran->calonMurid->person_id` |
| 2 | `app/Domains/Keuangan/Services/TagihanBillingGenerator.php:70` | Siswa (produksi) | `$siswa->person_id` (langsung) |
| 3 | `database/seeders/TagihanSeeder.php:53` | Pendaftaran (seeder) | `$diterima->calonMurid->person_id` |
| 4 | `database/seeders/TagihanSeeder.php:63` | Pendaftaran (seeder) | idem baris 3, `$diterima` sama |
| 5 | `database/seeders/TagihanSeeder.php:76` | Pendaftaran (seeder) | `$cicilanDemo->calonMurid->person_id` |
| 6 | `database/seeders/KeuanganDemoSeeder.php:105` | Siswa (seeder) | `$siswa->person_id` (langsung) |

Titik yang dikonfirmasi BUKAN penulis langsung (delegasi ke #1/#2 di atas, tidak perlu disentuh): `TagihanSusulanController::buatSusulan()`, `CatatManualTagihanAction` (mencatat pembayaran, bukan tagihan), `ProsesJenisTagihanBillingAction`, command `GenerateTagihanHarian`/`ProsesTagihan`, listener `GenerateTagihanForActivatedBillType`/`GenerateTagihanForUpdatedClass`/`GenerateTagihanForNewStudent`.

### 4.5 Backfill data lama

Command baru `keuangan:backfill-tagihan-person-id` (pola sama seperti `identity:backfill-persons` yang sudah ada, meski command itu sudah dihapus di Task 28 identity-v1 — pola desainnya yang direplikasi, bukan reuse kode):

```
Untuk setiap baris tagihan WHERE person_id IS NULL:
  JIKA tagihable_type = Pendaftaran::class:
    $pendaftaran = Pendaftaran::find(tagihable_id)
    JIKA $pendaftaran NULL ATAU $pendaftaran->calonMurid NULL ATAU $pendaftaran->calonMurid->person_id NULL:
      CATAT sebagai baris gagal (id tagihan + alasan), LEWATI baris ini -- JANGAN throw/berhenti
    LAIN:
      UPDATE tagihan SET person_id = $pendaftaran->calonMurid->person_id
  JIKA tagihable_type = Siswa::class:
    $siswa = Siswa::withoutGlobalScopes()->find(tagihable_id)
    JIKA $siswa NULL ATAU $siswa->person_id NULL:
      CATAT sebagai baris gagal, LEWATI
    LAIN:
      UPDATE tagihan SET person_id = $siswa->person_id

Di akhir: tampilkan ringkasan (total diproses, total berhasil, daftar lengkap baris gagal dengan id+alasan).
Idempotent: aman dijalankan ulang (hanya memproses WHERE person_id IS NULL).
```

Command verifikasi terpisah `keuangan:verify-tagihan-person-id` — exit 1 kalau masih ada `person_id IS NULL`, tampilkan daftar id-nya. Migration NOT NULL (§4.3 tahap akhir) HANYA dijalankan setelah command ini exit 0.

**Catatan realita data**: berdasarkan pengecekan langsung (2026-09-01), SEMUA 6 baris `tagihan` yang ada sekarang punya `tagihable` yang bisa di-resolve (`CalonMurid`/`Siswa` terkait semuanya sudah punya `person_id`) — secara praktis backfill di lingkungan dev ini akan 100% berhasil tanpa baris gagal. Command tetap WAJIB menangani kasus gagal dengan benar (bukan asumsi selalu sukses) untuk lingkungan lain yang datanya mungkin tidak serapi ini.

### 4.6 Konsumen yang disederhanakan

**`app/Http/Controllers/Admin/DashboardController.php`**, 2 titik:

```php
// Baris 133-135, SEBELUM:
$q->where(fn ($q2) => $q2->where('tagihable_type', Siswa::class)->where('tagihable_id', $siswa->id));
if ($siswa->pendaftaran_asal_id !== null) {
    $q->orWhere('pendaftaran_id', $siswa->pendaftaran_asal_id);
}
// SESUDAH:
$q->where('person_id', $siswa->person_id);

// Baris 200-206, SEBELUM:
$q->where(fn ($q2) => $q2->where('tagihable_type', Siswa::class)->whereIn('tagihable_id', $siswaIds));
if (!empty($pendaftaranIds)) {
    $q->orWhereIn('pendaftaran_id', $pendaftaranIds);
}
// SESUDAH:
$q->whereIn('person_id', $anakList->pluck('person_id'));
```

### 4.7 Reparenting saat `MergePersonsAction` menggabung 2 Person — Domain Event, sinkron

**Keputusan (dikonfirmasi user)**: domain Keuangan TIDAK ditambahkan langsung ke `MergePersonsAction::ROLE_TABLES` (identity-v1 tetap tidak tahu apa-apa soal skema Keuangan, konsisten dengan Non-Goals spec identity-v1). Sebagai gantinya: **domain event, WAJIB berjalan SINKRON dalam transaksi yang sama** — kalau listener gagal, seluruh merge (termasuk perubahan `Person`) HARUS rollback, bukan sekadar logging error.

**Event baru**: `App\Domains\Identity\Events\PersonsMerged` — **TIDAK boleh implement `ShouldQueue`** (kalau implement itu, Laravel akan queue listener-nya secara async, melanggar syarat sinkron). Payload: `$losing` (Person, sebelum di-soft-delete) dan `$winning` (Person).

```php
namespace App\Domains\Identity\Events;

use App\Domains\Identity\Models\Person;

class PersonsMerged
{
    public function __construct(
        public readonly Person $losing,
        public readonly Person $winning,
    ) {}
}
```

**Modifikasi `MergePersonsAction::execute()`** (`app/Domains/Identity/Actions/MergePersonsAction.php`) — tambah `event(new PersonsMerged($losing, $winning));` DI DALAM closure `DB::transaction()`, setelah loop `ROLE_TABLES` (baris 26-28 saat ini), SEBELUM logic `user_id`/`delete()`:

```php
DB::transaction(function () use ($losing, $winning) {
    foreach (self::ROLE_TABLES as $table) {
        DB::table($table)->where('person_id', $losing->id)->update(['person_id' => $winning->id]);
    }

    event(new PersonsMerged($losing, $winning)); // BARU -- listener domain lain (Keuangan, dst) jalan sinkron di sini

    if ($losing->user_id !== null && $winning->user_id === null) {
        // ... tidak berubah
    } else {
        $losing->update(['merged_into_person_id' => $winning->id]);
    }

    $losing->delete();
});
```

Karena `event()` Laravel menjalankan listener SINKRON secara default (kecuali listener implement `ShouldQueue`, yang TIDAK dilakukan di sini), exception apa pun dari listener akan propagate ke atas dan membatalkan `DB::transaction()` — memenuhi syarat "gagal = rollback semua."

**Listener baru**: `App\Domains\Keuangan\Listeners\ReparentTagihanOnPersonsMerged` — **TIDAK implement `ShouldQueue`**:

```php
namespace App\Domains\Keuangan\Listeners;

use App\Domains\Identity\Events\PersonsMerged;
use Illuminate\Support\Facades\DB;

class ReparentTagihanOnPersonsMerged
{
    public function handle(PersonsMerged $event): void
    {
        DB::table('tagihan')
            ->where('person_id', $event->losing->id)
            ->update(['person_id' => $event->winning->id]);
    }
}
```

Registrasi listener: `EventServiceProvider` atau auto-discovery Laravel 12 (cek konvensi yang sudah dipakai project ini untuk listener lain sebelum memutuskan — samakan dengan pola existing, jangan perkenalkan pola baru).

**Reusability**: pola ini (event `PersonsMerged` + listener per-domain) sengaja generik — kalau nanti Payroll atau domain lain butuh ikut reparenting saat merge, tinggal tambah listener baru, `MergePersonsAction` tidak perlu diubah lagi.

### 4.8 Test Requirements

- Kedua generator (`TagihanGenerator`, `TagihanBillingGenerator`) mengisi `person_id` dengan benar sesuai jalurnya.
- Command backfill: berhasil untuk baris valid, mencatat (bukan crash) untuk baris yang gagal resolve.
- Command verify: exit 1 kalau ada `NULL`, exit 0 kalau bersih.
- Migration NOT NULL + FK: berhasil diterapkan setelah backfill lengkap.
- `DashboardController` regression: hasil query BARU (`where('person_id', ...)`) menghasilkan angka yang SAMA PERSIS dengan hasil OR-hack LAMA, untuk skenario siswa yang punya riwayat tagihan dari masa Pendaftaran DAN masa Siswa aktif sekaligus.
- **Merge reparenting**: setelah `MergePersonsAction::execute($losing, $winning)` dipanggil, `tagihan` yang tadinya `person_id = $losing->id` berubah jadi `person_id = $winning->id`.
- **Rollback-on-failure**: simulasikan listener melempar exception (mis. lewat `Event::listen` override di test) — assert SELURUH transaksi merge rollback (Person TIDAK jadi ter-merge, `merged_into_person_id` tetap NULL) ketika listener gagal.

## 5. Perbaikan #3 — Sentralisasi `PPDB_KATEGORI`

> **Revisi 2026-09-01 (post-review, superseded oleh paket #4)**: §5.2 di bawah ini (buat `JenisTagihan::isPpdbKategori()`) TIDAK JADI DIKERJAKAN. Audit lanjutan menemukan kebutuhan lebih besar untuk mengubah `kategori` jadi PHP Backed Enum (13 titik patah, bukan cuma 7) — dipisah jadi `.agents/specs/2026-09-01-keuangan-kategori-tagihan-backed-enum.md` (paket #4). Keputusan urutan eksekusi terkunci: **paket #4 dikerjakan PENUH lebih dulu** (menghasilkan `KategoriTagihan::isPpdb()`), BARU paket ini (#1+2+3) dikerjakan — §5.2 disederhanakan jadi "pakai `KategoriTagihan::isPpdb()` yang sudah ada", bukan membuat mekanisme baru yang nanti harus dihapus lagi. Lihat plan implementasi gabungan untuk urutan detail.

### 5.1 Titik yang terpengaruh (7 total, terverifikasi lengkap)

| # | File:baris | Bentuk saat ini |
|---|---|---|
| 1 | `app/Console/Commands/ProsesTagihan.php:12` | `private const PPDB_KATEGORI = [...]` |
| 2 | `app/Domains/Keuangan/Services/TagihanBillingGenerator.php:18` | `private const PPDB_KATEGORI = [...]` |
| 3 | `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php:32` | `private const PPDB_KATEGORI = [...]` |
| 4 | `app/Domains/Keuangan/Listeners/GenerateTagihanForActivatedBillType.php:10` | `private const PPDB_KATEGORI = [...]` |
| 5 | `app/Domains/Keuangan/Listeners/GenerateTagihanForUpdatedClass.php:27` | literal array `whereNotIn('kategori', ['pendaftaran', 'daftar_ulang'])` |
| 6 | `app/Domains/Keuangan/Listeners/GenerateTagihanForNewStudent.php:27` | idem #5 |
| 7 | `app/Http/Controllers/Lembaga/Keuangan/PembayaranController.php:34` | ternary rapuh: `kategori === 'pendaftaran' ? 'Tagihan Pendaftaran' : 'Tagihan Daftar Ulang'` (salah kalau kategori sebenarnya bukan keduanya) |

### 5.2 Solusi (DIREVISI — lihat catatan di awal §5)

~~Tambah konstanta terpusat di `JenisTagihan::isPpdbKategori()`~~ — TIDAK JADI. Karena paket #4 dikerjakan lebih dulu dan sudah menghasilkan `App\Domains\Keuangan\Enums\KategoriTagihan::isPpdb()` (method instance, bukan static helper berbasis string), titik-titik di §5.1 SEMUANYA sudah selesai diperbaiki sebagai bagian dari eksekusi paket #4 itu sendiri (lihat spec paket #4 §4.2 — titik #1/#2/#3/#4/#5/#6/#12/#13 di sana adalah titik #1-4 dan #7 di daftar §5.1 ini, ditambah 2 titik lain yang baru ketemu di audit paket #4). Titik #5 dan #6 (`GenerateTagihanForUpdatedClass.php:27`, `GenerateTagihanForNewStudent.php:27` — keduanya `whereNotIn('kategori', [...])` di level query builder, bukan perbandingan objek) TIDAK termasuk breakage list paket #4 karena aman terhadap cast enum (operasi SQL langsung) — tetap diganti ke `KategoriTagihan` di sini murni untuk konsistensi sumber kebenaran (pakai `array_map(fn ($c) => $c->value, KategoriTagihan::cases())` yang di-filter, atau langsung `[KategoriTagihan::Pendaftaran->value, KategoriTagihan::DaftarUlang->value]`), BUKAN karena ada bug yang diperbaiki.

**Tidak ada pekerjaan baru untuk paket ini di §5** selain memastikan titik #5 dan #6 memakai `KategoriTagihan` (bukan literal string) untuk konsistensi, dan memverifikasi (test regression) bahwa titik #1-4, #7 dari paket #4 memang benar sudah menutup seluruh §5.1.

### 5.3 Test Requirements (DIREVISI)

- ~~`JenisTagihan::isPpdbKategori()` benar~~ — sudah tercakup sebagai `KategoriTagihan::isPpdb()` test di paket #4.
- Regression test untuk titik #5 dan #6 (§5.1): perilaku SAMA, memakai `KategoriTagihan` sebagai sumber nilai, bukan literal string.
- Regression test titik #7 (`PembayaranController.php:34`): sudah tercakup di test paket #4 (titik #7 di daftarnya).

## 6. Urutan Migrasi & Implementasi (DIREVISI — lihat plan gabungan untuk detail penuh)

**Prasyarat**: paket #4 (`.agents/specs/2026-09-01-keuangan-kategori-tagihan-backed-enum.md`) SUDAH selesai dieksekusi PENUH dan full test suite hijau, SEBELUM paket ini (#1+2+3) dimulai.

1. **#3**: pastikan titik #5/#6 (§5.1) memakai `KategoriTagihan` (bukan literal string) — konsolidasi murni, bukan bug fix.
2. **#2 — Schema**: migration tambah `tagihan.person_id` nullable.
3. **#2 — Kode**: sesuaikan 6 titik penulis (§4.4), event `PersonsMerged` + listener (§4.7), sederhanakan `DashboardController` (§4.6).
4. **#2 — Backfill**: command backfill + verify, jalankan terhadap data dev.
5. **#2 — Constraint**: migration terpisah untuk NOT NULL + FK, HANYA setelah verify exit 0.
6. **#1 — UI Keringanan**: independen dari #2/#3, dikerjakan terakhir dalam paket ini — controller, route, permission, view.
7. **Full test suite** setelah semua langkah di atas selesai.

## 7. Risiko

- **Event sinkron dalam transaksi** (§4.7) — kalau ke depannya ada developer yang tidak sadar menambahkan `ShouldQueue` ke listener baru (mengira semua listener "sebaiknya di-queue" sebagai best practice umum), syarat sinkron ini akan diam-diam rusak (merge tetap sukses, tapi reparenting tagihan jadi eventual/tertunda tanpa rollback protection). Mitigasi: komentar eksplisit di kedua class (event dan listener) menjelaskan KENAPA tidak boleh di-queue.
- **Backfill di lingkungan dengan data tidak serapi dev** — kalau di masa depan ada `Pendaftaran` tanpa `calonMurid` (data legacy pra-identity-v1 yang entah bagaimana lolos), command backfill akan mencatatnya sebagai gagal, BUKAN membuat migration NOT NULL macet — tapi tagihan itu akan permanen tidak punya `person_id` sampai ditangani manual. Ini diterima sebagai trade-off yang wajar (lebih baik dari memaksa nilai default yang salah).
