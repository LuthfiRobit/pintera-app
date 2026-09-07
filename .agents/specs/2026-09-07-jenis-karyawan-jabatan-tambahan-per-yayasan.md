# Spec: Jenis Karyawan & Jabatan Tambahan Master — Jadi Per-Yayasan

> **Branch**: `rbac-v2`
> **Tanggal**: 7 September 2026
> **Latar belakang**: Ditemukan lewat audit lanjutan (item 🟡 di `.agents/logs/2026-09-07-audit-scope-yayasan-lembaga-sidebar.md`): `jenis_karyawan_master` dan `jabatan_tambahan_master` TIDAK punya kolom tenant sama sekali — datanya dibagi lintas SEMUA yayasan di seluruh sistem (bukan cuma lintas lembaga dalam 1 yayasan). Didiskusikan dengan user: sempat dipertimbangkan pola "katalog nasional + custom per-yayasan" (`yayasan_id` nullable), TAPI diputuskan disederhanakan jadi **per-yayasan penuh, `yayasan_id` NOT NULL** — tidak ada lagi konsep baris "nasional" bersama.

## Keputusan Bisnis

1. **`yayasan_id` WAJIB terisi (NOT NULL)** di kedua tabel — TIDAK ADA baris global/nasional. Setiap yayasan mengelola daftarnya sendiri sepenuhnya (create/edit/delete tanpa pengecualian baris).
2. **Yayasan baru mulai dari starter catalog via seeder saat onboarding** (bukan kloning otomatis dari yayasan lain, bukan baris nasional bersama). Kalau alur "yayasan baru dibuat" belum punya hook yang menjalankan seeder starter data, itu DI LUAR SCOPE spec ini — dicatat sebagai temuan terpisah, TIDAK dikerjakan di sini (dikonfirmasi eksplisit oleh user: "jangan pikirkan yayasan lain dulu, aman saja").
3. **Backfill data existing**: SEMUA baris `jenis_karyawan_master`/`jabatan_tambahan_master` yang ada sekarang di-assign ke `yayasan_id = 1` ("Yayasan Pintera") apa adanya — TIDAK di-kloning ke yayasan lain, TIDAK ditebak kebutuhan yayasan lain (keputusan eksplisit user). Yayasan lain (2, 3, 4 di data demo saat ini) akan mulai dengan daftar kosong sampai starter-seeder-per-onboarding (poin 2, di luar scope) tersedia.

## Verifikasi Data Sebelum Migrasi (WAJIB dibaca — bukan asumsi)

Dicek langsung ke `pintera_sdm_app` (tinker, read-only, tanpa transaksi karena query SELECT murni):
- `jenis_karyawan_master`: 4 baris total, 3 di antaranya dipakai `Karyawan.jenis_karyawan_id` — SEMUA 3 pemakaian berasal dari `yayasan_id=1` saja (0 kasus dipakai lintas yayasan).
- `jabatan_tambahan_master`: 16 baris total, 4 di antaranya dipakai lewat pivot `guru_jabatan_tambahan` — SEMUA 4 pemakaian juga dari `yayasan_id=1` saja.
- `attendance_policies.jenis_karyawan_id` dan `kuota_cuti_config.jenis_karyawan_id`: **0 baris** memakai kolom ini sama sekali saat ini (aman, tidak ada backfill diperlukan untuk 2 tabel ini).
- **Catatan penting**: "0 kasus lintas yayasan" ini BUKAN bukti struktural aman — cuma karena data demo saat ini konsentrasi di yayasan_id=1 (3 yayasan lain belum punya karyawan/guru yang pakai tabel ini sama sekali). Migrasi backfill TETAP WAJIB ditulis defensif menangani kasus umum (1 baris master dipakai >1 yayasan), BUKAN mengasumsikan "pasti 1 pemilik", supaya aman kalau nanti dijalankan di data produksi riil yang polanya beda.

## Arsitektur

### 1. Migrasi Schema

**`jenis_karyawan_master`**: tambah kolom `yayasan_id` (bigint unsigned, awalnya nullable untuk proses backfill, FK ke `yayasan.id` `ON DELETE CASCADE`), lalu diubah jadi NOT NULL di migrasi/step terpisah setelah backfill selesai (pola standar Laravel: tambah nullable → backfill data → ubah jadi NOT NULL, supaya migrasi tidak gagal di tabel yang sudah berisi data). Index pada `yayasan_id`. Ubah unique constraint `nama` (global) jadi composite `UNIQUE(yayasan_id, nama)`.

**`jabatan_tambahan_master`**: perubahan identik (`yayasan_id` FK NOT NULL setelah backfill, unique `(yayasan_id, nama)` menggantikan unique `nama` global).

### 2. Logika Backfill (di dalam migrasi yang sama, method `up()`)

Untuk KEDUA tabel, logika backfill per baris:
1. Cari SEMUA yayasan_id berbeda yang memakai baris ini (lewat FK: `karyawan.jenis_karyawan_id` → `karyawan.yayasan_id` untuk tabel pertama; `guru_jabatan_tambahan.jabatan_tambahan_master_id` → `guru.lembaga_id` → `lembaga.yayasan_id` untuk tabel kedua, karena `Guru` tidak punya `yayasan_id` langsung).
2. **Kalau 0 yayasan memakainya** (baris belum dipakai sama sekali): assign `yayasan_id = 1`.
3. **Kalau TEPAT 1 yayasan memakainya**: assign `yayasan_id` ke yayasan itu langsung (UPDATE baris existing, tidak perlu clone).
4. **Kalau LEBIH DARI 1 yayasan memakainya** (tidak terjadi di data sekarang, tapi WAJIB ditangani): assign baris ASLI ke yayasan PERTAMA (urutan id yayasan terkecil) yang memakainya. Untuk SETIAP yayasan LAIN yang juga memakainya, buat baris BARU (clone `nama`/`is_konselor`/`kelompok` dengan `yayasan_id` yayasan itu), lalu UPDATE semua baris `karyawan`/`guru_jabatan_tambahan` milik yayasan tersebut supaya menunjuk ke baris clone yang baru (bukan baris asli lagi).

### 3. Model — Scope

Ikuti PERSIS pola `App\Domains\Identity\Models\Person` (`booted()` method, BUKAN trait baru — cuma 2 model yang butuh ini, membuat trait baru untuk 2 pemakaian melanggar YAGNI):

```php
// app/Domains/Sdm/Models/JenisKaryawanMaster.php
use App\Models\Scopes\YayasanScope;

protected static function booted(): void
{
    static::addGlobalScope(new YayasanScope);
}
```

Sama persis untuk `JabatanTambahanMaster`. `YayasanScope` (`app/Models/Scopes/YayasanScope.php`) SUDAH ADA & teruji (dipakai `Person`), resolusi `yayasan_id` aktor sudah menangani staff/admin (langsung `$actingUser->yayasan_id` atau `$actingUser->lembaga?->yayasan_id`) — untuk konteks controller SDM ini (bukan akun personal siswa/orang tua), 2 cabang pertama resolusi sudah cukup, cabang fallback siswa/orangTua di scope itu tidak akan pernah kena tapi tidak berbahaya (tetap `null`, fail-closed sesuai desain scope itu).

Tambahkan `'yayasan_id'` ke `$fillable` kedua model.

### 4. Action — Auto-isi `yayasan_id` Saat Create

`yayasan_id` BUKAN input form (user tidak memilihnya), jadi TIDAK masuk DTO — dihitung di Action, pola PERSIS `AkunOrangTuaGenerator::buat(..., ?int $yayasanId = null)`:

```php
// app/Domains/Sdm/Actions/JenisKaryawan/CreateJenisKaryawanAction.php
final class CreateJenisKaryawanAction
{
    public function execute(JenisKaryawanMasterData $data, int $yayasanId): JenisKaryawanMaster
    {
        return JenisKaryawanMaster::create([
            'nama' => $data->nama,
            'yayasan_id' => $yayasanId,
        ])->loadCount('karyawan');
    }
}
```

Controller (`JenisKaryawanMasterController::store()`) menghitung `$yayasanId` sebelum memanggil Action:
```php
$yayasanId = auth()->user()->yayasan_id ?? auth()->user()->lembaga?->yayasan_id;
abort_if($yayasanId === null, 422, 'Konteks yayasan tidak dapat ditentukan.');

$item = $action->execute(JenisKaryawanMasterData::fromArray($data), $yayasanId);
```
Pola sama persis untuk `JabatanTambahanMasterController::store()` + `CreateJabatanTambahanAction`.

`UpdateJenisKaryawanAction`/`UpdateJabatanTambahanAction` TIDAK berubah (tidak pernah mengubah `yayasan_id` baris yang sudah ada — kepemilikan yayasan permanen sejak dibuat, tidak ada fitur "pindah kepemilikan").

### 5. Action — Guard Delete: Hitung Pemakaian HANYA di Yayasan Sendiri

Saat ini `DeleteJenisKaryawanAction`/`DeleteJabatanTambahanAction` menghitung pemakaian TANPA filter yayasan sama sekali (`$jenisKaryawanMaster->karyawan()->count()`, `$jabatanTambahanMaster->guru()->withoutGlobalScopes()->count()`) — ini masih benar SECARA KEBETULAN untuk data sekarang (karena belum ada pemakaian lintas yayasan), tapi SALAH secara desain setelah fix ini: baris sekarang milik 1 yayasan spesifik, jadi guard HARUS menghitung pemakaian di SELURUH yayasan itu (semua lembaga di bawahnya, bukan cuma lembaga aktor yang sedang login), bukan cuma di lembaga aktor saat ini, dan bukan pula lintas SEMUA yayasan lain.

`Karyawan` punya `yayasan_id` LANGSUNG di `$fillable` (pola "pool", terverifikasi `app/Models/Karyawan.php:27`) — query langsung:
```php
// DeleteJenisKaryawanAction
$karyawanCount = Karyawan::withoutGlobalScope(TenantScope::class)
    ->where('yayasan_id', $jenisKaryawanMaster->yayasan_id)
    ->where('jenis_karyawan_id', $jenisKaryawanMaster->id)
    ->count();
```

`Guru` TIDAK punya `yayasan_id` langsung (cuma `lembaga_id`, terverifikasi `app/Models/Guru.php:33`) — perlu lewat daftar lembaga milik yayasan itu, pola yang sama dipakai berulang kali sepanjang audit scope yayasan/lembaga sesi ini:
```php
// DeleteJabatanTambahanAction
$lembagaIdsYayasan = Lembaga::where('yayasan_id', $jabatanTambahanMaster->yayasan_id)->pluck('id');
$guruCount = $jabatanTambahanMaster->guru()
    ->withoutGlobalScopes()
    ->whereIn('guru.lembaga_id', $lembagaIdsYayasan)
    ->count();
```

### 6. Controller — `index()`

TIDAK butuh perubahan filter tambahan — begitu `YayasanScope` aktif di model, query `JenisKaryawanMaster::withCount('karyawan')->orderBy('nama')->get()` dan `JabatanTambahanMaster::withCount(['guru' => fn ($q) => $q->withoutGlobalScopes()])->orderBy(...)->get()` OTOMATIS ter-scope ke yayasan aktor lewat global scope model, TANPA perlu `when(widestScopeLevel()...)` manual (beda dari pola `TenantScope` yang butuh percabangan lembaga/yayasan — `YayasanScope` cuma kenal 1 level: yayasan). `withCount('karyawan')`/`withCount(['guru' => ...])` di `index()` TETAP boleh dibiarkan seperti sekarang (total pemakaian di SELURUH yayasan, sudah otomatis benar karena baris `JenisKaryawanMaster`/`JabatanTambahanMaster` yang dihitung sudah ter-scope ke 1 yayasan; relasi `karyawan`/`guru` sendiri yang di-`withCount` TIDAK perlu filter tambahan karena tujuannya cuma "berapa total dipakai", identik dengan tujuan guard di poin 5 — untuk konsistensi, terapkan filter `yayasan_id`/`lembaga_id` yang SAMA seperti poin 5 di closure `withCount`, supaya angka yang tampil di tabel index PERSIS sama dengan yang dipakai guard delete).

### 7. Validasi Unique — Ganti ke Composite

`store()`/`update()` di kedua controller, ganti `unique:jenis_karyawan_master,nama` (global) jadi ter-scope ke yayasan aktor:
```php
'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_karyawan_master', 'nama')->where('yayasan_id', $yayasanId)],
```
Pola sama untuk `update()` (tambah `->ignore($jenisKaryawanMaster->id)`) dan untuk `jabatan_tambahan_master`.

## Di Luar Scope / Backlog Terpisah

1. **Hook seeder starter-data saat yayasan baru onboarding** — belum dicek apakah sudah ada alur "buat yayasan baru" yang otomatis menjalankan seeder. TIDAK dikerjakan di spec ini (keputusan eksplisit user). Perlu diverifikasi & dikerjakan terpisah sebelum yayasan baru benar-benar mulai dipakai secara nyata.
2. **Kloning starter catalog ke yayasan 2, 3, 4 (data demo existing)** — sengaja TIDAK dikerjakan (keputusan eksplisit user, "jangan pikirkan yayasan lain dulu, aman saja"). Yayasan-yayasan ini akan mulai kosong sampai item 1 di atas selesai.
3. **`attendance_policies`/`kuota_cuti_config`** — tidak ada perubahan kode diperlukan (0 baris memakai `jenis_karyawan_id` saat ini), FK constraint-nya tetap valid karena `jenis_karyawan_master.id` tidak berubah oleh migrasi ini (backfill hanya menambah kolom, tidak mengubah/menghapus id existing kecuali untuk kasus clone di poin backfill 4 yang tidak terjadi di data sekarang).

## Ringkasan Test yang Wajib Ditambahkan

| Area | Test |
|---|---|
| Migrasi/backfill | Test migrasi (kalau ada pola test migrasi di project — cek dulu saat plan ditulis) ATAU test Feature yang menjalankan `RefreshDatabase` lalu assert baris existing dari seeder ter-assign `yayasan_id` yang benar |
| Model scope | Admin yayasan A tidak melihat baris milik yayasan B di `index()` (Feature test, 2 yayasan beda, masing2 buat 1 baris, assert isolasi) |
| Create | `yayasan_id` baris baru otomatis sesuai aktor yang login, BUKAN input form (submit tanpa field itu tetap berhasil, dan tidak bisa dioverride oleh payload request) |
| Delete guard | Guru/Karyawan yang dipakai HANYA di yayasan lain TIDAK memblokir delete di yayasan aktor (regresi dari perilaku lama yang menghitung global) |
| Unique validation | 2 yayasan beda boleh punya baris `nama` yang SAMA PERSIS (tidak lagi unique global); 1 yayasan tidak boleh duplikat nama miliknya sendiri |
| Regresi | Seluruh `JenisKaryawanMasterCrudTest.php`, `JabatanTambahanMasterCrudTest.php`, `JenisKaryawanMasterSeederTest.php`, `GuruJabatanTambahanSeederTest.php`, `JabatanTambahanTest.php` existing tetap hijau |
