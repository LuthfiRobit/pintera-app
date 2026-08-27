# Audit Sistematis Akademik Tahap 2 — Kelompok A (Kritis) — Design Spec

**Tanggal**: 2026-08-27
**Branch**: `akademik-v2`
**Konteks**: Lanjutan audit sistematis Boost-assisted terhadap area Akademik yang belum pernah diaudit dengan lensa "dinamis lintas kurikulum/jenjang" (Kenaikan Kelas, Jadwal Pelajaran/Pola Jam/Kalender Akademik, RPP, Ekstrakurikuler, konsistensi `KurikulumAssignment`/`FaseDefaultMapping`, notifikasi Akademik). Dari 10 temuan gabungan, 3 dikategorikan **kritis** dan dikerjakan lebih dulu sebagai Kelompok A. Kelompok B (Kenaikan Kelas UX safety-net) dan Kelompok C (RPP reporting + test coverage) menyusul terpisah. Poin #10 (tidak ada notifikasi Akademik sama sekali) dicatat sebagai backlog fitur, bukan bagian fix ini.

---

## 1. Latar Belakang & Masalah

### 1.1 — Widget "Jadwal Hari Ini" guru mencampur jadwal lintas tahun ajaran/semester

`app/Http/Controllers/Admin/DashboardController.php:51-56`:
```php
$jadwalHariIni = $user->guru === null
    ? collect()
    : JadwalPelajaran::where('guru_id', $user->guru->id)
        ->whereHas('jamPelajaran', fn ($q) => $q->where('hari', $hariIni))
        ->with(['kelas', 'mataPelajaran', 'jamPelajaran'])
        ->get();
```
Tidak ada filter `semester_id`/tahun ajaran aktif. `jadwal_pelajaran` TIDAK dihapus di antar tahun ajaran (dan memang **tidak boleh** dihapus — lihat §1.1.1) sehingga seorang guru yang mengajar hari yang sama di lebih dari satu tahun ajaran akan melihat baris jadwal dari tahun-tahun sebelumnya tercampur dengan jadwal aktif di widget "hari ini".

**§1.1.1 — Kenapa tidak boleh menghapus jadwal lama**: `sesi_pembelajaran.jadwal_pelajaran_id` punya `cascadeOnDelete()` (`database/migrations/2026_07_25_120000_create_sesi_pembelajaran_table.php:14`), dan `presensi` terikat ke `sesi_pembelajaran`. Menghapus baris `jadwal_pelajaran` lama akan menghapus permanen seluruh riwayat sesi pembelajaran + presensi siswa untuk kelas tersebut. **Keputusan eksplisit**: jadwal lama TETAP tersimpan sebagai riwayat sah — perbaikan HANYA di level query (filter), tidak ada penghapusan data.

### 1.2 — `kelas.kurikulum` dan `kelas.fase_id` adalah snapshot beku tanpa mekanisme koreksi

`kelas.kurikulum` diisi sekali oleh `CreateKelasAction` via `KurikulumAssignmentResolver::resolve()`, dan `kelas.fase_id` via `FaseDefaultResolver::resolve()` — keduanya HANYA dipanggil saat kelas dibuat. `UpdateKelasAction` tidak pernah memanggil ulang resolver ini meski `tingkat`/`tahun_ajaran_id` kelas diubah. `UpdateKurikulumAssignmentAction`/`UpdateFaseDefaultMappingAction` juga tidak pernah men-cascade perubahan ke `kelas` yang sudah ada.

Perilaku "snapshot, tidak re-sync otomatis" ini **disengaja dan sudah di-test** (`tests/Feature/Akademik/KelasKurikulumSnapshotTest.php`) — mencegah perubahan kebijakan kurikulum di tengah tahun ajaran diam-diam mengubah kelas yang sudah berjalan. Yang HILANG: cara admin memperbaiki drift ketika sumber datanya salah dari awal (typo/kesalahan input di `kurikulum_assignment`/`fase_default_mapping`), bukan perubahan kebijakan yang disengaja.

### 1.3 — Ekskul di rapor cetak resmi tidak divalidasi terhadap master data

`catatan_wali_kelas.ekstrakurikuler` (JSON) diisi via form teks bebas (`resources/views/portals/guru/rapor/catatan/edit.blade.php:63-71`, Alpine.js repeatable rows dengan `<input type="text">`). `StoreCatatanWaliKelasRequest::rules()` hanya memvalidasi `ekstrakurikuler.*.nama` sebagai `string|max:255` — tidak ada referensi ke `ekstrakurikuler_lembaga` (daftar ekskul resmi ber-SK milik lembaga). Wali kelas bisa mengetik nama ekskul apa saja (typo, ekskul fiktif/tidak terdaftar) dan itu langsung tercetak di dokumen rapor resmi siswa.

---

## 2. Keputusan Desain

### 2.1 — Fix Jadwal Hari Ini: filter query saja, TIDAK ADA penghapusan data

`DashboardController.php` menambah resolusi semester aktif untuk lembaga guru, lalu memfilter `$jadwalHariIni` berdasarkan itu:

```php
$semesterAktif = $user->guru === null
    ? null
    : Semester::where('lembaga_id', $user->guru->lembaga_id)->where('status_aktif', true)->first();

$jadwalHariIni = ($user->guru === null || $semesterAktif === null)
    ? collect()
    : JadwalPelajaran::where('guru_id', $user->guru->id)
        ->where('semester_id', $semesterAktif->id)
        ->whereHas('jamPelajaran', fn ($q) => $q->where('hari', $hariIni))
        ->with(['kelas', 'mataPelajaran', 'jamPelajaran'])
        ->get();
```

Tambah `use App\Models\Semester;` di import.

**Guard default untuk consumer masa depan**: tambah `scopeAktif(Builder $query, ?int $lembagaId = null)` pada model `JadwalPelajaran` (`app/Models/JadwalPelajaran.php`) yang melakukan filter setara (join ke semester aktif lembaga terkait, atau terima `$semesterId` eksplisit — lihat detail di plan). Scope ini didokumentasikan via PHPDoc bahwa consumer baru WAJIB memakainya kecuali punya alasan eksplisit untuk melihat lintas semester (mis. laporan histori).

Tidak ada perubahan pada `ProsesKenaikanKelasAction` — jadwal lama tetap tersimpan apa adanya sebagai riwayat sah.

### 2.2 — Fix drift kurikulum/fase: aksi resync manual per lembaga+tahun ajaran

Halaman baru "Cek & Perbaiki Kurikulum/Fase Kelas" dapat diakses dari halaman index `KurikulumAssignmentController`/`FaseDefaultMappingController` (tombol/link), scoped ke satu `lembaga_id` + `tahun_ajaran_id` yang dipilih admin.

**Alur:**
1. Admin memilih lembaga + tahun ajaran (dropdown, hormat ke scope platform/yayasan/lembaga yang sudah ada di kedua controller ini).
2. Sistem query semua `Kelas` di kombinasi itu, lalu untuk tiap kelas hitung nilai LIVE via `KurikulumAssignmentResolver::resolve()` dan `FaseDefaultResolver::resolve()` (bungkus exception `KurikulumAssignmentNotFoundException` sbg "tidak ada assignment cocok, dilewati") dan bandingkan dengan nilai tersimpan di `kelas.kurikulum`/`kelas.fase_id`.
3. Kelas yang nilainya BEDA ditampilkan dalam tabel diff: `Nama Kelas | Kurikulum Tersimpan → Kurikulum Seharusnya | Fase Tersimpan → Fase Seharusnya`.
4. Admin mencentang baris yang mau disinkronkan (checkbox per baris + "pilih semua"), submit.
5. Action baru `ResyncKurikulumKelasAction::execute(array $kelasIds)` melakukan `DB::transaction` meng-update kolom `kurikulum`/`fase_id` kelas yang dicentang ke nilai live yang sudah dihitung ulang di server (bukan dari input form — mencegah tampering).

**Route baru** (mengikuti prefix `admin.kurikulum-assignment.*` yang sudah ada):
- `GET admin/kurikulum-assignment/resync` → `KurikulumAssignmentController::resyncForm` (tampilkan pemilih lembaga+tahun ajaran, dan tabel diff kalau sudah dipilih)
- `POST admin/kurikulum-assignment/resync` → `KurikulumAssignmentController::resync` (eksekusi `ResyncKurikulumKelasAction`)

**Otorisasi**: permission `kurikulum-assignment.update` yang sudah ada (dipakai ulang, tidak perlu permission baru).

**Non-destruktif**: tidak ada cron/auto-trigger. Murni tool koreksi manual sesuai kebutuhan admin.

### 2.3 — Fix ekskul: dropdown dari master data, bukan teks bebas

**Controller** (`app/Http/Controllers/Guru/RaporController.php::edit()`, baris 122) menambah data ke view:
```php
'ekskulOptions' => EkstrakurikulerLembaga::where('lembaga_id', $siswa->lembaga_id)->orderBy('nama_ekskul')->pluck('nama_ekskul'),
```

**View** (`resources/views/portals/guru/rapor/catatan/edit.blade.php:67`) — ganti `<input type="text">` nama jadi `<select>`:
```blade
<select :name="`ekstrakurikuler[${index}][nama]`" x-model="row.nama" class="sm:col-span-2 rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
    <option value="">— Pilih Ekskul —</option>
    @foreach ($ekskulOptions as $nama)
        <option value="{{ $nama }}">{{ $nama }}</option>
    @endforeach
</select>
```
Karena jumlah ekskul per lembaga biasanya kecil (SK-based, bukan katalog besar), dipakai `<select>` native — TIDAK perlu Tom Select kecuali lembaga tertentu terbukti punya >7-10 ekskul terdaftar (threshold dari konvensi proyek). Plan akan menambah query cepat untuk memverifikasi asumsi ini sebelum implementasi; jika ternyata > threshold di data produksi, upgrade ke Tom Select mengikuti pola existing di proyek.

**Data historis (backward-compat)**: kalau `catatan_wali_kelas` yang sedang diedit punya nilai `nama` yang TIDAK ada di `$ekskulOptions` saat ini (ekskul sudah dihapus/diganti dari master), tambahkan opsi tersebut secara eksplisit di render (ditandai, mis. `(tidak terdaftar lagi)`) supaya tidak hilang diam-diam saat form dibuka — tapi submit baru tetap wajib memilih dari daftar aktif kalau baris itu diubah.

**Validasi backend** (`StoreCatatanWaliKelasRequest::rules()`). Route menggunakan model binding `Siswa $siswa` (`Guru\RaporController::update(Siswa $siswa, StoreCatatanWaliKelasRequest $request)`), jadi `lembaga_id` diambil dari situ:
```php
'ekstrakurikuler.*.nama' => [
    'required_with:ekstrakurikuler',
    Rule::in(EkstrakurikulerLembaga::where('lembaga_id', $this->route('siswa')->lembaga_id)->pluck('nama_ekskul')),
],
```

## 3. Non-Goals (eksplisit di luar scope Kelompok A)

- Tidak menyentuh Kenaikan Kelas UX safety-net (validasi kecocokan kurikulum kelas tujuan, saran otomatis "lulus" di tingkat akhir, guard `bentuk_pendidikan`) — itu Kelompok B, spec terpisah.
- Tidak menyentuh RPP (reporting kurikulum, validasi kelas-semester) — itu Kelompok C, spec terpisah.
- Tidak menambah test regresi cross-tenant IDOR untuk `ekstrakurikuler_lembaga` — itu bagian Kelompok C.
- Tidak membangun sistem notifikasi Akademik apa pun (poin #10) — backlog fitur terpisah, bukan bug fix.
- Tidak mengubah perilaku "snapshot beku" `kelas.kurikulum`/`fase_id` menjadi auto-resync — itu keputusan arsitektur yang disengaja dan tetap dipertahankan; §2.2 HANYA menambah tool koreksi manual.
- Tidak mengubah skema `catatan_wali_kelas.ekstrakurikuler` dari JSON menjadi tabel relasional — tetap JSON, hanya nilainya divalidasi terhadap master data.

## 4. Testing (acceptance criteria wajib)

**4.1 — Widget Jadwal Hari Ini:**
- Guru dengan jadwal di semester AKTIF dan semester LAMA (tahun ajaran berbeda) pada hari yang sama → `$jadwalHariIni` HANYA berisi jadwal semester aktif.
- Guru tanpa semester aktif (lembaga belum punya semester aktif) → `$jadwalHariIni` kosong, tidak error.
- Assert dulu bahwa jadwal lama benar-benar tersimpan (exists) sebelum assert exclusion — pola existence-then-exclusion yang sudah baku di proyek ini.

**4.2 — Resync Kurikulum/Fase:**
- Kelas dengan `kurikulum`/`fase_id` yang cocok dengan `kurikulum_assignment`/`fase_default_mapping` saat ini → TIDAK muncul di tabel diff.
- Kelas dengan nilai berbeda (assignment diedit setelah kelas dibuat) → muncul di tabel diff dengan nilai lama dan nilai seharusnya yang benar.
- Setelah resync dijalankan untuk kelas yang dicentang → `kelas.kurikulum`/`fase_id` ter-update ke nilai live; kelas yang TIDAK dicentang tetap di nilai lama.
- Kelas dari lembaga lain tidak ikut muncul di tabel diff (tenant isolation).
- Kelas yang assignment-nya tidak ditemukan sama sekali (`KurikulumAssignmentNotFoundException`) dilewati dengan aman, tidak crash.

**4.3 — Validasi Ekskul:**
- Submit dengan nama ekskul yang ADA di `ekstrakurikuler_lembaga` milik lembaga siswa → tersimpan sukses.
- Submit dengan nama ekskul yang TIDAK ada di master data lembaga tsb → validasi gagal (422/redirect back dengan error).
- Submit dengan nama ekskul yang ada di lembaga LAIN (bukan lembaga siswa) → validasi gagal (tenant isolation pada validasi).
- Edit `catatan_wali_kelas` existing yang punya nilai ekskul historis tidak lagi terdaftar → form tetap menampilkan nilai tsb tanpa error saat load (lihat §2.3 backward-compat). **Keputusan tegas**: form TIDAK melakukan diff "baris mana yang diubah" — setiap submit selalu memvalidasi ULANG seluruh array `ekstrakurikuler` terhadap daftar master AKTIF saat ini. Jadi kalau wali kelas submit ulang form tanpa mengganti baris yang sudah usang itu, validasi tetap GAGAL pada baris tsb, memaksa wali kelas memilih nilai valid dari dropdown sebelum bisa menyimpan. Ini perilaku yang benar (memaksa data lama dibersihkan saat disentuh lagi), bukan bug.
