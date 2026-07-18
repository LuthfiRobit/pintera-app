# Redesign & Keamanan Halaman Jalur PPDB — Design Spec

**Tanggal:** 2026-07-18
**Status:** Disetujui, siap masuk writing-plans

## 1. Latar Belakang & Masalah

Fitur pembatasan jalur per gelombang (`docs/superpowers/specs/2026-07-18-gelombang-jalur-restriction-design.md`) menambahkan relasi `GelombangPpdb::jalur()` / `JalurPpdb::gelombang()` dan mengubah model checkbox di form Gelombang PPDB jadi "checked = dipakai, minimal satu wajib dicentang". Perubahan ini mengekspos dua masalah di sisi Jalur PPDB yang sebelumnya tidak relevan:

**Bug kritis (terkonfirmasi via reproduksi manual):** menonaktifkan (`status_aktif = false`) satu-satunya jalur yang dipakai sebuah gelombang, lalu menyimpan gelombang itu untuk alasan apa pun (ubah kuota, dsb.), **menghapus seluruh baris pivot restriksi gelombang itu secara diam-diam** — tanpa error, tanpa peringatan. Penyebabnya: form edit gelombang hanya menampilkan checkbox untuk jalur `status_aktif = true`; kalau jalur yang dinonaktifkan itu satu-satunya jalur aktif di tahun ajaran tersebut, seluruh bagian checkbox berubah jadi pesan "belum ada jalur aktif", validasi `jalur_ids` otomatis jadi `nullable`, dan `sync([])` berjalan menghapus histori pivot yang seharusnya tetap ada.

**Gap visibilitas:** `JalurPpdb::gelombang()` (dibuat di task pembatasan jalur) tidak dipakai di mana pun. Admin yang membuka halaman Jalur PPDB tidak tahu jalur itu sedang dipakai di gelombang mana saja, sehingga tidak ada peringatan sama sekali sebelum aksi yang berpotensi merusak data (menonaktifkan jalur yang masih dipakai).

**Gap desain visual (independen dari dua isu di atas):** halaman Jalur PPDB (index/create/edit + 3 partial: Formulir Field, Dokumen Syarat, Seleksi & Tes) belum pernah disentuh sejak redesign TailAdmin dimulai — masih memakai token desain lama (`x-panel`, `text-ink`/`text-slate`/`bg-brass`, `<x-slot name="header">`), termasuk form create yang masih `max-w-2xl` di tengah halaman (pola yang sudah diperbaiki di Lembaga dan Gelombang PPDB).

## 2. Lingkup

**Termasuk:**
- Backend: blokir penonaktifan jalur yang masih dipakai ≥1 gelombang, dengan pesan error yang menyebutkan nama gelombang terkait.
- Backend: index Jalur PPDB dapat filter `tahun_ajaran` (browse tahun lalu), sama seperti Gelombang PPDB.
- UI: badge "Gelombang (N)" di baris Kelengkapan halaman edit + daftar nama gelombang di dekat toggle status aktif.
- UI: kolom "Dipakai di N Gelombang" / "Tidak Dipakai" di index.
- UI: redesign penuh index/create/edit ke pola TailAdmin (filter card, tabel dengan Aksi dropdown, form full-width, komponen `x-icon`/`x-badge`/`x-table-actions`).
- UI: redesign visual ketiga partial (Formulir Field, Dokumen Syarat, Seleksi & Tes) — struktur list + form-tambah-inline yang sudah ada dipertahankan, hanya re-skin token warna/komponen.
- Testing: regresi khusus untuk skenario bug kritis di atas (harus berubah dari "berhasil, pivot terhapus" menjadi "ditolak validasi").

**Tidak termasuk:**
- Perubahan skema/logika `FormulirField`, `DokumenSyaratPpdb`, atau `SeleksiPpdb` (hanya re-skin visual, controller & route-nya tidak disentuh).
- Perubahan `PortalController` atau alur publik SPMB (kill-switch `status_aktif` tetap seperti sekarang).
- Penghapusan jalur (tidak ada route `destroy` untuk `JalurPpdb`, tidak ditambahkan di sini).
- Perubahan skema tabel `jalur_ppdb` (field `nama`/`deskripsi`/`status_aktif` sudah lengkap, tidak ada field tersembunyi seperti kasus Lembaga sebelumnya).

## 3. Backend — Blokir Penonaktifan Jalur yang Masih Dipakai

Di `JalurPpdbController::update()`, sebelum `$jalurPpdb->update($data)`, tambahkan pengecekan:

```php
if ($jalurPpdb->status_aktif && ! $data['status_aktif'] && $jalurPpdb->gelombang()->exists()) {
    $namaGelombang = $jalurPpdb->gelombang()->pluck('gelombang_ppdb.nama')->implode(', ');

    return back()->withErrors([
        'status_aktif' => "Tidak bisa menonaktifkan jalur ini karena masih dipakai di gelombang: {$namaGelombang}. Hapus centang jalur ini dari gelombang tersebut terlebih dahulu.",
    ])->withInput();
}
```

Perubahan `nama`/`deskripsi` di submit yang sama tetap lolos — hanya transisi `status_aktif: true → false` yang diblokir saat jalur masih dipakai. Mengaktifkan kembali jalur (`false → true`) tidak pernah diblokir. Ini menutup akar masalah bug kritis: jalur tidak bisa pernah menjadi nonaktif selagi masih tercentang di gelombang manapun, sehingga skenario "pool jalur aktif gelombang menyusut di bawah restriksi yang sudah ada" tidak mungkin terjadi lagi.

`JalurPpdbController::edit()` menambahkan data untuk badge dan daftar nama:

```php
'gelombangPemakai' => $jalurPpdb->gelombang()->orderBy('nama')->pluck('nama'),
```

`JalurPpdbController::index()` menambahkan filter `tahun_ajaran` (pola identik dengan `GelombangPpdbController::index()`) dan `withCount('gelombang')` pada query jalur:

```php
$tahunAjaranOptions = TahunAjaran::orderByDesc('tanggal_mulai')->get();
$tahunAjaranTerpilih = $request->filled('tahun_ajaran')
    ? $tahunAjaranOptions->firstWhere('id', (int) $request->query('tahun_ajaran'))
    : $tahunAjaranAktif;

$jalurList = $tahunAjaranTerpilih
    ? JalurPpdb::withCount('gelombang')->where('tahun_ajaran_id', $tahunAjaranTerpilih->id)->orderBy('nama')->get()
    : collect();
```

## 4. UI — Visibilitas "Dipakai di Gelombang"

**Index:** kolom baru di tabel, memakai `$jalur->gelombang_count`:

```blade
@if ($jalur->gelombang_count > 0)
    <x-badge tone="brass">Dipakai di {{ $jalur->gelombang_count }} Gelombang</x-badge>
@else
    <x-badge tone="slate">Tidak Dipakai</x-badge>
@endif
```

**Edit — baris Kelengkapan:** tambah badge ke-4 memakai pola yang sudah ada:

```blade
<x-badge :tone="$gelombangPemakai->isNotEmpty() ? 'brass' : 'slate'">Gelombang ({{ $gelombangPemakai->count() }})</x-badge>
```

**Edit — dekat toggle status aktif:** teks kecil di bawah checkbox `status_aktif`:

```blade
<p class="mt-1.5 text-xs text-gray-500">
    @if ($gelombangPemakai->isNotEmpty())
        Dipakai di gelombang: {{ $gelombangPemakai->implode(', ') }}. Jalur tidak bisa dinonaktifkan selama masih dipakai.
    @else
        Tidak dipakai di gelombang manapun saat ini.
    @endif
</p>
```

## 5. UI — Redesign Visual

**Index:** ikut pola persis `resources/views/admin/gelombang-ppdb/index.blade.php` — header tanpa card border (`font-display text-lg font-bold text-gray-900` + breadcrumb), filter card (`rounded-2xl border border-gray-200 bg-white p-5 shadow-card`) berisi search `nama` + filter `tahun_ajaran` + tombol "Tambah Jalur" di kanan card filter, tabel dengan kolom Aksi (sticky, `x-table-actions` + `x-dropdown-link` ke edit) / Nama / Status (`x-badge` Aktif-Nonaktif, sudah ada) / Dipakai di Gelombang (baru, §4). Tidak perlu pagination (`->paginate()`) mengingat jumlah jalur per tahun ajaran biasanya kecil (3-5) — cukup `->get()` seperti sekarang, kecuali nanti terbukti perlu.

**Create:** ikut pola `gelombang-ppdb/create.blade.php` — card `Detail Jalur` full-width (bukan `max-w-2xl` di tengah), field `nama` + `deskripsi` dengan placeholder jelas.

**Edit:** ikut pola yang sama untuk card "Detail Jalur" (nama/deskripsi/status_aktif + info dipakai-di-gelombang dari §4), lalu 3 partial di bawahnya masing-masing jadi card terpisah bergaya sama (`rounded-2xl border-gray-200 bg-white shadow-card`, header pakai `x-icon`, list item pakai `divide-y divide-gray-100`, tombol hapus tetap tautan teks "Hapus" seperti sekarang — cukup ganti warna dari `text-signal-red` ke `text-error-600 hover:text-error-700` — tidak ada icon "trash" di komponen `x-icon` saat ini dan menambah satu di luar lingkup spec ini, form tambah-inline di bagian bawah card dengan `x-input-label`/`x-text-input` yang sudah dipakai di tempat lain).

## 6. Testing

- Menolak update yang menonaktifkan jalur yang masih dipakai ≥1 gelombang, pesan error menyebut nama gelombang yang benar, `status_aktif` di database tidak berubah.
- Mengizinkan update yang menonaktifkan jalur yang tidak dipakai gelombang manapun.
- Mengizinkan mengaktifkan kembali jalur yang sedang nonaktif tanpa syarat apapun.
- **Regresi kunci:** skenario yang direproduksi manual (jalur satu-satunya di tahun ajaran dinonaktifkan lalu gelombang disimpan) sekarang gagal di langkah menonaktifkan jalur — tidak pernah sampai ke titik yang menghapus pivot.
- Index menampilkan badge "Dipakai di N Gelombang" / "Tidak Dipakai" sesuai kondisi, dan filter `tahun_ajaran` berfungsi.
- Edit menampilkan badge Kelengkapan "Gelombang (N)" dan daftar nama gelombang yang benar.
- Regresi visual: 3 partial (Formulir Field, Dokumen Syarat, Seleksi) tetap berfungsi (tambah/hapus item) setelah re-skin — tidak ada perubahan pada controller/route-nya sehingga test yang sudah ada untuk `FormulirFieldController`, `DokumenSyaratPpdbController`, `SeleksiPpdbController` (jika ada) harus tetap hijau tanpa perubahan.

## 7. Langkah Berikutnya

Tidak ada item yang sengaja didorong ke spec terpisah — spec ini menutup seluruh gap yang teridentifikasi dari analisa sebelumnya (bug kritis, visibilitas, dan redesign visual) dalam satu paket kerja, sesuai arahan eksplisit untuk menggabungkan ketiganya.
