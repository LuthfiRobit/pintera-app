# Spec: Siklus Hidup `kelas_id` Siswa Saat Status Berubah

**Tanggal**: 2026-09-03
**Branch**: `akademik-v2` (lanjut di branch yang sama, tidak pindah branch)
**Konteks**: Ditemukan saat audit bisnis lanjutan fitur Kenaikan Kelas (lihat `.agents/logs/2026-09-03-audit-akademik-perbaikan.md`, bagian "Audit Bisnis Lanjutan"). `SiswaController::updateStatus()` tidak pernah membersihkan `kelas_id` siswa saat status berubah ke `Pindah`/`Keluar`/`Lulus`, menyebabkan siswa yang sudah tidak aktif tetap "menempel" di kelas lama dan ikut terhitung di berbagai fitur yang query berdasarkan `kelas_id`. Salah satu titiknya (`SubmitPengajuanRaporAction`) sudah terbukti jadi **bug produksi aktif** (memblokir submit rapor wali kelas) dan sudah ditambal terpisah, minimal dan terisolasi, sebelum spec ini ditulis (lihat commit terkait di handoff log Kenaikan Kelas). Spec ini menutup akar masalahnya secara permanen.

## 1. Keputusan yang Sudah Final (dikonfirmasi lewat brainstorming, jangan tanya ulang)

- **Cakupan**: spec ini HANYA menutup siklus hidup `kelas_id` (Kelompok A). Validasi tingkat Kenaikan Kelas + konsep "siswa tinggal kelas" (Kelompok B) adalah spec terpisah, tidak digabung — independen secara teknis dan butuh keputusan bisnis sendiri yang bisa memakan waktu.
- **Ketiga status non-aktif diperlakukan SAMA**: `Lulus`, `Pindah`, `Keluar` semuanya membersihkan `kelas_id` (snapshot dulu ke `kelas_terakhir_id`) — ini juga menyeragamkan jalur Lulus-manual (sebelumnya tidak null-kan `kelas_id`) dengan jalur Lulus-via-Kenaikan-Kelas (sebelumnya sudah null-kan).
- **Reversal otomatis**: kalau status dikembalikan ke `Aktif`, `kelas_id` OTOMATIS dipulihkan dari `kelas_terakhir_id`, lalu `kelas_terakhir_id` di-null-kan lagi. Alasan: kondisi "Aktif tanpa kelas" adalah kegagalan SENYAP (siswa hilang dari semua fitur berbasis kelas tanpa tanda visual), sedangkan "kelas yang dipulihkan mungkin sudah usang" adalah kegagalan KELIHATAN (gampang dikoreksi manual lewat halaman edit Siswa yang sudah ada).
- **Idempotency**: kalau status target sama dengan status sekarang, tidak ada snapshot/restore yang dijalankan sama sekali.
- **CHECK constraint di database**: `CHECK (status = 'aktif' OR kelas_id IS NULL)` pada tabel `siswa` — dikonfirmasi didukung MySQL 8.0.30 (versi project ini). Ini membuat invariant terjamin di level data, bukan cuma konvensi kode yang bisa dilanggar kode masa depan.
- **Backfill wajib, sinkron (bukan queued/chunked)**: dicek langsung ke database dev — 0 dari 336 siswa berstatus non-aktif hari ini, jadi skala backfill kecil. 1 statement SQL sinkron cukup, tidak perlu job/queue seperti pola di modul Keuangan (yang menangani ratusan/ribuan baris tagihan).
- **Urutan WAJIB di dalam migration**: backfill (bersihkan data existing) HARUS jalan SEBELUM `ADD CONSTRAINT` — MySQL memvalidasi seluruh baris existing terhadap CHECK constraint baru saat `ALTER TABLE`, kalau dibalik migration akan gagal begitu ada baris yang melanggar.
- **Arsitektur**: logic dipindah dari inline `SiswaController::updateStatus()` ke Action baru `App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction`, sesuai konvensi proyek yang sudah direkam (`.ai/rules/controllers.md`: *state-transition endpoints inject a Domain Action*) — sekaligus membetulkan inkonsistensi arsitektur yang sudah ada sebelum spec ini.
- **`kelas_terakhir_id`**: kolom baru `BIGINT UNSIGNED NULL` di tabel `siswa`, dengan FK ke `kelas.id` `ON DELETE SET NULL` — menyamai definisi `kelas_id` yang sudah ada (dikonfirmasi lewat schema: `kelas_id` sudah punya FK asli dengan `ON DELETE SET NULL`, bukan kolom polos seperti dugaan awal).
- **Branch**: tetap di `akademik-v2`, tidak dipindah ke branch baru.
- **Accessor terpusat, bukan 3 logic fallback terpisah**: model `Siswa` mendapat 1 accessor (`getKelasEfektifAttribute()`, dipanggil sebagai `$siswa->kelas_efektif` — mengikuti pola magic-accessor legacy yang SUDAH dipakai konsisten di model ini, bukan `Attribute` class) yang return `$this->kelas ?? $this->kelasTerakhir`. **Dipakai di KETIGA tempat** yang butuh tampilkan kelas siswa non-aktif: `RaporPdfDataBuilder`, `_daftar.blade.php`, `profil.blade.php` — bukan logic fallback terpisah di masing-masing tempat yang bisa menyimpang seiring waktu.

## 2. Urutan Implementasi Wajib

Audit tambahan (dilakukan sebelum spec ini dianggap siap ditulis jadi plan) menemukan 2 titik yang akan RUSAK begitu migration (§4) di-deploy, kalau tidak ditambal DULUAN. Urutan berikut WAJIB diikuti persis:

1. **Perbaikan pra-migration #1**: guard validasi di `SiswaController::validateSiswa()` (§3.1).
2. **Perbaikan pra-migration #2**: perbaiki `tests/Unit/Services/SesiPembelajaranGeneratorTest.php:187` (§3.2).
3. **Migration**: kolom `kelas_terakhir_id` + backfill + CHECK constraint (§4).
4. **`UpdateStatusSiswaAction`** (§5).
5. **Accessor `kelas_efektif`** di model `Siswa` + terapkan ke 3 konsumen (§6).
6. **Frontend**: guard form edit + label "(kelas terakhir)" (§7).
7. Sisa test regresi untuk titik "gratis" (§9).

Kalau urutan ini dibalik (mis. migration dijalankan sebelum 2 perbaikan pra-migration), test suite akan merah dan/atau form edit siswa non-aktif akan crash 500 di produksi — lihat bukti di §3.

## 3. Perbaikan Pra-Migration (WAJIB PALING AWAL)

### 3.1. Guard validasi `kelas_id` untuk siswa non-aktif di `SiswaController`

**Masalah** (ditemukan lewat audit, dibuktikan dengan pembacaan kode langsung): `SiswaController::update()` (baris 178-180) mengizinkan mengubah `kelas_id` lewat form edit profil biasa — field ini (`_form.blade.php:73`, `<x-select name="kelas_id">`) SELALU ditampilkan sebagai editable, TANPA guard berdasarkan status siswa saat ini. `validateSiswa()` (baris 279+) memvalidasi `kelas_id` cuma `['nullable', 'integer']`, tidak mengecek status siswa yang sedang diedit sama sekali. Kalau admin membuka form edit untuk siswa yang SUDAH `Lulus`/`Pindah`/`Keluar` dan submit `kelas_id` non-kosong, `update()` akan set `kelas_id` sementara `status` tetap non-aktif — melanggar CHECK constraint baru begitu di-deploy, menghasilkan **500 error mentah dari database**, bukan pesan validasi.

**Perbaikan**: tambah validasi di `validateSiswa()` — kalau `$current` (siswa yang diedit) ada DAN `$current->status !== StatusSiswa::Aktif` DAN `$data['kelas_id']` tidak kosong, gagalkan validasi:

```php
if ($current && $current->status !== StatusSiswa::Aktif && ! empty($data['kelas_id'])) {
    throw ValidationException::withMessages([
        'kelas_id' => 'Tidak bisa menempatkan kelas untuk siswa berstatus non-aktif. Ubah status ke Aktif terlebih dahulu.',
    ]);
}
```

**Test**: submit `update()` dengan `kelas_id` terisi untuk siswa berstatus `Keluar` — assert `ValidationException`/`assertSessionHasErrors('kelas_id')`, assert `kelas_id` siswa TIDAK berubah di database.

### 3.2. Perbaiki `SesiPembelajaranGeneratorTest.php:187`

**Masalah**: test ini SENGAJA membuat kondisi `Siswa::factory()->create(['kelas_id' => $kelas->id, 'status' => 'lulus'])` — kombinasi yang setelah spec ini deploy jadi MUSTAHIL secara struktural (dijamin CHECK constraint). Baris factory ini sendiri akan melempar DB exception begitu constraint aktif, sebelum sempat sampai ke assertion.

**Perbaikan**: ubah konstruksi test supaya lewat alur yang benar — buat siswa `Aktif` dulu di kelas itu, panggil `UpdateStatusSiswaAction` untuk transisi ke `Lulus` (otomatis null-kan `kelas_id` sesuai desain baru di §5), baru jalankan `SesiPembelajaranGenerator`. Assertion akhir tetap sama (siswa itu tidak dapat presensi ter-generate), tapi sekarang menguji alur nyata end-to-end:

```php
it('excludes non-aktif siswa from auto-generated presensi', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasDenganJadwal();
    $siswaLulus = Siswa::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'kelas_id' => $kelas->id, 'status' => 'aktif']);
    app(UpdateStatusSiswaAction::class)->execute($siswaLulus, StatusSiswa::Lulus);

    $hasil = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect($hasil->first()->presensi()->count())->toBe(3);
    expect($hasil->first()->presensi()->where('siswa_id', $siswaLulus->id)->exists())->toBeFalse();
});
```

## 4. Perubahan Database (1 Migration)

Isi migration, urutan HARUS seperti ini:

1. **Tambah kolom**: `kelas_terakhir_id` (`bigint unsigned`, nullable) + foreign key ke `kelas.id`, `onDelete('set null')`.
2. **Backfill data existing** (raw SQL, 1 statement):
   ```sql
   UPDATE siswa
   SET kelas_terakhir_id = kelas_id, kelas_id = NULL
   WHERE status != 'aktif' AND kelas_id IS NOT NULL;
   ```
   Backfill ini murni snapshot ID — TIDAK memvalidasi keberadaan Kelas (dikonfirmasi: `KelasController` tidak punya `destroy()`, Kelas tidak pernah bisa dihapus lewat UI aplikasi ini hari ini; 0 baris orphan `kelas_id` ditemukan saat audit).
3. **Tambah CHECK constraint** (via `DB::statement()`, Laravel Schema Builder tidak punya method fluent untuk CHECK):
   ```sql
   ALTER TABLE siswa ADD CONSTRAINT chk_siswa_kelas_id_null_saat_nonaktif
       CHECK (status = 'aktif' OR kelas_id IS NULL);
   ```

**Down migration**: drop constraint, drop FK, drop kolom — tidak perlu me-restore `kelas_id` dari `kelas_terakhir_id` saat rollback (rollback migration ini secara alami berarti fitur ini dibatalkan sepenuhnya, bukan operasi yang butuh dipertahankan datanya).

## 5. `UpdateStatusSiswaAction`

**File baru**: `app/Domains/Akademik/Actions/Siswa/UpdateStatusSiswaAction.php`

```php
final class UpdateStatusSiswaAction
{
    public function execute(Siswa $siswa, StatusSiswa $statusBaru): Siswa
    {
        return DB::transaction(function () use ($siswa, $statusBaru) {
            if ($siswa->status === $statusBaru) {
                return $siswa;
            }

            if ($statusBaru === StatusSiswa::Aktif) {
                $siswa->kelas_id = $siswa->kelas_terakhir_id;
                $siswa->kelas_terakhir_id = null;
            } elseif ($siswa->status === StatusSiswa::Aktif) {
                $siswa->kelas_terakhir_id = $siswa->kelas_id;
                $siswa->kelas_id = null;
            }

            $siswa->status = $statusBaru;
            $siswa->save();

            if ($siswa->user_id) {
                $siswa->user()->update(['is_active' => $statusBaru === StatusSiswa::Aktif]);
            }

            return $siswa;
        });
    }
}
```

Catatan desain: transisi non-aktif → non-aktif lain (mis. `Pindah` → `Keluar`) tidak menyentuh `kelas_id`/`kelas_terakhir_id` sama sekali (keduanya sudah dalam keadaan benar dari transisi sebelumnya) — hanya kolom `status` yang berubah.

**`SiswaController::updateStatus()`** diubah untuk memanggil action ini (method-injected), validasi input (`in:aktif,lulus,pindah,keluar`) tetap di controller seperti sekarang.

## 6. Accessor `kelas_efektif` — Dipakai di 3 Konsumen

**Model**: `app/Models/Siswa.php` — tambah relasi baru dan 1 accessor:

```php
public function kelasTerakhir(): BelongsTo
{
    return $this->belongsTo(Kelas::class, 'kelas_terakhir_id');
}

public function getKelasEfektifAttribute(): ?Kelas
{
    return $this->kelas ?? $this->kelasTerakhir;
}
```

Dipakai (menggantikan akses `$siswa->kelas` langsung) di:

1. **`RaporPdfDataBuilder.php:45`**:
   ```php
   $kelas = $siswa->kelas_efektif;
   abort_if($kelas === null, 404);
   ```
2. **`resources/views/admin/siswa/_daftar.blade.php:92-93`**:
   ```blade
   @if ($siswa->kelas_efektif)
       {{ $siswa->kelas_efektif->nama }}
       @if (! $siswa->kelas && $siswa->kelasTerakhir)
           <span class="text-xs text-gray-400">(kelas terakhir)</span>
       @endif
   @endif
   ```
3. **`resources/views/admin/siswa/tabs/profil.blade.php:80`**:
   ```blade
   {{ $siswa->kelas_efektif?->nama ?? 'Belum ada kelas' }}
   @if (! $siswa->kelas && $siswa->kelasTerakhir)
       <span class="text-xs text-gray-400">(kelas terakhir)</span>
   @endif
   ```

**Label "(kelas terakhir)"**: ditambahkan supaya admin tidak bingung kenapa siswa yang sudah `Keluar`/`Pindah`/`Lulus` masih "punya kelas" di tampilan — sinyal visual bahwa ini snapshot historis, bukan penempatan aktif.

**Eager loading**: query yang memberi data ke `_daftar.blade.php` (listing siswa, kemungkinan di `SiswaController::index()`) harus ditambah `->with(['kelas', 'kelasTerakhir'])` supaya accessor tidak memicu N+1 query per baris siswa di daftar.

**Known-limitation, didokumentasikan (bukan diperbaiki di spec ini)**: `kelas_terakhir_id` cuma menyimpan 1 kelas "terakhir diketahui". Kalau siswa pernah pindah kelas beberapa kali sebelum status berubah jadi non-aktif, cetak ulang rapor untuk semester LAMA (bukan semester terakhir) akan tetap menampilkan kelas TERAKHIR, bukan kelas yang benar untuk semester itu. Ini bukan regresi baru — `$siswa->kelas` hari ini pun sudah selalu mengambil kelas TERKINI siswa untuk semester manapun yang dicetak (tidak historis per-semester).

## 7. Frontend — Perubahan UI Eksplisit

1. **Guard di `_form.blade.php`** (dipakai bersama create + edit): field `kelas_id` (baris 71-81) di-disable dan diberi pesan jelas saat mengedit siswa yang berstatus non-aktif — mencerminkan validasi backend di §3.1 supaya admin tidak dikagetkan error setelah submit:
   ```blade
   @php $siswaNonAktif = $siswa && $siswa->status !== \App\Enums\StatusSiswa::Aktif; @endphp
   <div class="sm:col-span-12">
       <x-input-label value="Penempatan Kelas" />
       <x-select name="kelas_id" :disabled="$siswaNonAktif" class="mt-1.5 block w-full transition duration-150" :error="$errors->has('kelas_id')">
           <option value="">— Belum ditempatkan —</option>
           @foreach ($kelasList as $kelas)
               <option value="{{ $kelas->id }}" @selected($val('kelas_id') == $kelas->id)>{{ $kelas->nama }}</option>
           @endforeach
       </x-select>
       @if ($siswaNonAktif)
           <x-input-hint>Siswa berstatus non-aktif — ubah status ke Aktif dulu untuk menempatkan kelas.</x-input-hint>
       @else
           <x-input-hint>(Opsional)</x-input-hint>
       @endif
       <x-input-error :messages="$errors->get('kelas_id')" class="mt-1.5" />
   </div>
   ```
   Field yang `disabled` tidak ikut ter-submit oleh browser — jadi selaras otomatis dengan guard backend (kalaupun field di-enable paksa lewat devtools, backend §3.1 tetap menolak).
2. **`_daftar.blade.php` dan `profil.blade.php`** — pakai `kelas_efektif` (§6), verifikasi manual (screenshot) bahwa siswa non-aktif menampilkan nama kelas terakhirnya dengan label "(kelas terakhir)", bukan "Belum ada kelas".

## 8. Titik Konsumen — Perlakuan per Kelompok

**5 titik "gratis"** (tidak butuh perubahan kode — begitu `kelas_id` null, query `where('kelas_id', ...)` otomatis tidak match):
- `ProsesKenaikanKelasAction`
- `RaporCalculationService.php:27`
- `Guru\RaporController.php:94` (listing biasa)
- `Guru\RaporController.php:143` (navigasi next/previous siswa)
- `DashboardStatsService.php:138`

**2 titik yang SUDAH ditambal sebelumnya** (di luar spec ini, referensi saja):
- `SubmitPengajuanRaporAction.php:37` — sudah ditambal minimal+terisolasi sebelum spec ini ditulis.
- `PresensiAggregationService.php:20` — sudah benar sejak awal (pola rujukan).

**3 titik pakai accessor `kelas_efektif`** (§6): `RaporPdfDataBuilder`, `_daftar.blade.php`, `profil.blade.php`.

**2 titik perbaikan pra-migration** (§3): guard validasi `SiswaController`, fix `SesiPembelajaranGeneratorTest`.

**1 titik di luar scope, catatan serah-terima** (lihat §10 Non-Goals): `JenisTagihanSasaranMatcher` + `TagihanBillingGenerator`.

## 9. Non-Goals

- **Validasi tingkat Kenaikan Kelas + konsep "siswa tinggal kelas"** — spec/topik terpisah (Kelompok B), tidak digarap di sini.
- **`JenisTagihanSasaranMatcher::resolveTargetSiswa()`/`countTotalSiswaPool()` dan `TagihanBillingGenerator`** — base query-nya tidak filter status SAMA SEKALI (independen dari masalah `kelas_id`; siswa non-aktif dengan kriteria sasaran non-kelas, mis. "semua siswa", tetap akan kena tagihan baru walau `kelas_id` sudah null). **TIDAK disentuh di spec ini** karena file yang sama sedang aktif digarap di spec Keuangan lain (`keuangan-v2`, dikonfirmasi lewat `git log` — ada histori kerja baru-baru ini di area billing dan referensi eksplisit ke file ini di brainstorming redesain form Jenis Tagihan). **Catatan serah-terima untuk direlay ke spec/sesi Keuangan yang berjalan paralel itu**: base query `resolveTargetSiswa()`/`countTotalSiswaPool()` perlu ditambah `->where('status', StatusSiswa::Aktif->value)`, terpisah dan tidak bergantung pada mekanisme `kelas_terakhir_id` di spec ini. Juga dicatat di kickoff (`.agents/kickoff/`).
- **Akurasi kelas historis per-semester** di `RaporPdfDataBuilder`/`kelas_efektif` — batasan lama (lihat §6), tidak diperbaiki.
- **FK `kelas_terakhir_id` bisa hilang diam-diam kalau fitur hapus-Kelas dibangun nanti** — CHECK constraint tidak menyentuh kolom ini. Dicatat, tidak mungkin terjadi hari ini (`KelasController` tidak punya `destroy()`).
- **Migrasi/pindah branch** — tetap di `akademik-v2`.
- **Tidak ada perubahan pada halaman/endpoint lain** di luar yang disebutkan eksplisit di atas.

## 10. Test Plan

| # | Area | Skenario |
|---|---|---|
| 1 | `SiswaController::update()` guard (§3.1) | Submit `kelas_id` untuk siswa berstatus `Keluar` — assert `assertSessionHasErrors('kelas_id')`, `kelas_id` tidak berubah di database. |
| 2 | `SesiPembelajaranGeneratorTest` (§3.2) | Diperbaiki memakai `UpdateStatusSiswaAction`, assertion tetap sama (lihat kode di §3.2). |
| 3 | `UpdateStatusSiswaAction` | Aktif → Keluar: `kelas_terakhir_id` = `kelas_id` lama, `kelas_id` jadi null. |
| 4 | `UpdateStatusSiswaAction` | Keluar → Aktif: `kelas_id` pulih dari `kelas_terakhir_id`, `kelas_terakhir_id` jadi null lagi. |
| 5 | `UpdateStatusSiswaAction` | **Siklus ganda**: Aktif→Keluar→Aktif→Keluar lagi (dengan `kelas_id` yang BERBEDA di siklus kedua) — assert `kelas_terakhir_id` di akhir siklus kedua adalah snapshot dari kelas SAAT ITU, bukan sisa data basi dari siklus pertama. |
| 6 | `UpdateStatusSiswaAction` | **Idempotency**: panggil dengan status target = status sekarang — assert `kelas_id`/`kelas_terakhir_id` tidak berubah. |
| 7 | CHECK constraint | Percobaan insert/update mentah yang melanggar invariant — assert exception database dilempar. |
| 8 | Migration | Seed siswa non-aktif dengan `kelas_id` terisi sebelum backfill — assert `kelas_terakhir_id` terisi benar, `kelas_id` null setelahnya. |
| 9 | Accessor `kelas_efektif` | Siswa aktif → return `kelas`; siswa non-aktif dengan `kelas_terakhir_id` terisi → return `kelasTerakhir`; siswa tanpa keduanya → return `null`. |
| 10 | `RaporPdfDataBuilder` | Cetak rapor untuk siswa berstatus Keluar — assert tidak crash, data kelas terbaca lewat `kelas_efektif`. |
| 11 | Kelompok "gratis" — 1 representatif | `ProsesKenaikanKelasAction`: kelas dengan siswa aktif+keluar campur — assert siswa keluar tidak ikut naik/lulus. **Sertakan komentar eksplisit di kode test**: `RaporCalculationService` dan `DashboardStatsService` punya pola query identik, dianggap terbukti benar oleh test ini + invariant CHECK constraint, sengaja tidak diduplikasi. |
| 12 | `Guru\RaporController` (listing) | Kelas dengan siswa aktif+keluar campur — assert siswa keluar tidak muncul di listing. |
| 13 | `Guru\RaporController` (navigasi next/previous) | Kelas dengan siswa aktif+keluar campur dalam urutan tertentu — assert navigasi cuma menghitung siswa aktif, tidak meleset index. |

Test #12 dan #13 WAJIB terpisah dan eksplisit (bukan diringkas jadi 1) karena navigasi next/previous punya logic tambahan (index-based) di luar sekadar filter WHERE.
