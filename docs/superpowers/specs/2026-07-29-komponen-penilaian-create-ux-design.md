# Komponen Penilaian (TP) — Halaman Create UX

## Goal

Tutup gap terakhir pada fitur Komponen Penilaian: halaman "Tambah Komponen Penilaian" tidak punya konsep Tahun Ajaran sama sekali, sehingga dropdown Semester ("Ganjil"/"Genap") ambigu — tidak jelas masuk tahun berapa. Tambahkan pilihan Tahun Ajaran yang men-cascade ke Semester (reuse endpoint `opsi()` yang sudah dibangun di paket index), dan jadikan semua dropdown Tom Select.

## Requirements

1. Tambah dropdown **Tahun Ajaran** di atas Mata Pelajaran/Semester, Tom Select, default terpilih ke Tahun Ajaran aktif.
2. Dropdown **Semester** men-cascade dari Tahun Ajaran terpilih — di-disable sampai Tahun Ajaran dipilih, opsinya diambil via endpoint `admin.komponen-penilaian.opsi` yang **sudah ada** dari paket sebelumnya (tidak perlu endpoint baru).
3. Dropdown **Mata Pelajaran** jadi Tom Select (daftar penuh per lembaga, tidak cascading — mata pelajaran tidak terikat tahun ajaran).
4. Tahun Ajaran **tidak disimpan** ke `KomponenPenilaian` (kolomnya tidak ada) — perannya murni UI untuk mempersempit opsi Semester. Tapi kalau validasi form gagal (mis. Deskripsi kosong), pilihan Tahun Ajaran & Semester yang sudah dipilih user harus tetap dipertahankan saat form dimuat ulang (`old()`), bukan reset ke default.
5. `store()` **tidak diubah** — validasi lembaga silang yang sudah ada (`mataPelajaran->lembaga_id !== $semester->lembaga_id`) sudah cukup karena keamanan bergantung pada `semester_id` yang benar-benar dikirim, bukan pada `tahun_ajaran_id` (yang hanya UI).

## Arsitektur

### Backend — `KomponenPenilaianController::create()`

```php
public function create(Request $request): View
{
    $this->authorize('komponen-penilaian.kelola');

    $tahunAjaranId = old('tahun_ajaran_id', $request->query('tahun_ajaran_id'));
    if (! $tahunAjaranId) {
        $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
    }

    return view('admin.komponen-penilaian.create', [
        'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
        'tahunAjaranId' => $tahunAjaranId,
        'semesterList' => $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect(),
        'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
    ]);
}
```

`old('tahun_ajaran_id', ...)` menangani redisplay setelah validasi `store()` gagal (Laravel redirect-back membawa flashed input dari request `store()` sebelumnya, termasuk `tahun_ajaran_id` kalau dikirim sebagai field form — lihat di bawah). Kalau tidak ada `old()` maupun query param, default ke Tahun Ajaran aktif.

### Frontend — `create.blade.php`

Ubah grid 2-kolom (Mata Pelajaran, Semester) jadi 3-kolom (Tahun Ajaran, Semester, Mata Pelajaran):

```blade
<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div>
        <x-input-label value="Tahun Ajaran *" />
        <select
            name="tahun_ajaran_id"
            required
            x-ref="tahunAjaranSelect"
            x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)"
            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
        >
            <option value="">— Pilih Tahun Ajaran —</option>
            @foreach ($tahunAjaranList as $tahunAjaran)
                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label value="Semester *" />
        <select
            name="semester_id"
            required
            x-ref="semesterSelect"
            x-init="initSemesterSelect($refs.semesterSelect)"
            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
        >
            <option value="">— Pilih Semester —</option>
            @foreach ($semesterList as $semester)
                <option value="{{ $semester->id }}" @selected(old('semester_id') == $semester->id)>{{ $semester->nama }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('semester_id')" class="mt-1" />
    </div>

    <div>
        <x-input-label value="Mata Pelajaran *" />
        <select
            name="mata_pelajaran_id"
            required
            x-ref="mataPelajaranSelect"
            x-init="initMataPelajaranSelect($refs.mataPelajaranSelect)"
            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
        >
            <option value="">— Pilih Mata Pelajaran —</option>
            @foreach ($mataPelajaranList as $mapel)
                <option value="{{ $mapel->id }}" @selected(old('mata_pelajaran_id') == $mapel->id)>{{ $mapel->nama }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('mata_pelajaran_id')" class="mt-1" />
    </div>
</div>
```

`<form>` mendapat `x-data="komponenPenilaianCreateForm({ tahunAjaranId: @js($tahunAjaranId), opsiUrl: @js(route('admin.komponen-penilaian.opsi')) })"`. `tahun_ajaran_id` DIKIRIM sebagai field form asli (bukan cuma state Alpine) — supaya `old('tahun_ajaran_id')` di controller bisa memulihkannya kalau validasi gagal. `store()` mengabaikan field ini (tidak ada di `$request->validate([...])`), jadi aman dikirim tanpa efek samping.

### JS baru — `resources/js/komponen-penilaian-create.js`

```js
import TomSelect from 'tom-select';

export function komponenPenilaianCreateForm(config) {
    return {
        tahunAjaranId: config.tahunAjaranId ?? '',
        opsiUrl: config.opsiUrl,
        semesterTomSelect: null,

        initTahunAjaranSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari tahun ajaran...',
                onChange: (value) => {
                    this.tahunAjaranId = value;
                    this.gantiTahunAjaran(value);
                },
            });
        },

        initSemesterSelect(el) {
            this.semesterTomSelect = new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari semester...',
            });

            if (!this.tahunAjaranId) {
                this.semesterTomSelect.disable();
            }
        },

        initMataPelajaranSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari mata pelajaran...',
            });
        },

        async gantiTahunAjaran(tahunAjaranId) {
            this.semesterTomSelect?.clear(true);
            this.semesterTomSelect?.clearOptions();

            if (!tahunAjaranId) {
                this.semesterTomSelect?.disable();
                return;
            }

            this.semesterTomSelect?.enable();

            try {
                const url = new URL(this.opsiUrl, window.location.origin);
                url.searchParams.set('tahun_ajaran_id', tahunAjaranId);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat opsi semester.');
                } else {
                    json.semesterList.forEach((semester) => {
                        this.semesterTomSelect.addOption({ value: String(semester.id), text: semester.nama });
                    });
                    this.semesterTomSelect.refreshOptions(false);
                }
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat opsi semester.');
            }
        },
    };
}
```

Ini bukan duplikasi cascading logic `komponen-penilaian-filter.js` — modul itu juga memicu `muatUlangDaftar()` (reload daftar AJAX) yang tidak relevan di halaman create (form single-submit, bukan filter live). Modul baru ini jauh lebih kecil (tanpa mata pelajaran/search reload, tanpa `perbaruiUrl()`).

## Self-Review

- **Tidak ada endpoint/route baru** — reuse `admin.komponen-penilaian.opsi` yang sudah dibangun dan sudah diuji di paket index.
- **`store()` tidak disentuh** — keamanan tetap bertumpu pada validasi `semester_id` yang sesungguhnya dikirim, `tahun_ajaran_id` murni kosmetik UI.
- **Cakupan scope**: hanya `create()` + `create.blade.php` + 1 modul JS baru. Tidak menyentuh index/edit/delete (sudah selesai) atau `store()`.
