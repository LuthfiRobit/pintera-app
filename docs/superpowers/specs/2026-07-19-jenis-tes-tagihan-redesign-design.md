# Redesign Jenis Tes & Jenis Tagihan — Pola Inline Datatable + Perbaikan Bug Kritis — Design Spec

**Tanggal:** 2026-07-19
**Status:** Disetujui, siap masuk writing-plans

## 1. Latar Belakang & Masalah

Scan menyeluruh (mengikuti metodologi yang sama seperti Jalur PPDB) menemukan dua halaman master-data yang butuh redesign UI/UX sekaligus punya satu bug kritis:

**Bug kritis (Jenis Tagihan):** `JenisTagihanController::destroy()` hanya mengecek relasi `nominalJalur()` sebelum menghapus. Tabel `tagihan_item` (baris tagihan riil milik calon murid, dibuat oleh `TagihanGenerator::generate()` di alur pembayaran normal dan dikonfirmasi lewat `TagihanItemSeeder`) memakai FK `jenis_tagihan_id` dengan `cascadeOnDelete()`. Akibatnya menghapus sebuah `JenisTagihan` yang sudah pernah ditagihkan ke calon murid akan **menghapus data tagihan finansial riil** tanpa peringatan apapun — lebih parah dari bug `hasil_seleksi` yang sudah diperbaiki sebelumnya karena ini data keuangan, bukan sekadar hasil penilaian.

**Masalah UX (kedua halaman):** Jenis Tes memakai daftar sederhana (`<x-panel>`, token lama `text-ink`/`text-slate`) dengan form tambah statis di bawah, tanpa aksi Edit sama sekali. Jenis Tagihan memakai halaman index terpisah dari `create.blade.php`/`edit.blade.php` (navigasi penuh, reload halaman), tanpa aksi Hapus di UI sama sekali, dan tanpa validasi nama unik. Keduanya belum memakai desain TailAdmin yang sudah dipakai di Lembaga/Gelombang/Jalur PPDB.

## 2. Lingkup

**Termasuk:**
- Migration mengubah FK `tagihan_item.jenis_tagihan_id` dari `cascadeOnDelete()` ke default (RESTRICT).
- Relasi `tagihanItem(): HasMany` baru di `JenisTagihan`, dipakai guard `destroy()` dua tingkat (tagihan riil diperiksa lebih dulu, lebih berat, lalu nominal terkonfigurasi).
- Redesign penuh `resources/views/admin/jenis-tes/index.blade.php` ke pola datatable TailAdmin + form inline di atas tabel (bukan card filter), form otomatis terisi saat klik Edit, tanpa navigasi halaman.
- Method `update()` baru + permission `jenis-tes.edit` baru untuk `JenisTesMasterController` (saat ini tidak ada method maupun permission edit sama sekali).
- Redesign penuh `resources/views/admin/jenis-tagihan/index.blade.php` ke pola yang sama; `create.blade.php` dan `edit.blade.php` dihapus, digantikan form inline di index.
- Validasi `nama` unik per lembaga untuk Jenis Tagihan (belum ada sama sekali saat ini) mengikuti pola unique constraint yang sudah ada di Jenis Tes.
- Dukungan JSON (`$request->wantsJson()`) di kedua controller untuk seluruh aksi CRUD, mengikuti pola `RoleController`/controller-controller Jalur PPDB yang sudah dikonversi.
- Dua komponen Alpine baru: `jenis-tes-table.js`, `jenis-tagihan-table.js` — pola CRUD inline mandiri (bukan generic/shared factory), mengikuti konvensi tiga file terpisah yang sudah dipakai untuk partial-partial Jalur PPDB.
- Konfirmasi hapus memakai `confirmDialog()` (store global yang sudah ada), badge status "Dipakai di..." mengikuti pola visual `<x-badge>` (brass/slate) seperti kolom "Dipakai di Gelombang" di Jalur PPDB.
- `resources/views/admin/jenis-tagihan/nominal.blade.php` di-re-skin secara visual ke token TailAdmin (card `rounded-2xl border border-gray-200 bg-white shadow-card`, breadcrumb konsisten) — halaman ini tetap terpisah dan logic-nya tidak berubah.

**Tidak termasuk:**
- Perubahan pada `simpanNominal()` — sudah aman (re-derive `jalur_ppdb_id` yang valid dari `$jenisTagihan->lembaga_id` sendiri), tidak disentuh.
- Perubahan pada `TagihanGenerator`, alur pembayaran, atau modul SPP/tagihan lain di luar dua halaman master-data ini.
- Pagination pada datatable baru (jumlah baris Jenis Tes/Jenis Tagihan per lembaga biasanya kecil).
- Redesign halaman lain (Guru, Tahun Ajaran, Verifikasi & Keputusan, dsb.) — tetap di antrean terpisah.

## 3. Backend — Perbaikan Bug Kritis Jenis Tagihan

Relasi baru di `app/Models/JenisTagihan.php`:

```php
public function tagihanItem(): HasMany
{
    return $this->hasMany(TagihanItem::class);
}
```

Migration baru (`database/migrations/2026_07_19_XXXXXX_restrict_jenis_tagihan_delete_on_tagihan_item.php`), pola identik dengan migration restrict `hasil_seleksi` sebelumnya:

```php
Schema::table('tagihan_item', function (Blueprint $table) {
    $table->dropForeign(['jenis_tagihan_id']);
    $table->foreign('jenis_tagihan_id')->references('id')->on('jenis_tagihan')->restrictOnDelete();
});
```

`destroy()` di `JenisTagihanController` diubah jadi guard dua tingkat — tagihan riil diperiksa lebih dulu karena lebih berat konsekuensinya (data finansial calon murid) daripada sekadar konfigurasi harga:

```php
public function destroy(Request $request, JenisTagihan $jenisTagihan): RedirectResponse|JsonResponse
{
    $this->authorize('jenis-tagihan.delete');

    $jumlahTagihan = $jenisTagihan->tagihanItem()->count();
    if ($jumlahTagihan > 0) {
        return $this->errorResponse(
            $request,
            "Tidak bisa dihapus, sudah dipakai di {$jumlahTagihan} tagihan milik calon murid."
        );
    }

    $jumlahNominal = $jenisTagihan->nominalJalur()->count();
    if ($jumlahNominal > 0) {
        return $this->errorResponse(
            $request,
            "Tidak bisa dihapus, sudah ada {$jumlahNominal} nominal jalur yang dikonfigurasi. Hapus dulu di halaman Kelola Nominal."
        );
    }

    $jenisTagihan->delete();

    if ($request->wantsJson()) {
        return response()->json(['message' => 'Jenis tagihan berhasil dihapus.']);
    }

    return redirect()->route('admin.jenis-tagihan.index')->with('status', 'Jenis tagihan berhasil dihapus.');
}
```

`errorResponse()` privat mengikuti bentuk yang sama persis dengan `RoleController`/controller-controller Jalur PPDB (JSON 422 kalau `wantsJson()`, else `back()->withErrors()`).

## 4. Backend — Jenis Tes: `update()` + Permission Baru

`app/Http/Controllers/Admin/JenisTesMasterController.php` mendapat method `update()` baru (saat ini tidak ada sama sekali):

```php
public function update(Request $request, JenisTesMaster $jenisTes): RedirectResponse|JsonResponse
{
    $this->authorize('jenis-tes.edit');

    $data = $request->validate([
        'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_tes_master', 'nama')
            ->where(fn ($query) => $query->where('lembaga_id', $jenisTes->lembaga_id))
            ->ignore($jenisTes->id)],
        'deskripsi' => ['nullable', 'string', 'max:1000'],
    ]);

    $jenisTes->update($data);

    if ($request->wantsJson()) {
        return response()->json(['data' => $jenisTes->fresh()]);
    }

    return redirect()->route('admin.jenis-tes.index')->with('status', 'Jenis tes berhasil diperbarui.');
}
```

`index()` menambah `withCount('seleksi')` supaya badge "Dipakai di N Seleksi" bisa dirender tanpa query N+1:

```php
return view('admin.jenis-tes.index', [
    'jenisTesList' => JenisTesMaster::withCount('seleksi')->orderBy('nama')->get(),
]);
```

`store()` menambah cabang JSON di akhir (pola sama seperti controller-controller lain yang sudah dikonversi). `destroy()` diubah dari `exists()` ke `count()` supaya pesannya konsisten dengan pola "sebutkan jumlahnya" yang sudah dipakai di controller lain, dan menambah cabang JSON:

```php
public function destroy(Request $request, JenisTesMaster $jenisTes): RedirectResponse|JsonResponse
{
    $this->authorize('jenis-tes.delete');

    $jumlahSeleksi = SeleksiPpdb::where('jenis_tes_master_id', $jenisTes->id)->count();
    if ($jumlahSeleksi > 0) {
        return $this->errorResponse(
            $request,
            "Tidak bisa dihapus, jenis tes ini masih dipakai di {$jumlahSeleksi} jadwal seleksi."
        );
    }

    $jenisTes->delete();

    if ($request->wantsJson()) {
        return response()->json(['message' => 'Jenis tes berhasil dihapus.']);
    }

    return redirect()->route('admin.jenis-tes.index')->with('status', 'Jenis tes berhasil dihapus.');
}
```

Permission baru `jenis-tes.edit` ditambahkan ke `database/seeders/PermissionSeeder.php` (grup Jenis Tes yang sudah ada, sejajar `jenis-tes.view`/`jenis-tes.create`/`jenis-tes.delete`) dan diberikan ke role Admin Lembaga/Admin Yayasan di `RoleSeeder.php` mengikuti pola permission `jenis-tes.*` lain yang sudah ada di sana.

Route baru di `routes/admin.php`:

```php
Route::put('jenis-tes/{jenisTes}', [JenisTesMasterController::class, 'update'])->name('jenis-tes.update');
```

## 5. Backend — Jenis Tagihan: Validasi Nama Unik + JSON + Hapus Rute Lama

`store()` dan `update()` menambah aturan unique pada `nama` (saat ini tidak ada validasi unique sama sekali):

```php
'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_tagihan', 'nama')
    ->where(fn ($query) => $query->where('lembaga_id', $lembagaId))
    ->ignore($jenisTagihan?->id)],
```

`$lembagaId` di `store()` diturunkan dari `session('active_lembaga_id')` (yayasan) atau `$request->user()->lembaga_id` (lembaga), pola identik dengan `JenisTesMasterController::store()`. Di `update()`, `$lembagaId` diambil langsung dari `$jenisTagihan->lembaga_id` (model sudah ter-scope tenant lewat `BelongsToTenant`).

`index()` menambah `withCount(['nominalJalur', 'tagihanItem'])` untuk badge status:

```php
return view('admin.jenis-tagihan.index', [
    'jenisTagihanList' => JenisTagihan::withCount(['nominalJalur', 'tagihanItem'])->orderBy('nama')->get(),
]);
```

`create()` dan `edit()` (method GET, dua halaman terpisah) **dihapus** — digantikan sepenuhnya oleh form inline di index. `store()` dan `update()` menambah cabang JSON, mengikuti pola yang sama seperti Jenis Tes. Route `GET jenis-tagihan/create` dan `GET jenis-tagihan/{jenisTagihan}/edit` dihapus dari `routes/admin.php`; route `POST jenis-tagihan`, `PUT jenis-tagihan/{jenisTagihan}`, `DELETE jenis-tagihan/{jenisTagihan}` tetap ada (dipakai lewat `fetch()`), begitu juga `GET/POST jenis-tagihan/{jenisTagihan}/nominal` (tidak berubah).

**Perilaku redirect setelah create dipertahankan:** pada `store()`, kalau `kategori` bukan `lainnya` (yaitu `pendaftaran`/`daftar_ulang`), response JSON menyertakan field tambahan supaya frontend tahu harus mengarahkan admin langsung ke Kelola Nominal:

```php
if ($request->wantsJson()) {
    return response()->json([
        'data' => $jenisTagihan->fresh(),
        'redirect' => $jenisTagihan->kategori !== 'lainnya'
            ? route('admin.jenis-tagihan.nominal', $jenisTagihan)
            : null,
    ], 201);
}
```

Ini mempertahankan alur UX yang sudah ada sekarang (create redirect langsung ke halaman nominal untuk dua kategori itu) tapi lewat JSON supaya cocok dengan arsitektur AJAX baru. `update()` tidak pernah mengirim `redirect` (edit selalu tetap di index, tidak ada alasan pindah halaman).

## 6. Frontend — Pola Inline Datatable + Form (Dipakai Kedua Halaman)

Pola baru (belum pernah dipakai di codebase ini sebelumnya): satu komponen Alpine per halaman menyimpan baik daftar item maupun state form, dengan `editingId` menentukan mode tambah vs edit. Bentuk `resources/js/jenis-tes-table.js`:

```js
export function jenisTesTable(config) {
    return {
        items: config.initialItems,
        storeUrl: config.storeUrl,
        updateUrlTemplate: config.updateUrlTemplate,
        deleteUrlTemplate: config.deleteUrlTemplate,
        editingId: null,
        form: { nama: '', deskripsi: '' },
        errors: {},
        submitting: false,

        startEdit(item) {
            this.editingId = item.id;
            this.form = { nama: item.nama, deskripsi: item.deskripsi ?? '' };
            this.errors = {};
            this.$refs.formCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },

        cancelEdit() {
            this.editingId = null;
            this.form = { nama: '', deskripsi: '' };
            this.errors = {};
        },

        async submit() {
            this.submitting = true;
            this.errors = {};
            const isEdit = this.editingId !== null;
            const url = isEdit ? this.updateUrlTemplate.replace('__ID__', this.editingId) : this.storeUrl;

            try {
                const response = await fetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(this.form),
                });

                const json = await response.json();

                if (response.status === 422) {
                    this.errors = json.errors ?? {};
                    Alpine.store('toast').push('error', json.message ?? 'Periksa kembali form.');
                    return;
                }

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menyimpan jenis tes.');
                    return;
                }

                if (isEdit) {
                    const index = this.items.findIndex((existing) => existing.id === this.editingId);
                    if (index !== -1) this.items[index] = json.data;
                } else {
                    this.items.push(json.data);
                }

                this.cancelEdit();
                Alpine.store('toast').push('success', isEdit ? 'Jenis tes berhasil diperbarui.' : 'Jenis tes berhasil ditambahkan.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menyimpan jenis tes.');
            } finally {
                this.submitting = false;
            }
        },

        async deleteItem(item) {
            const confirmed = await confirmDialog('Hapus Jenis Tes?', `Apakah Anda yakin ingin menghapus "${item.nama}"?`);
            if (!confirmed) return;

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
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menghapus jenis tes.');
                    return;
                }

                this.items = this.items.filter((existing) => existing.id !== item.id);
                if (this.editingId === item.id) this.cancelEdit();
                Alpine.store('toast').push('success', json.message ?? 'Jenis tes berhasil dihapus.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menghapus jenis tes.');
            }
        },
    };
}
```

Struktur Blade (form card di atas, table di bawah, mengikuti token visual TailAdmin yang sudah dipakai Lembaga/Gelombang/Jalur):

```blade
<div x-data="jenisTesTable({
    initialItems: @js($jenisTesList),
    storeUrl: @js(route('admin.jenis-tes.store')),
    updateUrlTemplate: @js(route('admin.jenis-tes.update', ['jenisTes' => '__ID__'])),
    deleteUrlTemplate: @js(route('admin.jenis-tes.destroy', ['jenisTes' => '__ID__'])),
})" class="space-y-5">

    <div x-ref="formCard" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="font-display text-sm font-bold text-gray-900" x-text="editingId === null ? 'Tambah Jenis Tes' : 'Edit Jenis Tes'"></p>
        <form @submit.prevent="submit()" class="mt-3 flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <x-input-label value="Nama Jenis Tes" />
                <x-text-input type="text" x-model="form.nama" placeholder="mis. Tes Tulis, Wawancara" class="mt-1.5" />
                <p class="mt-1.5 text-sm text-error-600" x-show="errors.nama" x-text="errors.nama?.[0]"></p>
            </div>
            <div class="flex-1 min-w-[200px]">
                <x-input-label value="Deskripsi (Opsional)" />
                <x-text-input type="text" x-model="form.deskripsi" class="mt-1.5" />
            </div>
            <x-primary-button type="submit" x-bind:disabled="submitting" x-text="editingId === null ? 'Tambah' : 'Simpan'"></x-primary-button>
            <x-secondary-button type="button" x-show="editingId !== null" @click="cancelEdit()">Batal</x-secondary-button>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nama</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Deskripsi</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-if="items.length === 0">
                    <tr><td colspan="4" class="px-5 py-6 text-center text-sm text-gray-500">Belum ada jenis tes.</td></tr>
                </template>
                <template x-for="item in items" :key="item.id">
                    <tr>
                        <td class="px-5 py-3 text-sm text-gray-900" x-text="item.nama"></td>
                        <td class="px-5 py-3 text-sm text-gray-500" x-text="item.deskripsi || '—'"></td>
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="item.seleksi_count > 0 ? 'bg-brand-50 text-brand-600' : 'bg-gray-100 text-gray-600'"
                                x-text="item.seleksi_count > 0 ? 'Dipakai di ' + item.seleksi_count + ' Seleksi' : 'Tidak Dipakai'"></span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <x-table-actions>
                                <x-dropdown-link href="#" @click.prevent="startEdit(item)">Edit</x-dropdown-link>
                                <x-dropdown-link href="#" @click.prevent="deleteItem(item)" class="text-error-600">Hapus</x-dropdown-link>
                            </x-table-actions>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>
```

`resources/js/jenis-tagihan-table.js` mengikuti bentuk identik dengan tiga perbedaan: (1) field form tambah `kategori` (select) dan `bisa_dicicil`/`maks_cicilan` (checkbox + field kondisional, mempertahankan pola `x-show="form.bisa_dicicil"` yang sudah ada di `create.blade.php`/`edit.blade.php` lama); (2) sukses `submit()` pada mode tambah (bukan edit) memeriksa `json.redirect` — kalau ada, `window.location.href = json.redirect` alih-alih reset form ke mode tambah lagi; (3) Aksi dropdown di baris tabel punya tiga item: Edit (`startEdit`), Kelola Nominal (`<a href="...">` biasa ke `route('admin.jenis-tagihan.nominal', item.id)`, bukan aksi Alpine — navigasi penuh, disengaja), dan Hapus (`deleteItem`). Badge status: `item.tagihan_item_count > 0` → "Dipakai di N Tagihan" (brass — `bg-brand-50 text-brand-600`), else `item.nominal_jalur_count > 0` → "N Nominal Dikonfigurasi" (biru — `bg-blue-100 text-blue-700`, tone `blue` yang sudah didefinisikan di `resources/views/components/badge.blade.php` untuk status informasional non-final), else "Belum Dipakai" (slate — `bg-gray-100 text-gray-600`).

Kedua komponen didaftarkan di `resources/js/app.js` lewat `Alpine.data('jenisTesTable', jenisTesTable)` / `Alpine.data('jenisTagihanTable', jenisTagihanTable)`, mengikuti pola pendaftaran yang sudah ada untuk `rolesTable`/tiga komponen partial Jalur PPDB.

## 7. Testing

Backend Jenis Tes (`tests/Feature/Admin/JenisTesMasterTest.php`, memperluas suite yang sudah ada):
- `update()` berhasil mengubah nama/deskripsi, ditolak 403 tanpa permission `jenis-tes.edit`, ditolak 422 kalau nama bentrok dengan jenis tes lain di lembaga yang sama (unique `ignore()` diuji dengan mencoba nama milik dirinya sendiri — harus tetap 200).
- `destroy()` tetap menolak (dengan pesan jumlah baru) kalau dipakai di `SeleksiPpdb`, regresi hijau untuk kasus tanpa pemakaian.
- `store()`/`update()`/`destroy()` merespons JSON yang benar saat diminta lewat `Accept: application/json`.

Backend Jenis Tagihan (`tests/Feature/Admin/JenisTagihanTest.php`, memperluas suite yang sudah ada — saat ini nol test untuk guard `destroy()`):
- `destroy()` ditolak dengan pesan tagihan-riil ketika `TagihanItem` ada (test baru, ini yang menutup bug kritis).
- `destroy()` ditolak dengan pesan nominal-terkonfigurasi ketika hanya `NominalTagihanJalur` yang ada (kasus yang sudah ada, belum pernah diuji — ditambahkan sebagai regresi).
- `destroy()` berhasil ketika tidak ada relasi sama sekali.
- `store()`/`update()` menolak 422 saat nama bentrok di lembaga yang sama (test baru, validasi baru).
- `store()` untuk kategori `pendaftaran`/`daftar_ulang` mengembalikan field `redirect` berisi URL nominal yang benar; untuk kategori `lainnya`, `redirect` bernilai `null`.
- Migration test/regresi: `tagihan_item` masih bisa dibuat dan dibaca normal setelah FK diubah ke restrict.

Frontend: di luar cakupan test otomatis Pest (tidak ada browser test di proyek ini) — diverifikasi manual di task final via browser sungguhan, mengikuti pola verifikasi yang sudah dipakai di redesign Jalur PPDB sebelumnya.

## 8. Langkah Berikutnya

Tidak ada item yang didorong ke spec terpisah. Redesign Guru, Tahun Ajaran, Pembayaran, Tagihan, Roles/Users, SPMB Pendaftaran/Verifikasi & Keputusan, dan halaman dashboard tetap di antrean terpisah untuk dikerjakan setelah paket ini selesai.
