# Spec: Restyle Tabel Daftar — Menu Kurikulum Assignment (Susulan)

> **Branch**: `akademik-v2` (setara `rbac-v2`)
> **Tanggal**: 8 September 2026
> **Latar belakang**: Susulan ke-3 dari audit Kurikulum Assignment. Tabel index (`resources/views/admin/kurikulum-assignment/index.blade.php`) memakai gaya visual LAMA (`shadow-sm`, header `bg-gray-50` polos, aksi teks biasa rata-kanan) — berbeda dari konvensi tabel modern yang sudah dipakai HAMPIR SEMUA modul lain (Mata Pelajaran, Kelas, Karyawan, dst.): card dengan header judul, header tabel `uppercase tracking-wider`, kolom "Aksi" sticky-left dengan dropdown `<x-table-actions>`. User minta disamakan, contoh acuan: tabel Mata Pelajaran (`resources/views/portals/lembaga/akademik/mata-pelajaran/_daftar.blade.php`).
>
> **Ini MURNI perubahan visual/markup Blade** — TIDAK ADA logic, query, controller, atau data yang berubah sama sekali. Variabel yang dipakai (`$assignmentList`, `$a->canManage`, `$a->lembaga_id`, dst.) SAMA PERSIS seperti sekarang.

## Keputusan yang Diambil

1. **HANYA tabel yang di-restyle** — header halaman (judul "Pengaturan Kurikulum", tombol "Cek & Perbaiki Kurikulum/Fase" + "Tambah Assignment") TIDAK diubah, sudah cukup konsisten dengan pola action-button-di-kanan yang juga dipakai modul lain.
2. **TIDAK menambahkan fitur baru** (pagination, search, filter, AJAX partial reload) — Mata Pelajaran PUNYA fitur-fitur itu, tapi user minta "restyling", bukan "tambah fitur". Kurikulum Assignment secara alami jumlah barisnya kecil (konfigurasi admin, bukan data per-siswa), TIDAK butuh pagination. Kalau nanti dibutuhkan, itu permintaan terpisah.
3. **Aksi "Edit"/"Hapus" DIPINDAH ke kolom sticky-left dengan dropdown `<x-table-actions>`** (pola SAMA PERSIS seperti Mata Pelajaran/Kelas), MENGGANTI 2 teks link rata-kanan yang sekarang — mekanismenya (link `<a>` biasa untuk Edit, `<form method="POST">` + `onsubmit="return confirm(...)"` untuk Hapus) TIDAK diubah sama sekali, cuma dibungkus ulang secara visual. TIDAK ada JS/Alpine baru, TIDAK ada AJAX fetch.
4. **Baris "Read-only (Platform)" (untuk `$a->canManage === false`) TETAP teks biasa**, bukan dropdown kosong — konsisten dengan makna aslinya (tidak ada aksi yang bisa dilakukan sama sekali di baris itu).

---

## Restyle Tabel

**File**: `resources/views/admin/kurikulum-assignment/index.blade.php`

Kode saat ini (baris ±31-86, seluruh blok tabel):
```blade
<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Scope</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Tahun Ajaran</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Bentuk Pendidikan</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Tingkat</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Kurikulum</th>
                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white">
            @forelse ($assignmentList as $a)
                <tr class="hover:bg-gray-50">
                    <td class="whitespace-nowrap px-6 py-3.5 text-sm">
                        @if ($a->lembaga_id === null)
                            <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">Platform Default</span>
                        @else
                            <span class="inline-flex rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700">{{ $a->lembaga->nama ?? 'Lembaga #' . $a->lembaga_id }}</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-6 py-3.5 text-sm text-gray-600">{{ $a->tahunAjaran->nama ?? '-' }}</td>
                    <td class="whitespace-nowrap px-6 py-3.5 text-sm font-semibold text-gray-900">{{ $a->bentuk_pendidikan }}</td>
                    <td class="whitespace-nowrap px-6 py-3.5 text-sm text-gray-600">{{ $a->tingkat ?? 'Semua Tingkat' }}</td>
                    <td class="whitespace-nowrap px-6 py-3.5 text-sm text-gray-900">{{ $a->kurikulum->label() }}</td>
                    <td class="whitespace-nowrap px-6 py-3.5 text-right text-sm">
                        @if ($a->canManage)
                            <div class="inline-flex items-center gap-2">
                                @can('kurikulum-assignment.edit')
                                    <a href="{{ route('admin.kurikulum-assignment.edit', $a) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Edit</a>
                                @endcan
                                @can('kurikulum-assignment.delete')
                                    <form method="POST" action="{{ route('admin.kurikulum-assignment.destroy', $a) }}" onsubmit="return confirm('Hapus assignment ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-error-600 hover:text-error-700">Hapus</button>
                                    </form>
                                @endcan
                            </div>
                        @else
                            <span class="text-xs text-gray-400">Read-only (Platform)</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada assignment kurikulum yang dikonfigurasi.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
```

Fix (restyle penuh, pola SAMA PERSIS `mata-pelajaran/_daftar.blade.php`):
```blade
<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Assignment Kurikulum</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50/75 font-display text-xs font-bold uppercase tracking-wider text-gray-500">
                    <th class="sticky left-0 z-10 bg-gray-50/75 px-5 py-3 w-32">Aksi</th>
                    <th class="px-4 py-3">Scope</th>
                    <th class="px-4 py-3">Tahun Ajaran</th>
                    <th class="px-4 py-3">Bentuk Pendidikan</th>
                    <th class="px-4 py-3">Tingkat</th>
                    <th class="px-5 py-3">Kurikulum</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 font-normal">
                @forelse ($assignmentList as $a)
                    <tr class="transition-colors hover:bg-gray-50/60">
                        <td class="sticky left-0 z-10 bg-white px-5 py-3">
                            @if ($a->canManage)
                                <x-table-actions>
                                    @can('kurikulum-assignment.edit')
                                        <x-dropdown-link href="{{ route('admin.kurikulum-assignment.edit', $a) }}">
                                            <span class="inline-flex items-center gap-2.5">
                                                <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                Edit Assignment
                                            </span>
                                        </x-dropdown-link>
                                    @endcan
                                    @can('kurikulum-assignment.delete')
                                        <form method="POST" action="{{ route('admin.kurikulum-assignment.destroy', $a) }}" onsubmit="return confirm('Hapus assignment ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-error-600 transition duration-150 ease-in-out hover:bg-error-50 focus:bg-error-50 focus:outline-none">
                                                <x-icon name="delete" class="h-4 w-4" />
                                                Hapus Assignment
                                            </button>
                                        </form>
                                    @endcan
                                </x-table-actions>
                            @else
                                <span class="text-xs text-gray-400">Read-only (Platform)</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3.5">
                            @if ($a->lembaga_id === null)
                                <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">Platform Default</span>
                            @else
                                <span class="inline-flex rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700">{{ $a->lembaga->nama ?? 'Lembaga #'.$a->lembaga_id }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3.5 text-gray-600">{{ $a->tahunAjaran->nama ?? '-' }}</td>
                        <td class="whitespace-nowrap px-4 py-3.5 font-semibold text-gray-900">{{ $a->bentuk_pendidikan }}</td>
                        <td class="whitespace-nowrap px-4 py-3.5 text-gray-600">{{ $a->tingkat ?? 'Semua Tingkat' }}</td>
                        <td class="whitespace-nowrap px-5 py-3.5 text-gray-900">{{ $a->kurikulum->label() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-500">
                            <p class="text-sm">Belum ada assignment kurikulum yang dikonfigurasi.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
```

**Rincian perubahan**:
- Card wrapper: `shadow-sm` → `shadow-card`, tambah header judul "Daftar Assignment Kurikulum" (pola Mata Pelajaran).
- Header tabel: `bg-gray-50` polos → `bg-gray-50/75` + `font-display text-xs font-bold uppercase tracking-wider` (pola SEMUA tabel modern di aplikasi ini).
- Kolom "Aksi" DIPINDAH dari paling kanan (rata-kanan, teks biasa) ke paling KIRI, `sticky left-0 z-10` (dropdown `<x-table-actions>` + `<x-dropdown-link>`, komponen SUDAH ADA & dipakai luas — dikonfirmasi lewat pembacaan `resources/views/components/table-actions.blade.php`/`dropdown-link.blade.php`, keduanya SELF-CONTAINED, tidak butuh `x-data` tambahan di parent).
- Tombol "Hapus" di dalam dropdown TETAP `<form method="POST">` + `onsubmit="return confirm(...)"` yang SAMA PERSIS seperti sekarang — HANYA class CSS-nya yang diganti supaya terlihat seperti item dropdown (pola disalin dari `jenis-karyawan-master/index.blade.php`'s tombol Hapus).
- Baris hover: `hover:bg-gray-50` → `transition-colors hover:bg-gray-50/60`.
- Empty-state: teks polos 1 baris → dibungkus `<p class="text-sm">` (pola Mata Pelajaran), `colspan` TETAP `6` (jumlah kolom tidak berubah, cuma urutannya — Aksi pindah ke depan).
- Teks tombol aksi diperjelas: "Edit" → "Edit Assignment", "Hapus" → "Hapus Assignment" (konsisten pola modul lain yang menyebut nama entitas di label aksi, bukan cuma kata kerja generik).

---

## Di Luar Scope / Backlog Terpisah

1. **Pagination/search/filter** — Mata Pelajaran punya, Kurikulum Assignment SENGAJA TIDAK ditambahkan (lihat Keputusan #2) — jumlah baris di modul ini secara alami kecil (konfigurasi admin per jenjang/tingkat/tahun ajaran, bukan data per-siswa).
2. **Header halaman (judul, breadcrumb, tombol atas)** — TIDAK disentuh, sudah cukup konsisten pola modul lain.
3. **`resync.blade.php`** ("Cek & Perbaiki Kurikulum/Fase") — halaman terpisah, TIDAK diaudit/di-restyle di spec ini.

---

## Ringkasan Test yang Wajib Diperbarui

Karena ini MURNI perubahan markup (bukan logic), SEMUA test existing yang menge-`assertSee`/`assertDontSee` konten data (nama lembaga, nama tahun ajaran, label kurikulum, dst.) TETAP valid tanpa perubahan — konten tersebut TIDAK berpindah, cuma pembungkus visualnya. Test yang PERLU disesuaikan:

| Test existing | Penyesuaian |
|---|---|
| Test manapun yang meng-assert teks bare `'Edit'`/`'Hapus'` (BELUM ADA saat ini, dikonfirmasi lewat pembacaan test file — SEMUA test yang cek aksi Edit/Hapus memakai `route(...)` URL, bukan teks label) | TIDAK ADA yang perlu diubah — aman |

Test BARU (regresi ringan untuk restyle):
```php
it('renders the restyled Aksi dropdown with explicit action labels for a manageable row', function () {
    $lembaga = Lembaga::factory()->create();
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.index'))->assertOk()
        ->assertSee('Edit Assignment')
        ->assertSee('Hapus Assignment');
});
```

Regresi wajib: seluruh `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` (SEMUA test existing dari spec-spec sebelumnya, termasuk test yang mengecek `assertDontSee(route('admin.kurikulum-assignment.edit', $assignmentGlobal))` — URL rute TIDAK berubah, jadi tetap valid).
