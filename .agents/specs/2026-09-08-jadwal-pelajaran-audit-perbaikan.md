# Audit & Perbaikan Menu Jadwal Pelajaran

**Tanggal**: 2026-09-08
**Branch**: `akademik-v2`
**Status**: Draft — menunggu review user sebelum plan+kickoff

## Ringkasan

Audit menu Jadwal Pelajaran (`JadwalPelajaranController` + `resources/views/portals/lembaga/akademik/jadwal-pelajaran/`) mencakup backend, keamanan lintas-tenant, dan UI/UX.

**Backend & keamanan: SUDAH AMAN, TIDAK DIUBAH sama sekali di spec ini.** Model `JadwalPelajaran` sudah pakai `BelongsToTenant` dengan benar (beda dari kasus `JamPelajaran` di menu Pola Jam) — route model binding di `edit()`/`update()`/`destroy()` otomatis terlindungi `TenantScope`. `store()`/`update()`/`duplicate()` malah punya defense-in-depth eksplisit ekstra (cek manual guru/mapel/semester/ruangan harus 1 lembaga dengan kelas). Widget siswa/orang tua (`JadwalPelajaranSiswaController`, `JadwalAnakController`) sudah pakai pola `withoutGlobalScope` + `scopeSemesterAktif()` yang terdokumentasi dari perbaikan bug lintas-tahun-ajaran 27 Agustus 2026 lalu — tidak ada regresi.

Halaman `create.blade.php`/`edit.blade.php` **BUKAN halaman mati** (beda dari kasus Pola Jam) — tombol "+ Tambah Slot Jadwal"/"Edit" pakai `@click.prevent` untuk buka modal TAPI `href`-nya tetap link asli valid, jadi tetap bisa diakses lewat klik-tengah/tab-baru/JS gagal load. Tidak disentuh strukturnya, hanya diperkaya kontennya (Item G).

8 item ditemukan, semua murni frontend/wording/UX — tidak ada perubahan skema database atau query keamanan.

---

## Item A — Dropdown Tahun Ajaran: Tambah Label "(Aktif)" + Suffix Lembaga

### Masalah

`index.blade.php` baris ±63-65 menampilkan Tahun Ajaran polos tanpa penanda status aktif maupun nama lembaga:

```blade
@foreach ($tahunAjaranList as $tahunAjaran)
    <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
@endforeach
```

Beda dari menu Kelas yang sudah benar (`resources/views/admin/kelas/index.blade.php:102`):

```blade
{{ $ta->nama }}{{ $ta->status_aktif ? ' (Aktif)' : '' }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($ta->lembaga->nama ?? '-') : '' }}
```

Tanpa ini: (1) user tidak bisa tahu Tahun Ajaran mana yang sedang aktif dari teks dropdown saja, (2) untuk aktor yayasan-scope mode agregat, 2+ lembaga yang kebetulan punya Tahun Ajaran bernama sama ("2025/2026") tidak bisa dibedakan.

### Perbaikan

**PENTING**: `JadwalPelajaranController.php` SAAT INI belum meng-`use App\Models\Lembaga;` (dicek langsung — tidak ada di daftar import). Tambahkan import ini di bagian atas file. Model `Kelas`, `Semester`, `TahunAjaran`, `Guru`, `JadwalPelajaran` sendiri SUDAH di-`use`, jadi hanya `Lembaga` yang perlu ditambahkan.

**Controller** (`app/Http/Controllers/Admin/JadwalPelajaranController.php`, method `index()`) — perlu tambah `scopeHeaderData()`-style info untuk dipakai view. Karena controller ini sudah mengirim `TahunAjaranList` mentah, cukup tambahkan `isYayasan`/`activeLembaga` ke payload view, pola PERSIS sama seperti yang sudah dipakai `MataPelajaranController`/`KelasController`/`GuruController`/`TahunAjaranController` (SUDAH diverifikasi identik di keempatnya saat audit Pola Jam sebelumnya):

```php
/**
 * Info scope yayasan/lembaga yang sedang aktif, dipakai utk suffix nama lembaga
 * di dropdown Tahun Ajaran saat mode agregat -- HANYA relevan utk aktor
 * berscope yayasan (punya switcher lembaga).
 *
 * @return array{isYayasan: bool, activeLembaga: ?Lembaga}
 */
private function scopeHeaderData(Request $request): array
{
    $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
    $lembagaId = $this->resolveActiveLembagaId($request->user());

    return [
        'isYayasan' => $isYayasan,
        'activeLembaga' => ($isYayasan && $lembagaId) ? Lembaga::withoutGlobalScopes()->find($lembagaId) : null,
    ];
}
```

Dipanggil di return `view('portals.lembaga.akademik.jadwal-pelajaran.index', [...])` (BUKAN di cabang `$request->ajax()`, karena dropdown Tahun Ajaran hanya dirender di halaman penuh, bukan di partial `_daftar`):

```php
return view('portals.lembaga.akademik.jadwal-pelajaran.index', [
    'tahunAjaranList' => TahunAjaran::with('lembaga')->orderByDesc('id')->get(),
    // ...existing keys tetap sama...
    ...$this->scopeHeaderData($request),
]);
```

Catatan: `TahunAjaran::orderByDesc('id')->get()` diubah jadi `TahunAjaran::with('lembaga')->orderByDesc('id')->get()` — eager-load `lembaga` supaya `$ta->lembaga->nama` di view tidak N+1.

**View** (`index.blade.php` baris ±63-65):

```blade
@foreach ($tahunAjaranList as $tahunAjaran)
    <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>
        {{ $tahunAjaran->nama }}{{ $tahunAjaran->status_aktif ? ' (Aktif)' : '' }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($tahunAjaran->lembaga->nama ?? '-') : '' }}
    </option>
@endforeach
```

---

## Item B — Dropdown Semester: Tambah Label "(Aktif)"

### Masalah

`index.blade.php` baris ±73-75, `Semester` juga punya kolom `status_aktif` (dipakai `Semester::activate()`) tapi tidak ditampilkan:

```blade
@foreach ($semesterList as $semester)
    <option value="{{ $semester->id }}" @selected($semesterId == $semester->id)>{{ $semester->nama }}</option>
@endforeach
```

### Perbaikan

```blade
@foreach ($semesterList as $semester)
    <option value="{{ $semester->id }}" @selected($semesterId == $semester->id)>{{ $semester->nama }}{{ $semester->status_aktif ? ' (Aktif)' : '' }}</option>
@endforeach
```

Catatan: Semester tidak butuh suffix lembaga (Semester tidak berelasi langsung ke Lembaga, cuma ke Tahun Ajaran yang sudah dipilih 1 secara eksplisit — begitu Tahun Ajaran dipilih, konteks lembaga sudah tidak ambigu lagi untuk level Semester ke bawah).

Semester juga dipopulate ulang via JS `gantiTahunAjaran()` (`resources/js/jadwal-pelajaran-filter.js` baris ±286-291) saat Tahun Ajaran diganti — baris itu JUGA perlu diupdate supaya konsisten:

```js
json.semesterList.forEach((semester) => {
    const option = document.createElement('option');
    option.value = semester.id;
    option.textContent = semester.nama;
    this.$refs.semesterSelect.appendChild(option);
});
```

menjadi:

```js
json.semesterList.forEach((semester) => {
    const option = document.createElement('option');
    option.value = semester.id;
    option.textContent = semester.nama + (semester.status_aktif ? ' (Aktif)' : '');
    this.$refs.semesterSelect.appendChild(option);
});
```

`opsi()` di controller perlu mengirim `status_aktif` juga (saat ini `->get(['id', 'nama'])` cuma 2 kolom):

```php
'semesterList' => Semester::where('tahun_ajaran_id', $tahunAjaran->id)->orderByDesc('id')->get(['id', 'nama']),
```

menjadi:

```php
'semesterList' => Semester::where('tahun_ajaran_id', $tahunAjaran->id)->orderByDesc('id')->get(['id', 'nama', 'status_aktif']),
```

---

## Item C — Konfirmasi Konteks di Modal "Tambah Slot Jadwal" / "Edit Sesi"

### Masalah

`_modal-form.blade.php` membuka modal langsung begitu tombol diklik, target (`kelas_id`/`semester_id`) cuma hidden input, TIDAK PERNAH ditampilkan ulang ke user sebagai konfirmasi visual sebelum mengisi form. Kalau user sudah scroll/ganti-ganti filter sebelum klik tombol, mereka bisa kehilangan konteks kelas mana yang sedang mereka kerjakan.

### Perbaikan

Tambahkan baris info read-only di atas form modal, setelah header (`_modal-form.blade.php`, setelah baris ±25 sebelum blok `@if (isset($jamPelajaranPerHari) ...)`):

```blade
@if (isset($kelas) && $kelas)
    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg bg-gray-50 px-3.5 py-2.5 text-xs text-gray-600 border border-gray-200">
        <span class="flex items-center gap-1.5">
            <x-icon name="class" class="h-3.5 w-3.5 text-gray-400" />
            Kelas <strong class="font-semibold text-gray-800">{{ $kelas->nama }}</strong>
        </span>
        <span class="flex items-center gap-1.5">
            <x-icon name="event" class="h-3.5 w-3.5 text-gray-400" />
            Semester <strong class="font-semibold text-gray-800">{{ $semesterList->firstWhere('id', $semesterId)?->nama ?? '—' }}</strong>
        </span>
    </div>
@endif
```

Catatan: `$kelas`, `$semesterList`, `$semesterId` SUDAH tersedia di scope `_modal-form.blade.php` — di-`@include` dari `_daftar.blade.php` yang menerima semuanya dari controller (`index()`, baik cabang halaman penuh maupun `$request->ajax()`).

---

## Item D — Konfirmasi Konteks di Modal "Salin dari Kelas Lain"

### Masalah

`_modal-duplicate.blade.php` punya 2 peran kelas yang bisa tertukar: "Kelas Sumber" (dipilih user di dalam modal) vs "Kelas Tujuan" (implisit dari filter luar, cuma hidden input `target_kelas_id`). Tidak ada penegasan visual "menyalin KE kelas apa" sebelum user memilih sumber.

### Perbaikan

Tambahkan baris info read-only setelah header modal (`_modal-duplicate.blade.php`, setelah baris ±25, sebelum `<form>`):

```blade
@if (isset($kelas) && $kelas)
    <div class="mt-3 rounded-lg bg-brand-50 px-3.5 py-2.5 text-xs text-brand-800 border border-brand-200">
        <span class="flex items-center gap-1.5">
            <x-icon name="arrow_forward" class="h-3.5 w-3.5 text-brand-500" />
            Menyalin KE: Kelas <strong class="font-semibold">{{ $kelas->nama }}</strong> · Semester <strong class="font-semibold">{{ $semesterList->firstWhere('id', $semesterId)?->nama ?? '—' }}</strong>
        </span>
    </div>
@endif
```

---

## Item E — Context Banner di Luar Modal (Sebelum Tombol Diklik)

### Masalah

Konfirmasi konteks (Item C, D) baru muncul SETELAH modal terbuka. Sebelum itu — tepat di titik user melihat tombol "Salin dari Kelas Lain"/"+ Tambah Slot Jadwal" dan memutuskan untuk klik — tidak ada penegasan apa pun.

### Perbaikan

Di `index.blade.php`, tambahkan strip kecil di sebelah tombol aksi (baris ±41-55, di dalam `<template x-if="kelasId && semesterId">`):

```blade
<template x-if="kelasId && semesterId">
    <div class="flex flex-wrap items-center gap-3 shrink-0">
        <span class="hidden sm:inline-flex items-center rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600">
            {{ $kelas->nama ?? '' }} · {{ $semesterList->firstWhere('id', $semesterId)?->nama ?? '' }}
        </span>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <button
                type="button"
                @click="openDuplicateModal()"
                class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3.5 py-2.5 text-xs font-semibold text-gray-700 shadow-sm border border-gray-200 hover:bg-gray-50 transition-colors"
            >
                <x-icon name="content_copy" class="h-4 w-4 text-gray-500" />
                <span>Salin dari Kelas Lain</span>
            </button>
            <x-link-button href="#" x-bind:href="tambahSlotUrl()" @click.prevent="openCreateModal()" class="shrink-0 justify-center">
                <span class="text-base leading-none mr-1.5">+</span> Tambah Slot Jadwal
            </x-link-button>
        </div>
    </div>
</template>
```

(Hanya menambah 1 `<span>` badge sebelum `<div>` tombol yang sudah ada — bukan restrukturisasi.)

---

## Item F — Fix Typo "Menyeduh..." → "Menyimpan..."

### Masalah

`_modal-form.blade.php` baris 123:

```blade
<span x-show="formModal.loading">Menyeduh...</span>
```

"Menyeduh" berarti "brewing" (seperti menyeduh kopi) — jelas typo/salah copy-paste, tidak masuk akal sebagai loading-state penyimpanan jadwal.

### Perbaikan

```blade
<span x-show="formModal.loading">Menyimpan...</span>
```

---

## Item G — Samakan Kelengkapan Modal dengan Halaman Penuh (`create.blade.php`)

### Masalah

Modal `_modal-form.blade.php` (jalur utama, dipakai mayoritas user) informasinya lebih sedikit dibanding `create.blade.php` (jalur fallback progressive-enhancement):

1. Dropdown Ruangan modal cuma `— Default Ruang Kelas —` polos; halaman penuh menyebut nama ruangan default eksplisit DAN kapasitas tiap opsi.
2. Modal cuma tampilkan 1 pesan error umum (`formModal.errorMessage`, ambil error pertama saja); halaman penuh tampilkan error PER FIELD di bawah masing-masing input.

### Perbaikan

**Ruangan** — `_modal-form.blade.php` baris ±108-116, tambahkan `$kelas->ruangan` dan kapasitas, mirror persis dari `create.blade.php` baris 108-111:

```blade
<div>
    <x-input-label value="Ruangan Sarpras" />
    <select name="ruangan_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">— Default Ruang Kelas —</option>
        @foreach ($ruanganList ?? [] as $ruangan)
            <option value="{{ $ruangan->id }}">{{ $ruangan->nama_ruangan }}</option>
        @endforeach
    </select>
</div>
```

menjadi:

```blade
<div>
    <x-input-label value="Ruangan Sarpras" />
    <select name="ruangan_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">— Default Ruang Kelas ({{ ($kelas->ruangan ?? null)?->nama_ruangan ?? 'Belum Diatur' }}) —</option>
        @foreach ($ruanganList ?? [] as $ruangan)
            <option value="{{ $ruangan->id }}">{{ $ruangan->nama_ruangan }} (Kapasitas: {{ $ruangan->kapasitas ?? '—' }})</option>
        @endforeach
    </select>
</div>
```

**Error per-field** — perlu 2 perubahan:

`resources/js/jadwal-pelajaran-filter.js`, `submitForm()` (baris ±107-138), tambahkan `formModal.errors` (object) selain `errorMessage` (string, tetap dipakai untuk toast):

```js
formModal: {
    mode: 'create',
    actionUrl: '',
    jam_ids: [],
    jam_id: '',
    mapel_id: '',
    guru_id: '',
    loading: false,
    errorMessage: '',
},
```

menjadi:

```js
formModal: {
    mode: 'create',
    actionUrl: '',
    jam_ids: [],
    jam_id: '',
    mapel_id: '',
    guru_id: '',
    loading: false,
    errorMessage: '',
    errors: {},
},
```

Di `submitForm()`, ganti:

```js
if (!response.ok || data.status === 'error') {
    const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Gagal menyimpan jadwal.');
    this.formModal.errorMessage = firstError;
    Alpine.store('toast').push('error', this.formModal.errorMessage);
}
```

menjadi:

```js
if (!response.ok || data.status === 'error') {
    this.formModal.errors = data.errors || {};
    const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Gagal menyimpan jadwal.');
    this.formModal.errorMessage = firstError;
    Alpine.store('toast').push('error', this.formModal.errorMessage);
}
```

Reset `formModal.errors = {}` juga ditambahkan di `openCreateModal()`/`openEditModal()` (sejajar dengan reset `errorMessage = ''` yang sudah ada di kedua fungsi itu):

```js
openCreateModal(data = {}) {
    this.formModal.mode = 'create';
    this.formModal.actionUrl = this.storeUrlBase;
    this.formModal.jam_ids = data && data.jam_ids ? data.jam_ids.map(String) : [];
    this.formModal.mapel_id = '';
    this.formModal.guru_id = '';
    this.formModal.errorMessage = '';
    this.formModal.errors = {};
    this.showModalForm = true;
    // ...$nextTick block di bawahnya TIDAK berubah...
},

openEditModal(data) {
    this.formModal.mode = 'edit';
    this.formModal.actionUrl = data.url;
    this.formModal.jam_id = String(data.jam_id);
    this.formModal.mapel_id = data.mapel_id ? String(data.mapel_id) : '';
    this.formModal.guru_id = String(data.guru_id);
    this.formModal.errorMessage = '';
    this.formModal.errors = {};
    this.showModalForm = true;
    // ...$nextTick block di bawahnya TIDAK berubah...
},
```

`_modal-form.blade.php`, tambahkan error per-field di bawah masing-masing select (contoh untuk Guru, baris ±98-106 — pola yang sama diulang untuk Mata Pelajaran dan Ruangan):

```blade
<div>
    <x-input-label value="Guru Pengampu" />
    <select name="guru_id" required x-init="initModalGuruSelect($el)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="" disabled>— Pilih Guru —</option>
        @foreach ($guruList ?? [] as $guru)
            <option value="{{ $guru->id }}">{{ $guru->nama }}</option>
        @endforeach
    </select>
    <p x-show="formModal.errors.guru_id" x-text="formModal.errors.guru_id?.[0]" class="mt-1 text-[11px] text-error-600"></p>
</div>
```

Field `jam_pelajaran_id` juga dapat baris error yang sama (di bawah blok `<template x-if>` create/edit).

---

## Item H — Fitur Baru: Salin Jadwal Lintas Tahun Ajaran

### Masalah

`_modal-duplicate.blade.php` saat ini hanya bisa menyalin dari Kelas lain DALAM Tahun Ajaran yang sedang difilter — `$kelasList`/`$semesterList` yang dikirim ke modal sama persis dengan `$kelasList`/`$semesterList` filter utama (di-scope ke `$tahunAjaranId` saat ini). Skenario umum "awal tahun ajaran baru, salin jadwal Kelas 4A tahun lalu ke Kelas 4A tahun ini" TIDAK BISA dilakukan lewat fitur ini.

### Analisis: Backend SUDAH MENDUKUNG, ini murni gap frontend

`JadwalPelajaranController::duplicate()` mengambil `source_kelas_id`/`source_semester_id` lewat `Kelas::findOrFail()`/`Semester::findOrFail()` — TIDAK PEDULI kelas/semester itu dari Tahun Ajaran mana, HANYA peduli `lembaga_id`-nya cocok (`abort_if($sourceKelas->lembaga_id !== $targetKelas->lembaga_id, 404)` di mode agregat, atau harus sama dengan lembaga aktif di mode narrow). **Tidak perlu perubahan controller/action apa pun** — endpoint `opsi()` yang sudah ada (dipakai filter utama) bisa dipakai ulang APA ADANYA untuk modal ini.

### Perbaikan

**View** (`_modal-duplicate.blade.php`) — tambah dropdown "Tahun Ajaran Sumber" SEBELUM "Semester Sumber", ganti render server-side Kelas/Semester Sumber jadi container kosong yang dipopulate JS:

```blade
<div class="space-y-4">
    <div>
        <x-input-label value="Tahun Ajaran Sumber" />
        <select x-ref="duplicateTahunAjaranSelect" x-init="initDuplicateTahunAjaranSelect($refs.duplicateTahunAjaranSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">— Pilih Tahun Ajaran Sumber —</option>
            @foreach ($tahunAjaranList as $ta)
                <option value="{{ $ta->id }}">{{ $ta->nama }}{{ $ta->status_aktif ? ' (Aktif)' : '' }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-[11px] text-gray-400">Boleh dari Tahun Ajaran yang berbeda dari yang sedang dilihat.</p>
    </div>

    <div>
        <x-input-label value="Semester Sumber" />
        <select name="source_semester_id" x-ref="duplicateSemesterSelect" x-model="duplicateForm.source_semester_id" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">— Pilih Tahun Ajaran Sumber Dulu —</option>
        </select>
    </div>

    <div>
        <x-input-label value="Kelas Sumber" />
        <select name="source_kelas_id" x-ref="duplicateKelasSelect" x-model="duplicateForm.source_kelas_id" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">— Pilih Tahun Ajaran Sumber Dulu —</option>
        </select>
        <p class="mt-1 text-[11px] text-gray-400">Pilih kelas yang memiliki konfigurasi jadwal yang ingin diterapkan.</p>
    </div>
</div>
```

`index.blade.php` — teruskan `$tahunAjaranList` ke `_daftar.blade.php` (sudah tersedia di controller `index()`, TAPI saat ini hanya dikirim ke view utama, BUKAN ke cabang `$request->ajax()`) — tambahkan `'tahunAjaranList' => TahunAjaran::with('lembaga')->orderByDesc('id')->get(),` ke payload cabang `if ($request->ajax())` di controller (baris ±94-108).

**JS** (`jadwal-pelajaran-filter.js`) — tambah handler baru `initDuplicateTahunAjaranSelect()`, memakai ulang `opsiUrl` yang sudah ada:

```js
initDuplicateTahunAjaranSelect(el) {
    new TomSelect(el, {
        maxItems: 1,
        create: false,
        placeholder: 'Cari tahun ajaran sumber...',
        onChange: async (value) => {
            this.duplicateForm.source_semester_id = '';
            this.duplicateForm.source_kelas_id = '';
            this.$refs.duplicateSemesterSelect.innerHTML = '<option value="">— Pilih Semester Sumber —</option>';
            this.$refs.duplicateKelasSelect.innerHTML = '<option value="">— Pilih Kelas Sumber —</option>';

            if (!value) return;

            try {
                const url = new URL(this.opsiUrl, window.location.origin);
                url.searchParams.set('tahun_ajaran_id', value);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat opsi kelas dan semester sumber.');
                    return;
                }

                json.semesterList.forEach((semester) => {
                    const option = document.createElement('option');
                    option.value = semester.id;
                    option.textContent = semester.nama + (semester.status_aktif ? ' (Aktif)' : '');
                    this.$refs.duplicateSemesterSelect.appendChild(option);
                });

                json.kelasList.forEach((kelas) => {
                    const option = document.createElement('option');
                    option.value = kelas.id;
                    option.textContent = kelas.nama;
                    this.$refs.duplicateKelasSelect.appendChild(option);
                });
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat opsi kelas dan semester sumber.');
            }
        },
    });
},
```

`openDuplicateModal()` diubah supaya TIDAK mengisi `source_kelas_id`/`source_semester_id` dari state filter utama (karena sekarang dipilih ulang dari dropdown baru):

```js
openDuplicateModal() {
    this.duplicateForm.target_kelas_id = this.kelasId;
    this.duplicateForm.target_semester_id = this.semesterId;
    this.duplicateForm.source_semester_id = this.semesterId;
    this.duplicateForm.source_kelas_id = '';
    this.duplicateForm.errorMessage = '';
    this.showModalDuplicate = true;
},
```

menjadi:

```js
openDuplicateModal() {
    this.duplicateForm.target_kelas_id = this.kelasId;
    this.duplicateForm.target_semester_id = this.semesterId;
    this.duplicateForm.source_semester_id = '';
    this.duplicateForm.source_kelas_id = '';
    this.duplicateForm.errorMessage = '';
    this.showModalDuplicate = true;
},
```

**`opsi()` controller** — sudah dipakai ulang APA ADANYA, TAPI perlu tambahan `status_aktif` di `semesterList` (SAMA dengan perubahan Item B, jadi tidak dobel-hitung sebagai perubahan terpisah).

---

## Di Luar Scope

- Tidak menambah filter/pencarian baru selain yang sudah dibahas.
- Backend/query/keamanan TIDAK diubah sama sekali — semua defense-in-depth existing (`abort_if` cross-lembaga) tetap apa adanya.
- Bug sistemik `TenantScope` untuk aktor platform-scope TIDAK disentuh — backlog terpisah, konsisten dengan semua spec sebelumnya di rangkaian audit ini.
- `_matrix-roster.blade.php` (tampilan matriks mingguan) TIDAK disentuh — sudah pakai `confirmDialog()` dengan benar, tidak ada temuan di situ.
- `JadwalPelajaranSiswaController`/`JadwalAnakController` (widget siswa/orang tua) TIDAK disentuh — sudah benar, di luar cakupan menu admin Jadwal Pelajaran.

## Tabel Panduan Test

| Item | Test yang dibutuhkan |
|---|---|
| A | Dropdown Tahun Ajaran menampilkan "(Aktif)" untuk TA aktif, suffix lembaga saat agregat, TIDAK ada suffix saat narrow/lembaga-scope |
| B | Dropdown Semester (server-render awal DAN hasil AJAX `gantiTahunAjaran`) menampilkan "(Aktif)" |
| C | Modal Tambah/Edit menampilkan nama kelas & semester read-only |
| D | Modal Duplikat menampilkan "Menyalin KE: ..." dengan nama kelas & semester TUJUAN |
| E | Banner konteks (nama kelas · semester) tampil di sebelah tombol aksi setelah filter lengkap |
| F | Response TIDAK mengandung teks "Menyeduh", modal berisi "Menyimpan..." |
| G | Dropdown Ruangan modal menampilkan nama+kapasitas; response error mengandung pesan spesifik per field (bukan cuma 1 pesan umum) |
| H | Modal Duplikat bisa memuat Kelas Sumber dari Tahun Ajaran BERBEDA dari yang sedang difilter; `duplicate()` backend tetap menolak kalau lintas LEMBAGA (regresi test existing "rejects...") |
