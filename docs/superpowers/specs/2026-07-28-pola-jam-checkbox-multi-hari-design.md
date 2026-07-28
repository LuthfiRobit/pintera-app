# Pola Jam — Checkbox Multi-Hari pada Form Tambah Slot Design

## Goal

Ganti input "Hari" pada form "Tambah Slot Jam Pelajaran" (di halaman `admin/pola-jam/index.blade.php`) dari `<select>` (satu hari per submit) menjadi checkbox (banyak hari sekaligus per submit). Alasan: slot jam yang sama (jam mulai/selesai, urutan, label, jenis sesi) sangat sering berlaku identik di beberapa hari sekaligus (mis. "Jam ke-1, 07:00–07:40" berlaku Senin–Jumat) — dengan `<select>`, admin harus mengulang submit form satu per satu untuk tiap hari. Checkbox memungkinkan satu submit membuat beberapa `JamPelajaran` sekaligus, satu per hari yang dicentang.

## Scope

**Termasuk:** form create ("Tambah Slot Jam Pelajaran") di `admin/pola-jam/index.blade.php`, dan `JamPelajaranController::store()`.

**Tidak termasuk (sengaja dipertahankan seperti sekarang):**
- Form Edit slot (`admin/jam-pelajaran/edit.blade.php`, `JamPelajaranController::edit()`/`update()`) — tetap satu hari via `<select>`, karena edit mengubah satu record `JamPelajaran` yang sudah spesifik ke satu hari. Mengubah hari pada satu slot yang sudah ada tidak mengubah jumlah recordnya.
- `destroy()` — tidak berubah.
- Aturan tabrakan (unique `pola_jam_id`+`hari`+`urutan`) itu sendiri — tidak berubah, hanya diterapkan per-hari di dalam loop baru.
- Tidak ada perubahan keamanan/tenant-scoping — `pola_jam_id` tetap divalidasi & di-resolve dengan cara yang sama seperti sekarang (`PolaJam::find()`), murni perubahan logika batch-create pada field `hari`.

## UI Change

Di `resources/views/admin/pola-jam/index.blade.php`, bagian "3. Form Tambah Slot Jam Pelajaran", ganti:

```blade
<div class="lg:col-span-2">
    <x-input-label value="Hari" class="mb-1 text-sm text-gray-700" />
    <select name="hari" class="block w-full rounded-lg border-gray-200 py-2 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
        @foreach ($hariAktifPola as $hari)
            <option value="{{ $hari->value }}">{{ $hari->label() }}</option>
        @endforeach
    </select>
</div>
```

menjadi baris checkbox horizontal, mengikuti gaya visual checkbox "Tautkan ke Kelas" yang sudah ada di halaman yang sama (`h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500`, label dengan `flex items-center gap-2`). Karena daftar hari sekarang butuh lebar lebih dari 2 kolom grid (`lg:col-span-2`) yang dipakai select, checkbox hari dipindah menjadi barisnya sendiri di atas grid Urutan/Jam Mulai/Jam Selesai/Label yang sudah ada (grid tersebut menyusut dari `lg:grid-cols-12` dengan kolom Hari `lg:col-span-2` menjadi tanpa kolom Hari, kolom-kolom lain melebar proporsional atau tetap seperti sekarang di baris terpisah — implementer memutuskan proporsi persis saat membangun, mengikuti breakpoint yang sudah ada).

Tambahkan validasi HTML `required` tidak memungkinkan untuk grup checkbox (browser tidak mendukung "minimal satu tercentang" secara native) — validasi minimal-satu-dicentang dilakukan di server (lihat Backend Change), dan pesan errornya muncul lewat mekanisme toast error yang sudah ada di halaman ini (`$errors->first()`).

## Backend Change — `JamPelajaranController::store()`

**Validasi**, dari:
```php
'hari' => ['required', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
```
menjadi:
```php
'hari' => ['required', 'array', 'min:1'],
'hari.*' => ['in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
```

**Logika create**, dari satu `JamPelajaran::create($data)` menjadi loop per hari:

```php
$berhasil = [];
$dilewati = [];

foreach ($data['hari'] as $hari) {
    if ($this->tabrakanSlot($data['pola_jam_id'], $hari, $data['urutan'])) {
        $dilewati[] = $hari;
        continue;
    }

    JamPelajaran::create([...$data, 'hari' => $hari]);
    $berhasil[] = $hari;
}
```

(Field `hari` dalam `$data` di sini masih array hasil validasi — setiap pemanggilan `JamPelajaran::create()` di atas mengoper `hari` sebagai string tunggal lewat override `'hari' => $hari`, bukan array asli `$data['hari']`.)

Duplikat pada `hari[]` (mis. request yang dimanipulasi mengirim `senin` dua kali) otomatis aman: iterasi kedua untuk `senin` akan menemukan slot yang baru saja dibuat oleh iterasi pertama lewat `tabrakanSlot()`, sehingga otomatis masuk `$dilewati` alih-alih membuat duplikat — tidak perlu `array_unique()` eksplisit, tapi implementer boleh menambahkannya untuk kejelasan kalau mau.

**Pesan hasil**, label hari untuk pesan memakai `Hari::from($value)->label()` (mis. `senin` → `Senin`):

- Kalau `$berhasil` tidak kosong → redirect dengan `status` (flash sukses, seperti sekarang):
  - Kalau `$dilewati` kosong: `"Slot berhasil ditambahkan untuk {daftar hari berhasil, dipisah koma}."`
  - Kalau `$dilewati` tidak kosong: `"Slot berhasil ditambahkan untuk {daftar hari berhasil}. {daftar hari dilewati} dilewati karena urutan ini sudah dipakai."` (Kalau hari yang dilewati lebih dari satu, gunakan bentuk jamak yang wajar, mis. "Selasa dan Kamis dilewati ...")
- Kalau `$berhasil` kosong (semua hari yang dicentang bentrok) → `return back()->withErrors(['hari' => "Semua hari yang dipilih ({daftar hari dicentang}) sudah punya slot di urutan ini — tidak ada yang ditambahkan."])->withInput();` — tampil sebagai toast error (merah), bukan status sukses, mengikuti pola `$errors->first()` yang sudah ada di halaman ini. Route redirect TIDAK berpindah ke index (tetap `back()`) sehingga input form (kecuali checkbox, yang tidak di-restore oleh `withInput()` untuk array checkbox secara otomatis — dapat diterima, ini sudah perilaku standar Laravel untuk checkbox groups) tidak hilang percuma.

## Edge Cases

- **Tidak ada hari dicentang sama sekali:** ditangkap oleh validasi `'hari' => ['required', 'array', 'min:1']` sebelum masuk logika loop — muncul sebagai validation error standar lewat `$errors->first()`, tidak perlu penanganan khusus.
- **Hari yang dicentang di luar `$hariAktifPola`** (mis. request dimanipulasi mengirim hari libur mingguan yang tidak ditampilkan di checkbox): TIDAK divalidasi ulang terhadap `$hariAktifPola` di server — sama seperti perilaku `<select>` yang sekarang (yang juga tidak melakukan validasi ini), karena `$hariAktifPola` murni filter tampilan, bukan aturan bisnis yang mengunci hari mana yang boleh punya `JamPelajaran`. Tidak ada perubahan perilaku di sini, hanya dipertahankan.
- **`pola_jam_id` tidak valid / bukan milik lembaga sendiri:** tidak berubah — pengecekan `PolaJam::find($data['pola_jam_id'])` + `abort(404)` yang sudah ada tetap dipakai apa adanya, dilakukan sebelum loop hari.

## Testing

Test yang perlu ditambahkan/diubah (di test feature yang sudah menguji `JamPelajaranController::store()` — cari lokasinya saat implementasi, kemungkinan `tests/Feature/Admin/JamPelajaranCrudTest.php` atau serupa):
1. Submit `hari[]` dengan 2 hari yang keduanya kosong (tidak bentrok) → assert 2 `JamPelajaran` baru dibuat dengan `urutan`/`jam_mulai`/`jam_selesai`/`label`/`is_pelajaran` yang sama, `hari` berbeda; assert redirect dengan `status` sukses.
2. Submit `hari[]` dengan 2 hari, satu bentrok (sudah ada slot di urutan yang sama) satu tidak → assert hanya 1 `JamPelajaran` baru dibuat (untuk yang tidak bentrok); assert redirect dengan `status` sukses yang menyebut hari yang berhasil DAN hari yang dilewati.
3. Submit `hari[]` dengan 2 hari yang keduanya bentrok → assert 0 `JamPelajaran` baru dibuat; assert response membawa session error (bukan `status`), pesan menyebut semua hari yang dicentang.
4. Submit tanpa `hari[]` sama sekali (array kosong atau field tidak dikirim) → assert validation error pada field `hari`, 0 `JamPelajaran` dibuat.
5. Test existing yang sudah ada untuk single-hari (kalau ada) perlu disesuaikan payloadnya dari `'hari' => 'senin'` menjadi `'hari' => ['senin']`, karena field ini sekarang array.

## Self-Review

- **Placeholder scan:** tidak ada TBD/TODO — semua kode dan pesan sudah konkret.
- **Konsistensi internal:** validasi (`array`+`min:1`), logika loop, dan penanganan pesan sukses/error semuanya konsisten dengan keputusan yang disepakati (buat yang tidak bentrok, beri tahu yang dilewati; error kalau 0 berhasil).
- **Cakupan scope:** terfokus pada satu form + satu method controller, tidak menyentuh Edit/Destroy/tenant-scoping — cukup kecil untuk satu plan implementasi tanpa perlu dipecah lagi.
- **Ambiguitas:** proporsi grid exact untuk baris checkbox sengaja diserahkan ke implementer (bukan ambiguitas yang berbahaya — hanya detail visual minor mengikuti breakpoint yang sudah ada), didokumentasikan secara eksplisit sebagai keputusan yang disengaja, bukan ketinggalan.
