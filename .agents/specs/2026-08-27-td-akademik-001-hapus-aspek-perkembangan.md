# Spec: TD-AKADEMIK-001 — Hapus `TipeMataPelajaran::AspekPerkembangan` (Dead-End Non-Terintegrasi)

**Status:** Ready for Plan — disetujui user, siap masuk plan eksekusi.
**Branch:** `akademik-v2`
**Bergantung pada:** Sprint 1-5 Fondasi Akademik Multi-Jenjang + `TD-AKADEMIK-002` (SELESAI semua).

## Latar Belakang

`TD-AKADEMIK-001` dicatat saat Sprint 1: ditemukan bahwa desain ASLI modul Akademik (`docs/superpowers/specs/2026-07-24-presensi-asesmen-design.md`, 1 bulan sebelum Sprint 1) sudah merancang `MataPelajaran.tipe = 'aspek_perkembangan'` sebagai cara mewakili penilaian PAUD non-formal — TAPI Sprint 1 malah membangun model terpisah `ElemenCp` + sistem polymorphic `subjek_type`/`subjek_id`, tanpa menyadari mekanisme lama sudah ada.

**Audit menyeluruh (26-27 Agustus 2026) sebelum spec ini ditulis** — dilakukan bertahap, dikoreksi 2x setelah temuan awal ternyata kurang lengkap:

1. **Klaim awal "dead code total" TERBUKTI SALAH/TERLALU KUAT.** `aspek_perkembangan` PUNYA CRUD yang berfungsi dan diuji: `MataPelajaranController` (validasi `in:mapel,aspek_perkembangan`), dropdown form (`_form.blade.php`), 3 file test yang benar-benar membuat & memverifikasi row bertipe ini (`MataPelajaranCrudTest`, `MataPelajaranTest`, `AcademicEnumsTest`) — semua LULUS.

2. **TAPI tidak terintegrasi ke belakang, dan ini bug nyata bukan cuma kerapian.** `CreateKomponenPenilaianAction::execute()` (Sprint 2) menghitung default `assessment_type` HANYA dari `subjek_type` (`'elemen_cp'` → narrative, `'mata_pelajaran'` → numeric) — **sama sekali tidak melihat `MataPelajaran.tipe`**. Artinya kalau ada lembaga PAUD sungguhan membuat Mata Pelajaran "Motorik Halus" bertipe `aspek_perkembangan` (persis skenario yang tesnya sendiri simulasikan), sistem TETAP memberi default `numeric` — salah, seharusnya `narrative` seperti halnya `ElemenCp`. Ini genuinely bug laten, independen dari soal data di bawah.

3. **Soal ketiadaan data pemakaian nyata: itu gap seeder, bukan bukti fitur tidak penting.** `LembagaSeeder.php` cuma membuat lembaga `bentuk_pendidikan='SD'` — TIDAK ADA satu pun lembaga PAUD/TK/KB di seluruh data demo, jadi wajar `MataPelajaranSeeder` tidak pernah membuat row `aspek_perkembangan` (bukan karena fiturnya dihindari, tapi karena skenario pemicunya memang tidak pernah di-seed).

**Kesimpulan & keputusan (27 Agustus 2026)**: `aspek_perkembangan` adalah CRUD yang berfungsi tapi **jebakan UX** — kelihatan seperti opsi valid, tapi kalau benar-benar dipakai, hasilnya salah default penilaian. Dibanding memperbaiki integrasinya (tetap mempertahankan 2 cara mewakili konsep yang sama, tidak menghilangkan duplikasi arsitektur — pola yang sudah ditolak di keputusan Sprint 5 soal `ReportBuilder`), diputuskan: **hapus total** `TipeMataPelajaran::AspekPerkembangan`, pertahankan `ElemenCp` sebagai SATU-SATUNYA jalur resmi untuk penilaian PAUD non-formal-subject (sudah terintegrasi penuh: default narrative benar, PDF rapor PAUD, dashboard).

**Konfirmasi tidak ada data nyata yang akan hilang**: nol row `mata_pelajaran` dengan `tipe='aspek_perkembangan'` di seluruh seeder/data demo — penghapusan ini TIDAK memerlukan migrasi/backfill data apa pun.

## Keputusan Desain

1. **Hapus total, bukan cuma tandai deprecated.** Sesuai prinsip yang sudah dipegang sejak Sprint 5 (`ReportBuilder` dihapus total, bukan dibiarkan setengah-jalan): kalau sesuatu belum pernah benar-benar terpakai dan ada jalur pengganti yang sudah teruji (`ElemenCp`), lebih sehat dihapus daripada dipertahankan sbg opsi yang menyesatkan.

2. **Penghapusan sampai level skema DB, bukan cuma kode aplikasi.** Kolom `mata_pelajaran.tipe` adalah `ENUM('mapel', 'aspek_perkembangan')` di level database (bukan cuma PHP enum). Migration baru WAJIB menyempitkan `ENUM` jadi `ENUM('mapel')` — supaya penghapusan benar-benar total (skema tidak lagi secara diam-diam mengizinkan nilai yang sudah tidak ada jalur menujunya di kode), bukan cuma "kode tidak pernah generate itu lagi tapi DB masih permisif".

3. **Migration WAJIB guard eksplisit sebelum `ALTER TABLE`** — cek dulu tidak ada row `tipe='aspek_perkembangan'` sebelum menyempitkan ENUM; kalau ternyata ADA (mis. di environment lain yang datanya beda dari asumsi spec ini), migration harus GAGAL KERAS dengan pesan jelas, BUKAN diam-diam menghapus/mengubah data row itu. Ini defense-in-depth — audit di §Latar Belakang sudah memastikan kosong utk environment ini, tapi migration tidak boleh mengasumsikan itu berlaku universal tanpa verifikasi runtime.

4. **Test yang murni menguji `aspek_perkembangan` dihapus; test yang menguji konsep lebih umum (kelompok nullable) diadaptasi, bukan dihapus.** `kelompok` nullable adalah properti umum kolom (berlaku utk tipe `mapel` juga, mis. mata pelajaran mulok/lokal tanpa kelompok resmi) — test `'allows nullable kelompok for aspek perkembangan paud'` diubah memakai `TipeMataPelajaran::Mapel` supaya cakupan "kelompok boleh null" tidak hilang, bukan sekadar dihapus bersama fiturnya.

## §1. Migration — Sempitkan ENUM `tipe`

```php
<?php
// database/migrations/2026_08_27_100000_remove_aspek_perkembangan_from_mata_pelajaran_tipe.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $jumlahTerpakai = DB::table('mata_pelajaran')->where('tipe', 'aspek_perkembangan')->count();

        if ($jumlahTerpakai > 0) {
            throw new \RuntimeException(
                "Migration dibatalkan: ditemukan {$jumlahTerpakai} baris mata_pelajaran dengan tipe='aspek_perkembangan'. " .
                "Migration ini mengasumsikan nol baris terpakai (dikonfirmasi kosong di seluruh seeder demo saat spec ditulis). " .
                "Selesaikan migrasi data baris tersebut secara manual (pilih konsolidasi ke ElemenCp atau tipe lain) sebelum menjalankan migration ini."
            );
        }

        DB::statement("ALTER TABLE mata_pelajaran MODIFY COLUMN tipe ENUM('mapel') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE mata_pelajaran MODIFY COLUMN tipe ENUM('mapel', 'aspek_perkembangan') NOT NULL");
    }
};
```

## §2. Enum PHP — Hapus Case

`app/Enums/TipeMataPelajaran.php`:
```php
<?php

namespace App\Enums;

enum TipeMataPelajaran: string
{
    case Mapel = 'mapel';

    public function label(): string
    {
        return match ($this) {
            self::Mapel => 'Mata Pelajaran',
        };
    }
}
```

## §3. Controller — Validasi & Hapus Stat `countAspek`

`app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`:
- Baris `'countAspek' => MataPelajaran::where('tipe', TipeMataPelajaran::AspekPerkembangan->value)->count(),` (di method `index()`) **DIHAPUS TOTAL** (bukan diganti `0` statis — variabel `$countAspek` tidak lagi dikirim ke view sama sekali, karena `label()`/stat itu sendiri sudah tidak relevan).
- 2 baris `'tipe' => ['required', 'in:mapel,aspek_perkembangan'],` (di `store()` dan `update()`) diganti `'tipe' => ['required', 'in:mapel'],`.

## §4. View — Hapus UI Terkait

`resources/views/portals/lembaga/akademik/mata-pelajaran/_form.blade.php`: hapus baris
```blade
<option value="aspek_perkembangan" @selected($val('tipe') === 'aspek_perkembangan')>Aspek Perkembangan (PAUD / TK)</option>
```
(Baris `<option value="mapel" ...>` dipertahankan.)

`resources/views/portals/lembaga/akademik/mata-pelajaran/index.blade.php`:
- Hapus card ke-3 (stat "Aspek Perkembangan", ikon `extension`, background `amber`) di blok "KPI Compact Horizontal Statistic Cards".
- Ubah grid pembungkus dari `grid-cols-1 gap-3 sm:grid-cols-3` menjadi `grid-cols-1 gap-3 sm:grid-cols-2` (menyesuaikan jadi 2 card, bukan 3 — cegah layout kosong/tidak seimbang).
- Filter dropdown "Tipe Kurikulum" (baris `@foreach ($tipeList as $item)`) **TIDAK PERLU diubah** — otomatis cuma menampilkan 1 opsi ("Mata Pelajaran") begitu enum PHP-nya cuma 1 case, karena loop-nya generik dari `TipeMataPelajaran::cases()`.

## §5. Test — Hapus yang Murni Terkait, Adaptasi yang Konsepnya Umum

**`tests/Unit/Enums/AcademicEnumsTest.php`**: ubah
```php
expect(array_column(TipeMataPelajaran::cases(), 'value'))->toBe(['mapel', 'aspek_perkembangan']);
```
menjadi
```php
expect(array_column(TipeMataPelajaran::cases(), 'value'))->toBe(['mapel']);
```

**`tests/Feature/Admin/MataPelajaranCrudTest.php`**: test `'calculates executive KPI statistics accurately in index view'` (judul TIDAK perlu diubah, sudah generik) membuat 2 row (1 `Mapel` kode `SD-01`, 1 `AspekPerkembangan` kode `PAUD-01`/"Motorik Halus") lalu assert `totalMapel=2`, `countKurikulum=1`, `countAspek=1`. Ubah jadi: HAPUS blok `MataPelajaran::create([...'AspekPerkembangan'...])` kedua sepenuhnya, HAPUS baris `$response->assertViewHas('countAspek', 1);`, ubah `$response->assertViewHas('totalMapel', 2);` jadi `$response->assertViewHas('totalMapel', 1);` (krn cuma 1 row yang dibuat sekarang).

**`tests/Unit/Models/MataPelajaranTest.php`**: test `'allows nullable kelompok for aspek perkembangan paud'` DIADAPTASI (bukan dihapus) — ganti `TipeMataPelajaran::AspekPerkembangan->value` jadi `TipeMataPelajaran::Mapel->value`, judul test jadi `'allows nullable kelompok for a mata pelajaran without a formal kelompok'` (mis. mulok/lokal), `nama`/`kode` disesuaikan supaya tidak menyiratkan PAUD lagi (mis. `'Muatan Lokal'`/`'MULOK-01'`).

## Non-Goals

- TIDAK menyentuh `ElemenCp` sama sekali (model, migration, seeder, test) — `ElemenCp` adalah pemenang keputusan ini, tetap dipakai apa adanya.
- TIDAK menambah field/kolom baru — murni penghapusan/penyempitan.
- TIDAK mengerjakan STPPA 6-aspek (bonus temuan lama di `TD-AKADEMIK-001` soal `ElemenCp` cuma 3 dari 6 elemen resmi) — itu keputusan terpisah, di luar scope spec ini (hapus `aspek_perkembangan` tidak bergantung pada apakah `ElemenCp` nanti diperkaya atau tidak).
- TIDAK menyentuh `KelompokMataPelajaran` enum (`AgamaKemenag`/`ProjekP5Ppra` dkk) — itu konteks kurikulum lain yang disebut di catatan `TD-AKADEMIK-001` asli, tapi tidak overlap dengan `aspek_perkembangan` secara langsung, di luar scope.

## Self-Review

- Semua keputusan hasil diskusi masuk eksplisit: (1) hapus total bukan deprecated, konsisten prinsip Sprint 5 §Keputusan Desain poin 1; (2) migrasi sampai level DB ENUM §Keputusan Desain poin 2/§1; (3) guard defense-in-depth sebelum ALTER §Keputusan Desain poin 3/§1; (4) test kelompok-nullable diadaptasi bukan dihapus §Keputusan Desain poin 4/§5.
- Placeholder scan: tidak ada. Semua file & baris yang berubah sudah diverifikasi langsung dari kode (bukan ditebak) — termasuk nama file migration existing terkait (`2026_07_25_090000_create_mata_pelajaran_table.php`) yang dipakai sbg referensi struktur ENUM asli.
- Scope check: fokus tunggal pada penghapusan `aspek_perkembangan`. Tidak melebar ke `ElemenCp`, STPPA 6-aspek, atau `KelompokMataPelajaran`.
- Konsistensi: `TipeMataPelajaran::cases()` dipakai generik di filter dropdown index (`§4`) — otomatis konsisten begitu enum PHP diubah (`§2`), tidak perlu perubahan terpisah di titik itu.
