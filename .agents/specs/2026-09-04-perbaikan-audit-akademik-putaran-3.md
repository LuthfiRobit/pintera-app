# Spec: Perbaikan Audit Akademik Putaran 3 (Billing Trigger, RPP Guru, Race Condition, Lainnya)

**Tanggal**: 2026-09-04
**Branch**: `akademik-v2`
**Konteks**: Ditemukan lewat audit putaran 3 modul Akademik (dilakukan setelah paket IDOR/RPP-verify/bentrok-jadwal/resolver-precedence selesai). Fokus khusus: audit skeptis kode BARU dari 3 paket sebelumnya untuk cari bug interaksi lintas-task, plus verifikasi independen 2 temuan Minor yang sebelumnya cuma dilaporkan tanpa dicek.

## 1. Keputusan yang Sudah Final (dikonfirmasi lewat brainstorming, jangan tanya ulang)

- **Cakupan final**: #1-6 (bug terkonfirmasi) + #7 **hanya untuk 3 controller non-PPDB** (`GuruController`, `KalenderAkademikController`, `PengaturanAkademikController` — **skip** `JalurPpdbController`/`GelombangPpdbController`, tetap jadi catatan serah-terima) + #8. **Skip #9** (Activitylog mass-update, cuma relevan kalau Opsi B tracking dibangun nanti — tetap ditunda).
- **#1 dan #6 adalah SATU akar masalah, ditambal SATU KALI, bukan dua tambalan terpisah.** Perbaikan HANYA di `JenisTagihanSasaranMatcher.php` (2 titik), **TIDAK menyentuh** `TagihanBillingGenerator.php` maupun menambah guard terpisah di `GenerateTagihanForUpdatedClass.php` — akar masalahnya (matcher tidak pernah filter status siswa) sudah cukup ditutup di satu tempat untuk menutup SEMUA jalur trigger (event `StudentUpdatedClass` maupun cron/generator massal).
- **Keputusan sadar menyentuh file yang berpotensi bentrok dengan `keuangan-v2`**: user eksplisit memilih menyelesaikan semuanya di `akademik-v2` sekarang, menerima risiko konflik merge nanti, daripada menunggu koordinasi dengan sesi Keuangan paralel.
- **#2 (RPP guru fallback)**: Opsi A dipilih — tambah `guru_id` eksplisit tervalidasi di form/`StoreRppRequest` untuk actor tanpa profil Guru sendiri, BUKAN hapus kemampuan admin/staf non-guru membuat RPP.
- **#3, #4, #5, #8**: perbaikan mekanis mengikuti pola yang SUDAH ADA di codebase (lihat detail masing-masing di §2).
- **#7 pakai ulang `ResolveLembagaScopeTrait`** (method BARU di trait yang sama, BUKAN `resolveLembagaId()` yang sudah ada — beda semantik: yang lama untuk CREATE/derive nilai baru, yang baru untuk READ/filter berdasarkan lembaga aktif) — dipakai lintas domain (Guru bukan folder Akademik, tapi logic-nya identik, reuse dianggap lebih baik daripada duplikasi 3x).

## 2. Temuan & Perbaikan

### 2.1. [Paling mendesak, dampak finansial] Root fix: `JenisTagihanSasaranMatcher` tidak filter status siswa

**Masalah**: `resolveTargetSiswa()`/`countTotalSiswaPool()` (baris 20-48) dan `siswaMatchesJenisTagihan()` (baris 63+) tidak pernah mengecek `$siswa->status`. Sejak `UpdateStatusSiswaAction` (paket sebelumnya) meng-null-kan `kelas_id` saat siswa Lulus/Pindah/Keluar, `Siswa::booted()`'s `static::updated()` hook (`app/Models/Siswa.php:150-153`, kode lama tidak disentuh) mendeteksi `wasChanged('kelas_id')` dan memicu event `StudentUpdatedClass` — sebelumnya event ini TIDAK PERNAH terpicu oleh perubahan status (karena `kelas_id` memang tidak pernah berubah saat sekadar ganti status). Listener `GenerateTagihanForUpdatedClass` memanggil `siswaMatchesJenisTagihan()` langsung per-siswa, dan untuk `JenisTagihan` tanpa kriteria sasaran spesifik (`sasaranGrups->isEmpty()` → `return true`, baris 73-75), siswa yang BARU SAJA keluar/lulus/pindah tetap "cocok" dan bisa ditagih.

**Perbaikan** — 2 titik, SEMUA di `app/Domains/Keuangan/Services/JenisTagihanSasaranMatcher.php`:

1. Tambah `use App\Enums\StatusSiswa;` ke import.
2. `resolveTargetSiswa()` dan `countTotalSiswaPool()` — tambah `->where('status', StatusSiswa::Aktif->value)` ke base query (setelah `->where('lembaga_id', ...)`).
3. `siswaMatchesJenisTagihan()` — tambah pengecekan status SEBELUM cek lembaga:
   ```php
   public function siswaMatchesJenisTagihan(Siswa $siswa, JenisTagihan $jenisTagihan): bool
   {
       if ($siswa->status !== StatusSiswa::Aktif) {
           return false;
       }

       if ($siswa->lembaga_id !== $jenisTagihan->lembaga_id) {
           return false;
       }
       // ... sisanya tidak berubah
   }
   ```

**Non-Goals eksplisit**: TIDAK menyentuh `TagihanBillingGenerator.php` (base query bulk-nya sudah otomatis benar begitu `resolveTargetSiswa()` diperbaiki, karena itulah sumber datanya). TIDAK menambah guard terpisah di listener manapun.

### 2.2. [Important] Fallback guru acak di `RppController::store()`

**Masalah**: `RppController.php:147` — `$guruId = $guru ? $guru->id : ($request->input('guru_id') ?: Guru::where('lembaga_id', $kelas->lembaga_id)->value('id'));`. `guru_id` SUDAH diterima secara informal dari `$request->input()` (tanpa validasi apa pun) tapi kalau tidak dikirim, fallback ke guru PERTAMA di lembaga itu (urutan DB, tidak relevan) — `StoreRppRequest::rules()` (baris 25-33) tidak punya rule untuk `guru_id` sama sekali.

**Perbaikan**:
1. `StoreRppRequest.php` — tambah rule: `'guru_id' => ['nullable', 'integer', 'exists:guru,id'],`.
2. Tambah validasi di `withValidator()` (setelah cek kelas/semester yang sudah ada):
   ```php
   $validator->after(function (Validator $validator) {
       // ... cek kelas/semester yang sudah ada tetap di atas ...

       if ($this->user()->guru === null) {
           $guruId = $this->input('guru_id');
           if (! $guruId) {
               $validator->errors()->add('guru_id', 'Guru pengampu wajib dipilih.');

               return;
           }

           $kelasId = $this->input('kelas_id');
           $kelas = $kelasId ? Kelas::find($kelasId) : null;
           $guru = \App\Models\Guru::find($guruId);
           if ($kelas && $guru && $guru->lembaga_id !== $kelas->lembaga_id) {
               $validator->errors()->add('guru_id', 'Guru yang dipilih bukan dari lembaga yang sama dengan kelas ini.');
           }
       }
   });
   ```
3. `RppController::store()` baris 147 — ganti fallback acak:
   ```php
   $guruId = $guru ? $guru->id : (int) $request->input('guru_id');
   ```
   (Tidak perlu `abort(422)` tambahan lagi — `StoreRppRequest` sudah menolak sebelum sampai ke controller kalau `guru_id` kosong/tidak valid untuk actor tanpa profil guru.)
4. **View**: tambah dropdown pilih guru (dari lembaga yang sama) di form create RPP, ditampilkan HANYA kalau actor tidak punya profil Guru sendiri (`auth()->user()->guru === null`) — detail markup ditentukan saat writing-plans (baca struktur view RPP existing dulu).

### 2.3. [Minor, terkonfirmasi] Race condition validasi total bobot Komponen Penilaian

**Masalah**: `CreateKomponenPenilaianAction.php:20-30`/`UpdateKomponenPenilaianAction.php` — `sum('bobot')` lalu `create()`/`save()` tanpa transaksi/lock. Dua request paralel bisa lolos validasi "≤100%" bersamaan.

**Perbaikan** (keputusan final, bukan opsi terbuka): karena ini INSERT baru, tidak selalu ada baris `KomponenPenilaian` existing untuk di-`lockForUpdate()` (kasus baris pertama untuk subjek+semester itu — 0 baris existing tidak bisa dikunci lewat row lock). Solusinya: kunci baris `Semester` (SELALU ada, independen dari berapa banyak `KomponenPenilaian` sudah dibuat) sebagai *advisory lock*, di dalam `DB::transaction()`, SEBELUM `sum('bobot')` dihitung:
```php
public function execute(KomponenPenilaianData $data): KomponenPenilaian
{
    return DB::transaction(function () use ($data) {
        $semester = Semester::where('id', $data->semesterId)->lockForUpdate()->first();

        $existingSum = KomponenPenilaian::where('subjek_type', $data->subjekType)
            ->where('subjek_id', $data->subjekId)
            ->where('semester_id', $data->semesterId)
            ->sum('bobot');

        if (($existingSum + $data->bobot) > 100) {
            $remaining = max(0, 100 - $existingSum);
            throw ValidationException::withMessages([
                'bobot' => "Total bobot melebihi 100%. Sisa bobot yang tersedia untuk subjek ini adalah {$remaining}%.",
            ]);
        }

        // ... sisa logic (assessmentType, KomponenPenilaian::create()) tidak berubah, tetap di dalam closure ini
    });
}
```
Ini menyerialkan SEMUA penulisan Komponen Penilaian untuk 1 semester (lintas subjek) — lebih kasar dari yang sebenarnya diperlukan (idealnya per subjek+semester), tapi sederhana, benar, dan cukup untuk skala penggunaan fitur ini (bukan hot-path frekuensi tinggi). Pola identik diterapkan ke `UpdateKomponenPenilaianAction`.

### 2.4. [Minor] `mata_pelajaran_id` RPP admin-path tanpa re-check tenant

**Masalah**: `StoreRppRequest::toDTO()` baris 82 — `mataPelajaranId: ! empty($validated['mata_pelajaran_id']) ? (int) $validated['mata_pelajaran_id'] : null` — nilai mentah dari `validated()`, `exists:mata_pelajaran,id` di rules() adalah raw DB check TANPA scoping tenant. Beda dengan `kelas_id`/`semester_id` yang di-`findOrFail()` ulang di controller (otomatis kena `TenantScope`).

**Perbaikan**: di `RppController::store()`, untuk jalur admin (`$guru === null`, sebelum baris 154 blok `if ($guru !== null)`), tambah re-fetch tenant-scoped kalau `mata_pelajaran_id` dikirim:
```php
if ($guru === null && $request->filled('mata_pelajaran_id')) {
    $mapel = MataPelajaran::find($request->input('mata_pelajaran_id'));
    abort_if($mapel === null || $mapel->lembaga_id !== $kelas->lembaga_id, 404);
}
```

### 2.5. [Minor, pre-existing] Dropdown kelas lintas tahun ajaran di `SiswaController`

**Masalah**: `SiswaController::create()` (baris 81) DAN `edit()` (baris 142) — keduanya `Kelas::orderBy('nama')->get()`, tidak difilter ke tahun ajaran aktif (beda dari `index()` baris 45-47 yang sudah benar).

**Perbaikan**: samakan pola `index()` di KEDUA method:
```php
$tahunAjaranAktif = TahunAjaran::where('status_aktif', true)->first(); // scoped otomatis via TenantScope
$kelasList = $tahunAjaranAktif
    ? Kelas::where('tahun_ajaran_id', $tahunAjaranAktif->id)->orderBy('nama')->get()
    : collect();
```

### 2.6. [Important, impact tinggi/likelihood rendah] Session-staleness — 3 controller non-PPDB

**Masalah**: `GuruController::resolveLembagaId()` (baris 257-263), `KalenderAkademikController.php` (baris 44, 73, 111), `PengaturanAkademikController.php` (baris 29, 58) — semuanya baca `session('active_lembaga_id')` langsung untuk actor yayasan tanpa re-verifikasi kepemilikan yayasan di titik pakai (beda dari pola yang sudah benar di `ResolveLembagaScopeTrait` untuk `KurikulumAssignment`/`FaseDefaultMapping`).

**Perbaikan**: tambah method BARU (bukan `resolveLembagaId` yang sudah ada, beda semantik) di `app/Domains/Akademik/Support/ResolveLembagaScopeTrait.php`:
```php
private function resolveActiveLembagaId(User $actor): ?int
{
    if ($actor->lembaga_id !== null) {
        return $actor->lembaga_id;
    }

    $lembagaId = session('active_lembaga_id');
    if ($lembagaId === null) {
        return null;
    }

    if ($actor->widestScopeLevel() === 'yayasan') {
        $milikYayasan = Lembaga::where('id', $lembagaId)->where('yayasan_id', $actor->yayasan_id)->exists();

        return $milikYayasan ? $lembagaId : null;
    }

    return $lembagaId;
}
```
Dipakai di ketiga controller (`use ResolveLembagaScopeTrait;`), menggantikan pola `$request->user()->lembaga_id ?? session('active_lembaga_id')` / method `resolveLembagaId(Request)` GuruController — kalau hasil `resolveActiveLembagaId()` adalah `null`, guard "lembaga aktif tidak valid" yang SUDAH ADA di masing-masing controller (mis. `KalenderAkademikController.php:40`, `PengaturanAkademikController.php:24,46`) otomatis menangkapnya (null dianggap sama seperti "belum pilih lembaga aktif").

**Catatan nama method**: `GuruController` punya method PRIVATE bernama SAMA `resolveLembagaId` (signature beda, `Request` bukan `User`) — kalau `use ResolveLembagaScopeTrait;` ditambahkan di sana, akan BENTROK nama dengan `resolveLembagaId(User, ?int)` milik trait (PHP tidak overload berdasar signature). Solusi: method GuruController TETAP bernama `resolveLembagaId(Request $request)` (tidak diubah), isinya diganti jadi 1 baris `return $this->resolveActiveLembagaId($request->user());` — tidak ada konflik nama karena `resolveActiveLembagaId` adalah nama BARU yang tidak dipakai GuruController sebelumnya.

### 2.7. [Kosmetik] `ProsesKenaikanKelasAction`'s cabang `lulus` tidak isi `kelas_terakhir_id`

**Masalah**: `ProsesKenaikanKelasAction.php:42-49` — mass-update `status` + null-kan `kelas_id`, tidak mengisi `kelas_terakhir_id`. Beda dengan jalur manual via `UpdateStatusSiswaAction` yang sudah benar snapshot. Akibatnya siswa lulus MASSAL tidak dapat badge "(kelas terakhir)" di halaman Siswa.

**Perbaikan**:
```php
if ($aksi['tindakan'] === 'lulus') {
    Siswa::where('kelas_id', $kelasLama->id)->update([
        'status' => StatusSiswa::Lulus->value,
        'kelas_terakhir_id' => DB::raw('kelas_id'),
        'kelas_id' => null,
    ]);

    continue;
}
```
**Catatan teknis**: MySQL mengevaluasi assignment di klausa `SET` berurutan kiri-ke-kanan dalam SATU statement `UPDATE` — `kelas_terakhir_id = kelas_id` dieksekusi SEBELUM `kelas_id = NULL` pada baris yang sama, jadi nilai lama `kelas_id` tersalin dengan benar sebelum di-null-kan. `DB::raw('kelas_id')` WAJIB ditulis PERSIS di posisi itu dalam array (sebelum `'kelas_id' => null`) supaya urutan SET clause yang dihasilkan Laravel benar.

## 3. Non-Goals

- `#7` untuk `JalurPpdbController`/`GelombangPpdbController` — SKIP, tetap catatan serah-terima.
- `#9` (Activitylog mass-update) — SKIP, ditunda.
- `TagihanBillingGenerator.php` — TIDAK disentuh, root fix cukup di `JenisTagihanSasaranMatcher.php`.
- Perubahan pada modul Keuangan di luar `JenisTagihanSasaranMatcher.php` — tidak ada.

## 4. Test Plan

| # | Area | Skenario |
|---|---|---|
| 1 | `JenisTagihanSasaranMatcher` | `siswaMatchesJenisTagihan()` return `false` untuk siswa non-aktif meski `JenisTagihan` tanpa kriteria sasaran (sasaranGrups kosong). |
| 2 | `JenisTagihanSasaranMatcher` | `resolveTargetSiswa()`/`countTotalSiswaPool()` exclude siswa non-aktif dari hasil. |
| 3 | End-to-end (regresi persis skenario awal) | Deaktivasi siswa via `UpdateStatusSiswaAction` (status → Keluar) untuk siswa yang match `JenisTagihan` tanpa kriteria sasaran → assert `StudentUpdatedClass` terpicu (opsional) TAPI **tidak ada `Tagihan` baru dibuat** untuk siswa itu setelahnya. |
| 4 | `StoreRppRequest`/`RppController::store()` | Actor tanpa profil Guru, tidak kirim `guru_id` → `assertSessionHasErrors('guru_id')`, RPP tidak dibuat. |
| 5 | Sama | Actor tanpa profil Guru, kirim `guru_id` valid (lembaga sama) → RPP dibuat dengan `guru_id` itu. |
| 6 | Sama | Actor tanpa profil Guru, kirim `guru_id` milik lembaga LAIN → `assertSessionHasErrors('guru_id')`, ditolak. |
| 7 | Sama | Actor DENGAN profil Guru sendiri → tetap jalan seperti sebelumnya (regresi, `guru_id` tidak relevan). |
| 8 | `CreateKomponenPenilaianAction`/`UpdateKomponenPenilaianAction` | Behavioral: 2 pemanggilan sekuensial cepat yang totalnya akan melebihi 100% tetap konsisten ditolak pada pemanggilan kedua (bukti lock/transaksi bekerja, bukan regresi ke kondisi lolos keduanya). |
| 9 | `RppController::store()` | Jalur admin (guru null), `mata_pelajaran_id` milik lembaga LAIN → 404. |
| 10 | `SiswaController::create()`/`edit()` | `kelasList` cuma berisi kelas dari tahun ajaran aktif. |
| 11-13 | `GuruController`/`KalenderAkademikController`/`PengaturanAkademikController` | Masing-masing 1 test: actor yayasan dengan `active_lembaga_id` stale (lembaga di luar yayasannya) → ditolak/diarahkan ke pesan "pilih lembaga aktif", BUKAN lolos diam-diam memakai lembaga asing. |
| 14 | `ProsesKenaikanKelasAction` | Siswa lulus lewat Kenaikan Kelas MASSAL → assert `kelas_terakhir_id` terisi benar (bukan null), `kelas_id` null, status Lulus. |
