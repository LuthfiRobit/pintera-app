# Spec: Migrasi Domain Keuangan Sub-project 5 (Mini, Penutup Celah) — Kategori & Siswa Keringanan

## 1. Latar Belakang

Review independen pasca-SP4 (dilakukan sebagai audit tambahan atas permintaan user, 24 Agustus 2026) menemukan bahwa audit final Task 12 SP4 punya blind spot: command grep-nya hanya mencari pola `wallet|cicilan|tagihan|pembayaran|bri`, yang tidak menangkap kata "keringanan" (potongan/diskon biaya tagihan). Akibatnya 2 model dan 1 controller yang murni milik domain Keuangan lolos tanpa termigrasi.

**Baseline commit**: `032200b`.

## 2. Cakupan

3 file berpindah:

| File | Lokasi saat ini | Lokasi baru |
|---|---|---|
| `app/Models/KategoriKeringanan.php` | `App\Models` | `App\Domains\Keuangan\Models` |
| `app/Models/SiswaKeringanan.php` | `App\Models` | `App\Domains\Keuangan\Models` |
| `app/Http/Controllers/Admin/KategoriKeringananController.php` | `App\Http\Controllers\Admin` | `App\Http\Controllers\Lembaga\Keuangan` |

**Route TIDAK berubah** — nama `admin.kategori-keringanan.store` dan path tetap sama (konsisten dengan sibling route Keuangan lain di `routes/admin/keuangan.php` yang semuanya berprefix `admin.` walau controllernya sudah di `Lembaga\Keuangan\`, pola ini sudah ada sejak sebelum SP1 dan bukan sesuatu yang diubah migrasi ini).

## 3. Bukti Keterikatan ke Domain Keuangan

- `SiswaKeringanan` dipakai langsung oleh `Domains\Keuangan\Services\TagihanNominalResolver::resolveDiscount()` — bagian inti mesin kalkulasi nominal tagihan per siswa.
- `KategoriKeringanan` dipakai oleh `Domains\Keuangan\Models\JenisTagihanKeringanan` (aturan potongan per jenis tagihan, sudah di domain sejak SP1) dan `Lembaga\Keuangan\JenisTagihanController` (form kelola jenis tagihan, referensi dropdown kategori keringanan).
- Route `kategori-keringanan.store` di `routes/admin/keuangan.php` — 1 dari 6 route Keuangan di file itu, satu-satunya yang masih menunjuk controller `Admin\` (5 lainnya sudah `Lembaga\Keuangan\`).
- 7 file test menyentuh fitur ini secara aktif — bukan dead code.
- TIDAK ada keterikatan ke domain lain (SPMB/PPDB, Kesiswaan umum, dll) — grep menyeluruh mengonfirmasi HANYA dipakai oleh kode Keuangan.

## 4. Keputusan Desain

1. **Zero-behavior-change total** — tidak ada bug yang diperbaiki di sub-project ini (beda dengan SP4), murni pemindahan namespace. Tidak ada Action/DTO baru diekstrak dari `KategoriKeringananController::store()` — controller ini sudah sangat thin (1 method, validasi inline pendek), konsisten dengan preseden `DashboardController` read-only di SP4 yang juga dipindah tanpa restrukturisasi tambahan.
2. **Route name dan path TIDAK berubah** — hanya `use` statement di `routes/admin/keuangan.php` yang diupdate menjadi controller baru.
3. **Test TIDAK dipindah foldernya** — `tests/Feature/Admin/KategoriKeringananTest.php` dkk tetap di lokasi asalnya, hanya `use` import model yang diupdate. Konsisten dengan pola SP1-4 yang tidak pernah memindahkan file test, hanya mengupdate importnya.
4. **Gotcha dua arah yang perlu ditangani**:
   - `JenisTagihanKeringanan.php` (sudah di `Domains\Keuangan\Models`) punya `use App\Models\KategoriKeringanan;` — setelah `KategoriKeringanan` pindah ke namespace yang SAMA, `use` ini jadi referensi SAMA-NAMESPACE yang redundan, WAJIB dihapus (bukan cuma diupdate path-nya) supaya konsisten dengan pola cleanup SP4.
   - `KategoriKeringanan.php` sendiri punya `hasMany(\App\Domains\Keuangan\Models\JenisTagihanKeringanan::class)` (FQCN) — setelah pindah ke namespace yang sama dengan `JenisTagihanKeringanan`, FQCN ini jadi redundan, disederhanakan jadi `JenisTagihanKeringanan::class` biasa.
   - `SiswaKeringanan.php` punya `belongsTo(KategoriKeringanan::class)` bare — kedua model pindah BERSAMAAN ke namespace yang sama, referensi ini tetap valid tanpa perubahan.
   - `TagihanNominalResolver.php` (sudah di `Domains\Keuangan\Services`) punya `use App\Models\SiswaKeringanan;` — beda sub-namespace dari `Domains\Keuangan\Models`, WAJIB diupdate path-nya (bukan dihapus).
   - `JenisTagihanController.php` (sudah di `Lembaga\Keuangan`) punya `use App\Models\KategoriKeringanan;` — beda namespace, WAJIB diupdate path-nya.

## 5. Definisi Selesai

- 3 file di lokasi baru, 2 file lama (`app/Models/KategoriKeringanan.php`, `app/Models/SiswaKeringanan.php`) dan 1 controller lama (`app/Http/Controllers/Admin/KategoriKeringananController.php`) terhapus.
- Grep `App\Models\KategoriKeringanan\b` dan `App\Models\SiswaKeringanan\b` scope `app database tests` kosong total.
- Route `admin.kategori-keringanan.store` tetap ada dengan path sama, menunjuk controller baru.
- 7 test existing lolos tanpa modifikasi logic, hanya import.
- Full suite dijalankan (izin user dulu) hijau.
