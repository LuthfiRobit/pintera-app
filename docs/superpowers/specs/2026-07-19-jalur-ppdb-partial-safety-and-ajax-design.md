# Keamanan Hapus & Interaksi Tanpa Reload — Formulir Field, Dokumen Syarat, Seleksi & Tes — Design Spec

**Tanggal:** 2026-07-19
**Status:** Disetujui, siap masuk writing-plans

## 1. Latar Belakang & Masalah

Tiga bagian di halaman edit Jalur PPDB (Formulir Field, Dokumen Syarat, Seleksi & Tes) punya pola CRUD identik: daftar item + tombol hapus per baris + form tambah item di bawahnya. Ditemukan dua masalah lewat scan menyeluruh:

**Bug kritis (terkonfirmasi, dilaporkan user):** menghapus Dokumen Syarat yang sudah punya dokumen ter-unggah dari calon murid (`dokumen_pendaftaran.dokumen_syarat_ppdb_id`) menghasilkan `SQLSTATE[23000]` — foreign key constraint violation — karena `DokumenSyaratController::destroy()` memanggil `->delete()` langsung tanpa pengecekan apapun. Ini menampilkan halaman error mentah ke admin.

**Bug yang sama persis di Formulir Field:** `jawaban_formulir_pendaftaran.formulir_field_id` juga terikat FK tanpa `onDelete`, jadi `FormulirFieldController::destroy()` akan gagal dengan error yang sama begitu ada calon murid yang sudah menjawab field tersebut.

**Bug berbeda dan lebih berbahaya di Seleksi & Tes:** `hasil_seleksi.seleksi_ppdb_id` sudah diberi `->cascadeOnDelete()` di migration. Akibatnya `SeleksiController::destroy()` **tidak pernah error** — tapi menghapus jadwal seleksi otomatis ikut menghapus seluruh hasil penilaian yang sudah dicatat untuk jadwal itu, tanpa peringatan apapun.

**Masalah UX (diminta user):** ketiga bagian ini submit lewat `<form method="POST">` biasa yang redirect ke seluruh halaman edit — setiap tambah/hapus item me-reload seluruh halaman, termasuk scroll position hilang dan komponen lain di halaman "berkedip" walau tidak berubah.

## 2. Lingkup

**Termasuk:**
- `destroy()` di ketiga controller (`FormulirFieldController`, `DokumenSyaratController`, `SeleksiController`) menolak hapus jika item masih punya data pendaftar terkait, dengan pesan yang menyebutkan jumlahnya.
- Migration mengubah FK `hasil_seleksi.seleksi_ppdb_id` dari `cascadeOnDelete()` menjadi default (RESTRICT) — konsisten dengan dua tabel lain, jadi constraint DB tidak lagi jadi jalan keluar diam-diam kalau guard aplikasi suatu saat terlewati.
- `store()`/`destroy()` di ketiga controller mendukung response JSON (`$request->wantsJson()`), mengikuti pola yang sudah ada di `RoleController`.
- Ketiga partial Blade diubah dari server-rendered form ke komponen Alpine.js dengan `fetch()`, mengikuti pola `roles-table.js`/`role-form.js` yang sudah ada — tambah/hapus item tidak lagi reload halaman.
- Toast sukses/gagal memakai `Alpine.store('toast')` yang sudah ada (`resources/js/toast-store.js`, sudah terpasang di layout via `<x-toast />`, saat ini dipakai `roles-table.js`/`role-form.js`).

**Tidak termasuk:**
- Perubahan pada card utama "Detail Jalur" (nama/deskripsi/status_aktif) — tetap submit form biasa seperti sekarang.
- Perubahan pada `Pendaftaran`, `DokumenPendaftaran`, `JawabanFormulirPendaftaran`, `HasilSeleksi`, atau alur publik SPMB.
- Fitur edit-in-place untuk item yang sudah ada (formulir field/dokumen/seleksi) — CRUD saat ini hanya tambah & hapus, itu yang dipertahankan.
- Pagination atau infinite-scroll pada ketiga daftar ini (jumlah item per jalur biasanya kecil, cukup render semua sekaligus seperti sekarang).

## 3. Backend — Model Relations

Tambahkan relasi `hasMany` ke tabel data pendaftar di ketiga model:

```php
// app/Models/FormulirField.php
public function jawabanFormulir(): HasMany
{
    return $this->hasMany(JawabanFormulirPendaftaran::class);
}
```

```php
// app/Models/DokumenSyaratPpdb.php
public function dokumenPendaftaran(): HasMany
{
    return $this->hasMany(DokumenPendaftaran::class);
}
```

```php
// app/Models/SeleksiPpdb.php
public function hasilSeleksi(): HasMany
{
    return $this->hasMany(HasilSeleksi::class);
}
```

Semua memakai konvensi FK default Laravel (`formulir_field_id`, `dokumen_syarat_ppdb_id`, `seleksi_ppdb_id`) — sudah cocok dengan kolom yang ada di migration masing-masing tabel anak, dikonfirmasi lewat `belongsTo()` yang sudah ada di `DokumenPendaftaran`, `HasilSeleksi`, `JawabanFormulirPendaftaran`.

## 4. Backend — Migration untuk `hasil_seleksi`

```php
Schema::table('hasil_seleksi', function (Blueprint $table) {
    $table->dropForeign(['seleksi_ppdb_id']);
    $table->foreign('seleksi_ppdb_id')->references('id')->on('seleksi_ppdb')->restrictOnDelete();
});
```

Ini murni pertahanan berlapis di level DB — dengan guard aplikasi di bagian 5, baris `hasil_seleksi` tidak akan pernah ada di titik constraint ini diuji (guard sudah menolak duluan). Tapi kalau suatu saat ada kode lain yang menghapus `SeleksiPpdb` tanpa lewat controller ini, constraint DB tetap mencegah kehilangan data diam-diam, sama seperti dua tabel lainnya.

## 5. Backend — Guard di `destroy()` + Dual JSON/Redirect Response

Pola yang sama untuk ketiga controller, mengikuti persis `RoleController::destroy()`/`errorResponse()` yang sudah ada:

```php
// app/Http/Controllers/Admin/DokumenSyaratController.php
public function destroy(Request $request, DokumenSyaratPpdb $dokumenSyarat): RedirectResponse|JsonResponse
{
    $this->authorize('dokumen-syarat.delete');

    $jumlahDokumen = $dokumenSyarat->dokumenPendaftaran()->count();
    if ($jumlahDokumen > 0) {
        return $this->errorResponse(
            $request,
            "Tidak bisa dihapus, sudah ada {$jumlahDokumen} dokumen terkait dari calon murid."
        );
    }

    $jalur = $dokumenSyarat->jalurPpdb;
    $dokumenSyarat->delete();

    if ($request->wantsJson()) {
        return response()->json(['message' => 'Dokumen syarat berhasil dihapus.']);
    }

    return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Dokumen syarat berhasil dihapus.');
}

private function errorResponse(Request $request, string $message): RedirectResponse|JsonResponse
{
    if ($request->wantsJson()) {
        return response()->json(['message' => $message], 422);
    }

    return back()->withErrors(['dokumen_syarat' => $message]);
}
```

`FormulirFieldController::destroy()` dan `SeleksiController::destroy()` mengikuti bentuk identik, cukup ganti nama relasi (`jawabanFormulir()`/`hasilSeleksi()`) dan kata bendanya ("jawaban"/"hasil penilaian") di pesan.

`store()` di ketiga controller menambahkan cabang JSON di akhir (sebelum/menggantikan redirect), pola sama seperti yang sudah ada di `RoleController::store()`:

```php
if ($request->wantsJson()) {
    return response()->json(['data' => $dokumenSyarat->fresh()], 201);
}

return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Dokumen syarat berhasil ditambahkan.');
```

Validasi error pada `store()` (400/422 dari `$request->validate()`) sudah otomatis menghasilkan response JSON yang benar ketika request punya header `Accept: application/json` — perilaku bawaan Laravel, tidak perlu kode tambahan.

## 6. Frontend — Pola Alpine per Bagian

Setiap partial jadi komponen Alpine mandiri, didaftarkan di `resources/js/app.js` mengikuti pola `Alpine.data('rolesTable', rolesTable)` yang sudah ada. Tiga file baru: `resources/js/dokumen-syarat-list.js`, `resources/js/formulir-field-list.js`, `resources/js/seleksi-list.js`.

Bentuk `dokumen-syarat-list.js` (dua lainnya mengikuti bentuk sama, field form menyesuaikan):

```js
export function dokumenSyaratList(config) {
    return {
        items: config.initialItems,
        jalurId: config.jalurId,
        storeUrl: config.storeUrl,
        deleteUrlTemplate: config.deleteUrlTemplate,
        form: { nama_dokumen: '', wajib: true },
        errors: {},
        submitting: false,

        async addItem() {
            this.submitting = true;
            this.errors = {};

            try {
                const response = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ jalur_ppdb_id: this.jalurId, ...this.form }),
                });

                const json = await response.json();

                if (response.status === 422) {
                    this.errors = json.errors ?? {};
                    Alpine.store('toast').push('error', json.message ?? 'Periksa kembali form.');
                    return;
                }

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menambah dokumen.');
                    return;
                }

                this.items.push(json.data);
                this.form = { nama_dokumen: '', wajib: true };
                Alpine.store('toast').push('success', 'Dokumen syarat berhasil ditambahkan.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menambah dokumen.');
            } finally {
                this.submitting = false;
            }
        },

        async deleteItem(item) {
            if (!confirm(`Hapus dokumen "${item.nama_dokumen}"?`)) {
                return;
            }

            try {
                const response = await fetch(this.deleteUrlTemplate.replace('__ID__', item.id), {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menghapus dokumen.');
                    return;
                }

                this.items = this.items.filter((existing) => existing.id !== item.id);
                Alpine.store('toast').push('success', json.message ?? 'Dokumen syarat berhasil dihapus.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menghapus dokumen.');
            }
        },
    };
}
```

Blade partial memakai `x-data`, `<template x-for>` untuk daftar, dan `@submit.prevent="addItem()"` untuk form tambah — mengikuti pola persis `resources/views/admin/roles/index.blade.php`:

```blade
<div
    class="rounded-2xl border border-gray-200 bg-white shadow-card"
    x-data="dokumenSyaratList({
        initialItems: @js($jalur->dokumenSyarat),
        jalurId: {{ $jalur->id }},
        storeUrl: @js(route('admin.dokumen-syarat.store')),
        deleteUrlTemplate: @js(route('admin.dokumen-syarat.destroy', ['dokumenSyarat' => '__ID__'])),
    })"
>
    <div class="border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Dokumen Syarat</p>
        <p class="mt-0.5 text-sm text-gray-500">Daftar dokumen yang harus diunggah calon murid pada jalur ini.</p>
    </div>

    <ul class="divide-y divide-gray-100 px-5">
        <template x-if="items.length === 0">
            <li class="py-6 text-center text-sm text-gray-500">Belum ada dokumen syarat.</li>
        </template>
        <template x-for="item in items" :key="item.id">
            <li class="flex items-center justify-between py-3">
                <span class="flex items-center gap-2 text-sm text-gray-900">
                    <span x-text="item.nama_dokumen"></span>
                    <span
                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="item.wajib ? 'bg-brand-50 text-brand-600' : 'bg-gray-100 text-gray-600'"
                        x-text="item.wajib ? 'Wajib' : 'Opsional'"
                    ></span>
                </span>
                <button type="button" @click="deleteItem(item)" class="text-sm font-semibold text-error-600 hover:text-error-700">Hapus</button>
            </li>
        </template>
    </ul>

    <form @submit.prevent="addItem()" class="flex flex-wrap items-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-4">
        <div class="flex-1">
            <x-input-label value="Nama Dokumen" />
            <x-text-input type="text" x-model="form.nama_dokumen" placeholder="mis. Akta Kelahiran" class="mt-1.5" />
        </div>
        <label class="flex items-center gap-2 pb-2.5 text-sm text-gray-700">
            <input type="checkbox" x-model="form.wajib" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
            Wajib
        </label>
        <x-secondary-button type="submit" x-bind:disabled="submitting">Tambah Dokumen</x-secondary-button>
    </form>
</div>
```

`<x-badge>` is a server-rendered Blade component and can't reactively bind to client-side Alpine state, so items rendered inside the `x-for` loop use a plain `<span>` with the exact classes `<x-badge>` would have produced (`bg-brand-50 text-brand-600` for the `brass` tone, `bg-gray-100 text-gray-600` for `slate`, both confirmed in `resources/views/components/badge.blade.php`), bound reactively via `:class`. Only client-rendered items need this — nothing about `<x-badge>` itself changes.

## 7. Testing

Backend (untuk masing-masing dari 3 controller):
- Menolak hapus (assert 422 + pesan yang benar via JSON; assert redirect+withErrors via non-JSON) ketika item masih punya data pendaftar terkait, dan baris asli tetap ada di database setelahnya.
- Mengizinkan hapus normal ketika tidak ada data pendaftar terkait (regresi wajib hijau — perilaku existing untuk kasus tanpa data pendaftar tidak berubah).
- `store()` merespons JSON berisi item baru saat diminta lewat `Accept: application/json`, dan tetap redirect seperti sebelumnya untuk request biasa.
- Migration test/regresi: `hasil_seleksi` masih bisa dibuat dan dibaca normal setelah FK diubah ke restrict (perilaku baca/tulis normal tidak berubah, cuma delete yang berbeda).

Frontend: di luar cakupan test otomatis Pest (tidak ada browser test di proyek ini) — diverifikasi manual di task final seperti pola task-task sebelumnya (curl tidak bisa mengeksekusi JS, jadi verifikasi manual perlu dilakukan lewat browser sungguhan atau minimal memverifikasi response JSON dari endpoint langsung via curl).

## 8. Langkah Berikutnya

Tidak ada item yang didorong ke spec terpisah — spec ini menutup baik bug FK yang dilaporkan maupun permintaan UX no-reload dalam satu paket kerja, sesuai arahan menggabungkan keduanya karena saling terkait (perbaikan UX secara alami memperbaiki cara error FK ditampilkan).
