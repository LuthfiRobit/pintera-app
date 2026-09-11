# Pola Jam & Jam Pelajaran Audit Perbaikan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perbaiki modul Pola Jam & Jam Pelajaran (`resources/views/portals/lembaga/akademik/pola-jam/*`) sesuai temuan audit UI/UX: perbaiki 5 nama icon yang tidak terdaftar (render jadi "?"), ringkas tampilan tautan kelas, permudah input slot jam pelajaran, ganti daftar harian jadi tab navigasi, perbaiki label matriks mingguan, tambah pencarian di modal assign kelas, tambah konteks lembaga + `<x-select>` di modal lain, dan tambah 2 KPI card ringkas.

**Architecture:** Perubahan MURNI presentational (Blade + Alpine.js client-side) plus 1 penambahan `@case` di komponen icon bersama. TIDAK ADA perubahan controller, action, DTO, FormRequest, atau domain layer apa pun — seluruh data yang dibutuhkan sudah tersedia dari eager-load `PolaJamController::index()` yang ada saat ini.

**Tech Stack:** Laravel 12 (Blade), Alpine.js (x-data lokal per komponen, pola yang sudah dipakai di file ini sendiri), Pest untuk test.

## Global Constraints

- JANGAN implementasikan auto-increment urutan & jam mulai di form slot — sengaja dikeluarkan (asumsi hari/urutan acuan tidak jelas untuk form multi-hari sekaligus; salah-suggest lebih berbahaya daripada field kosong yang jelas kosong).
- JANGAN redesain ulang pembedaan warna Jam Belajar vs Non-KBM di Matriks Mingguan — SUDAH ADA di kode saat ini (`bg-brand-50/40 border-brand-200 ...` vs `bg-gray-50 border-gray-200 ...`), menyentuhnya lagi adalah scope creep.
- JANGAN tambah KPI "Rata-rata Jam Belajar per Hari" — definisi bisnisnya ambigu (PAUD/SD/SMP punya hari aktif berbeda, satu lembaga bisa punya banyak Pola Jam dengan cakupan hari aktif masing-masing).
- JANGAN redesain besar-besaran Matriks Mingguan — HANYA ganti label kolom kiri. Per-sel matriks SUDAH akurat (setiap sel sudah menampilkan jam mulai-selesai miliknya sendiri via `Carbon::parse(...)->format('H:i')`), klaim audit awal soal ini berlebihan dan sudah dikoreksi di spec.
- JANGAN sentuh `PolaJamController.php`, `JamPelajaranController.php`, atau domain layer manapun (`Actions/`, `DataTransferObjects/`) — semua data yang dibutuhkan (KPI, tautan kelas, dsb) SUDAH tersedia dari `with(['jamPelajaran', 'lembaga', 'kelas.tahunAjaran'])` yang ada di `index()` saat ini.
- Preset label slot WAJIB pakai `<datalist>` HTML native (BUKAN pill button seperti preset Hari) — field Label adalah teks bebas (banyak sekolah punya nama sesi custom di luar 5 preset), `<datalist>` memberi saran cepat TANPA mengunci input hanya ke opsi preset; pill button cocok untuk field dengan domain nilai terbatas (seperti Hari), tidak cocok di sini.

## ⚠️ Catatan Wajib Soal Urutan Eksekusi — BACA SEBELUM MULAI TASK APA PUN

**`index.blade.php` disentuh oleh 7 dari 9 task (Task 1, 2, 3, 4, 5, 6, 8).** Ini BERBEDA dari plan-plan sebelumnya di sesi ini yang task-nya lebih independen. Task 1, 2, 3, 4, 5, 6, 8 **WAJIB dikerjakan SEKUENSIAL dalam urutan angka itu** — TIDAK BOLEH didispatch sebagai subagent paralel satu sama lain, walaupun secara nomor task terlihat berurutan biasa. Ini **hard requirement**, bukan sekadar saran, karena SEMUA task itu mengedit file yang sama (`index.blade.php`) dan mengerjakannya paralel AKAN menyebabkan salah satu overwrite hasil kerja yang lain.

**HANYA Task 7** (`_modal-pola.blade.php` + `_modal-edit-slot.blade.php`, file yang sama sekali berbeda dari `index.blade.php`) **AMAN dikerjakan paralel** dengan Task 1-6/8 kalau memakai subagent-driven-development.

**Task 9 (regression sweep) WAJIB paling akhir**, setelah SEMUA task lain (termasuk Task 7) selesai.

**Untuk setiap task yang mengedit `index.blade.php` (Task 2, 3, 4, 5, 6, 8): implementer WAJIB membaca ulang isi file `index.blade.php` TERKINI dulu sebelum mengedit** (pakai Read tool), BUKAN berasumsi dari kode "Current" yang dikutip di task sebelumnya — karena task sebelumnya di urutan sekuensial ini SUDAH mengubah file itu. Kode "Current"/"cari teks ini" yang dikutip di tiap task di bawah mengacu ke **state file SETELAH task-task sebelumnya selesai**, bukan ke file original sebelum plan ini mulai — sudah ditandai eksplisit di tiap task mana yang berubah dari task sebelumnya.

---

## Task 1: Perbaiki 5 Nama Icon Rusak

**Files:**
- Modify: `resources/views/components/icon.blade.php`
- Modify: `resources/views/portals/lembaga/akademik/pola-jam/index.blade.php`
- Test: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:**
- Consumes: tidak ada.
- Produces: `@case('content_copy')` baru di komponen icon global (dipakai lagi oleh Task 2 di file yang sama, dan otomatis memperbaiki halaman `jadwal-pelajaran` sebagai efek samping — TIDAK PERLU aksi tambahan untuk itu).

Ini task **PALING KRITIS** dan **PRASYARAT untuk Task 2** (Task 2 mengasumsikan nama icon `school`/`groups` sudah benar di blok yang sama).

- [ ] **Step 1: Tulis test yang gagal — halaman pola-jam TIDAK menampilkan atribut nama icon yang rusak**

Tambahkan di akhir `tests/Feature/Admin/PolaJamCrudTest.php`:
```php
it('tidak ada nama icon rusak (class/playlist_add/grid_view/add_circle/content_copy) yang bocor sebagai teks literal di halaman pola-jam', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertDontSee('name="class"', false);
    $response->assertDontSee('name="playlist_add"', false);
    $response->assertDontSee('name="grid_view"', false);
    $response->assertDontSee('name="add_circle"', false);
    $response->assertDontSee('name="content_copy"', false);
});
```
(`assertDontSee` mencari **string literal `name="..."` di HTML output** — ini valid karena `<x-icon name="...">` HARUS sudah ter-compile jadi elemen `<svg>` murni oleh Blade sebelum sampai ke response; kalau nama itu masih muncul literal berarti ada bug lain di luar scope task ini. Cara yang BENAR untuk membuktikan "icon-nya sekarang valid" adalah lewat Step 6 di bawah — cek langsung isi `icon.blade.php`, bukan lewat assertion HTML yang tidak bisa membedakan ikon valid vs ikon fallback "?" karena keduanya sama-sama render `<svg>`.)

- [ ] **Step 2: Jalankan test, catat hasil (BOLEH pass atau fail di titik ini)**

Run: `php artisan test --filter="tidak ada nama icon rusak" --compact`
Expected: kemungkinan besar PASS (karena nama icon rusak memang TIDAK pernah muncul sebagai teks literal `name="..."` di HTML — Blade sudah meng-compile atribut `name` component JADI PARAMETER internal, bukan atribut HTML akhir). Test ini BUKAN pembukti utama — pembukti utama ada di Step 6.

- [ ] **Step 3: Tambah `@case('content_copy')` baru di `resources/views/components/icon.blade.php`**

Buka file, sisipkan blok berikut DI MANA SAJA di antara `@case` yang sudah ada (sebelum baris `@default`):
```blade
    @case('content_copy')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        @break

```

- [ ] **Step 4: Ganti 4 nama icon di `index.blade.php`**

Buka `resources/views/portals/lembaga/akademik/pola-jam/index.blade.php`, cari dan ganti 4 baris berikut (masing-masing HANYA atribut `name`, tidak ada perubahan lain di baris itu):

1. Baris berisi `<x-icon name="class" class="h-3.5 w-3.5 text-gray-400" />` (di dalam label "Tautan Kelas:") → ganti `name="class"` jadi `name="school"`.
2. Baris berisi `<x-icon name="playlist_add" class="h-4 w-4 text-brand-500" />` (di header "Input Slot Jam Pelajaran Baru") → ganti `name="playlist_add"` jadi `name="assignment_add"`.
3. Baris berisi `<x-icon name="add_circle" class="h-4 w-4 text-brand-500" />` (di tombol "Kelola Tautan") → ganti `name="add_circle"` jadi `name="groups"`.
4. Baris berisi `<x-icon name="grid_view" class="h-3.5 w-3.5 text-brand-500" />` (di toggle "Matriks Mingguan") → ganti `name="grid_view"` jadi `name="data_table"`.

(`content_copy` di tombol "Duplikat" TIDAK diganti namanya — sudah benar sebagai NAMA, yang kurang adalah case-nya di komponen icon, sudah ditambahkan Step 3.)

- [ ] **Step 5: Jalankan test Step 1 lagi, pastikan tetap PASS**

Run: `php artisan test --filter="tidak ada nama icon rusak" --compact`
Expected: PASS.

- [ ] **Step 6: Verifikasi manual — pastikan SEMUA nama icon yang dipakai `index.blade.php` sekarang punya `@case` yang valid**

Run: `grep -oE 'x-icon name="[a-z_]+"' resources/views/portals/lembaga/akademik/pola-jam/index.blade.php | grep -oE 'name="[a-z_]+"' | sort -u`
Run: `grep -oE "@case\('[a-z_]+'\)" resources/views/components/icon.blade.php | sort -u`
Expected: setiap nama dari hasil grep PERTAMA (dipakai di `index.blade.php`) HARUS ada di hasil grep KEDUA (terdaftar di komponen). Kalau ada yang tidak ketemu, ulangi Step 3/4 sampai cocok.

- [ ] **Step 7: Format & commit**

```bash
git add resources/views/components/icon.blade.php resources/views/portals/lembaga/akademik/pola-jam/index.blade.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "fix(pola-jam): perbaiki 5 nama icon rusak (school/assignment_add/groups/data_table + content_copy baru)"
```

---

## Task 2: Ringkas Tautan Kelas (Aktif vs Arsip, Expand/Collapse)

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/pola-jam/index.blade.php` (state SETELAH Task 1 — icon `school`/`groups` sudah benar)
- Test: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:**
- Consumes: icon `school`/`groups` dari Task 1 (dipakai langsung di kode task ini).
- Produces: tidak ada interface baru untuk task lain.

- [ ] **Step 1: Baca ulang `index.blade.php` TERKINI**

Run: Read tool pada `resources/views/portals/lembaga/akademik/pola-jam/index.blade.php` — cari blok yang diawali komentar `{{-- 2. Tautan Kelas (Pill Tags & Smart Assign Button) --}}` (blok ini SEKARANG sudah punya `name="school"` dan `name="groups"` hasil Task 1, bukan `class`/`add_circle` lagi).

- [ ] **Step 2: Tulis test yang gagal — ringkasan jumlah kelas aktif/arsip muncul**

Tambahkan di akhir `tests/Feature/Admin/PolaJamCrudTest.php`:
```php
it('tautan kelas menampilkan ringkasan jumlah kelas aktif vs arsip, bukan menumpuk semua pill mentah', function () {
    Permission::firstOrCreate(['name' => 'kelas.edit', 'guard_name' => 'web']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $manager->givePermissionTo('kelas.edit');
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $taAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $taArsip = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => false]);

    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taAktif->id, 'pola_jam_id' => $pola->id, 'nama' => 'Kelas 1A Aktif']);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taArsip->id, 'pola_jam_id' => $pola->id, 'nama' => 'Kelas 1A Arsip']);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('1 kelas aktif');
    $response->assertSee('1 arsip');
});
```
- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="tautan kelas menampilkan ringkasan" --compact`
Expected: FAIL — teks "1 kelas aktif" / "1 arsip" belum ada.

- [ ] **Step 4: Ganti blok "Tautan Kelas" di `index.blade.php`**

Cari blok yang diawali `{{-- 2. Tautan Kelas (Pill Tags & Smart Assign Button) --}}` sampai `@endcan` penutupnya, ganti SELURUH isinya (dari `@can('kelas.edit')` sampai `@endcan`) menjadi:
```blade
@can('kelas.edit')
    <div class="border-b border-gray-100 bg-gray-50/60 px-6 py-3.5" x-data="{ tampilkanArsip: false }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 flex items-center gap-1">
                    <x-icon name="school" class="h-3.5 w-3.5 text-gray-400" />
                    <span>Tautan Kelas:</span>
                </span>
                @php
                    $kelasAktif = $pola->kelas->filter(fn ($k) => $k->tahunAjaran?->status_aktif);
                    $kelasArsip = $pola->kelas->reject(fn ($k) => $k->tahunAjaran?->status_aktif);
                @endphp
                <span class="text-xs text-gray-600 font-medium">
                    {{ $kelasAktif->count() }} kelas aktif
                    @if ($kelasArsip->isNotEmpty())
                        &bull; {{ $kelasArsip->count() }} arsip
                    @endif
                </span>
            </div>
            <button type="button"
                    @click="openAssignModal({{ $pola->toJson() }}, {{ $pola->kelas->pluck('id')->values()->toJson() }}, '{{ route('admin.pola-jam.assign-kelas', $pola) }}')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-2xs hover:bg-gray-50 transition active:scale-95 shrink-0">
                <x-icon name="groups" class="h-4 w-4 text-brand-500" />
                <span>Kelola Tautan</span>
            </button>
        </div>

        @if ($pola->kelas->isEmpty())
            <p class="mt-2 text-xs text-gray-400 italic">Belum ada kelas yang ditautkan pada pola ini.</p>
        @else
            <div class="mt-2.5 flex flex-wrap gap-2">
                @foreach ($kelasAktif as $kelasTerikat)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white border-brand-200 text-brand-700 ring-1 ring-brand-500/10 border px-3 py-1 text-xs font-semibold shadow-2xs">
                        <span>{{ $kelasTerikat->nama }}</span>
                    </span>
                @endforeach
                @if ($kelasArsip->isNotEmpty())
                    <button type="button" @click="tampilkanArsip = !tampilkanArsip" class="inline-flex items-center gap-1 rounded-full border border-dashed border-gray-300 px-3 py-1 text-xs font-semibold text-gray-500 hover:bg-gray-100 transition">
                        <span x-text="tampilkanArsip ? 'Sembunyikan arsip' : '+{{ $kelasArsip->count() }} arsip'"></span>
                    </button>
                @endif
            </div>
            @if ($kelasArsip->isNotEmpty())
                <div x-show="tampilkanArsip" x-cloak class="mt-2 flex flex-wrap gap-2">
                    @foreach ($kelasArsip as $kelasTerikat)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-200/60 border-gray-300 text-gray-600 border px-3 py-1 text-xs font-semibold shadow-2xs">
                            <span>{{ $kelasTerikat->nama }}</span>
                            <span class="text-[10px] opacity-70">(&bull; {{ $kelasTerikat->tahunAjaran->nama ?? 'N/A' }})</span>
                        </span>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
@endcan
```

- [ ] **Step 5: Jalankan test, pastikan PASS**

Run: `php artisan test --filter="tautan kelas menampilkan ringkasan" --compact`
Expected: PASS.

- [ ] **Step 6: Jalankan seluruh file test regresi**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php tests/Feature/Admin/KelasPolaJamTest.php --compact`
Expected: semua PASS (termasuk test lama).

- [ ] **Step 7: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/portals/lembaga/akademik/pola-jam/index.blade.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "feat(pola-jam): ringkas tautan kelas jadi hitungan aktif/arsip + expand arsip"
```

---

## Task 3: Form Input Slot — Shortcut Hari, Preset Label, Live Duration

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/pola-jam/index.blade.php` (state SETELAH Task 2)
- Test: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:**
- Consumes: tidak ada dari task lain.
- Produces: tidak ada interface baru untuk task lain (blok form ini independen dari blok Daftar Harian/Matriks di Task 4/5).

- [ ] **Step 1: Baca ulang `index.blade.php` TERKINI**

Cari blok yang diawali komentar `{{-- 3. Form Tambah Slot Jam Pelajaran (Fast-Input Inline) --}}`.

- [ ] **Step 2: Tulis test yang gagal — shortcut hari & preset label & duration preview muncul**

Tambahkan di akhir `tests/Feature/Admin/PolaJamCrudTest.php`:
```php
it('form input slot menampilkan tombol shortcut hari dan preset label datalist', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('Senin–Kamis');
    $response->assertSee('Semua Hari');
    $response->assertSee('preset-label-'.$pola->id, false);
    $response->assertSee('Istirahat');
    $response->assertDontSee('sm:col-span-1');
});
```

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="form input slot menampilkan tombol shortcut" --compact`
Expected: FAIL.

- [ ] **Step 4: Ganti `<form>` di blok "Input Slot Jam Pelajaran Baru"**

Cari `<form method="POST" action="{{ route('admin.jam-pelajaran.store') }}" ...>` sampai `</form>` penutupnya di dalam blok itu, ganti SELURUH isinya menjadi:
```blade
<form method="POST" action="{{ route('admin.jam-pelajaran.store') }}" class="space-y-4" x-data="{
    hariTerpilih: [],
    jamMulai: '',
    jamSelesai: '',
    hariAktifValues: @js(collect($hariAktifPola)->pluck('value')->all()),
    get durasiMenit() {
        if (! this.jamMulai || ! this.jamSelesai) return null;
        const [h1, m1] = this.jamMulai.split(':').map(Number);
        const [h2, m2] = this.jamSelesai.split(':').map(Number);
        const menit = (h2 * 60 + m2) - (h1 * 60 + m1);
        return menit > 0 ? menit : null;
    },
    pilihPreset(preset) {
        if (preset === 'semua') {
            this.hariTerpilih = [...this.hariAktifValues];
        } else if (preset === 'senin_kamis') {
            this.hariTerpilih = this.hariAktifValues.filter(h => ['senin', 'selasa', 'rabu', 'kamis'].includes(h));
        }
    }
}">
    @csrf
    <input type="hidden" name="pola_jam_id" value="{{ $pola->id }}">

    <div>
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <label class="block text-xs font-semibold text-gray-700">Pilih Hari <span class="text-gray-400 font-normal">(bisa lebih dari satu)</span></label>
            <div class="flex items-center gap-1.5">
                <button type="button" @click="pilihPreset('senin_kamis')" class="rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] font-semibold text-gray-600 hover:bg-gray-50 transition">Senin–Kamis</button>
                <button type="button" @click="pilihPreset('semua')" class="rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] font-semibold text-gray-600 hover:bg-gray-50 transition">Semua Hari</button>
                <button type="button" @click="hariTerpilih = []" class="rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] font-semibold text-gray-400 hover:bg-gray-50 transition">Reset</button>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach ($hariAktifPola as $hari)
                <label class="flex cursor-pointer select-none items-center justify-center rounded-lg border px-3.5 py-1.5 text-xs font-semibold transition duration-150 active:scale-[0.97]"
                       :class="hariTerpilih.includes('{{ $hari->value }}') ? 'border-brand-500 bg-brand-50 text-brand-700 ring-1 ring-brand-500/20 shadow-2xs' : 'border-gray-200 bg-gray-50/50 text-gray-600 hover:border-brand-300 hover:bg-white'">
                    <input type="checkbox" name="hari[]" value="{{ $hari->value }}" x-model="hariTerpilih" class="sr-only">
                    {{ $hari->label() }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
        <div class="sm:col-span-2">
            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Urutan Ke- <span class="text-error-500">*</span></label>
            <input type="number" name="urutan" placeholder="Ke-" min="1" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Mulai <span class="text-error-500">*</span></label>
            <input x-model="jamMulai" type="time" name="jam_mulai" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 font-mono text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Selesai <span class="text-error-500">*</span></label>
            <input x-model="jamSelesai" type="time" name="jam_selesai" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 font-mono text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
            <p x-show="durasiMenit" class="mt-1 text-[11px] font-semibold text-brand-600">Durasi: <span x-text="durasiMenit"></span> menit</p>
        </div>
        <div class="sm:col-span-3">
            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Label Slot <span class="text-error-500">*</span></label>
            <input type="text" name="label" required placeholder="mis. Jam ke-1 / Istirahat" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-1.5 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500" list="preset-label-{{ $pola->id }}">
            <datalist id="preset-label-{{ $pola->id }}">
                <option value="Istirahat">
                <option value="Upacara Bendera">
                <option value="Sholat Dzuhur">
                <option value="Literasi">
                <option value="Sholat Dhuha">
            </datalist>
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Sesi</label>
            <select name="is_pelajaran" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                <option value="1">Jam Belajar</option>
                <option value="0">Non-pelajaran</option>
            </select>
        </div>
        <div class="sm:col-span-12 flex justify-end">
            <x-primary-button type="submit" class="px-6 py-2 text-xs">
                Simpan Slot
            </x-primary-button>
        </div>
    </div>
</form>
```

- [ ] **Step 5: Jalankan test, pastikan PASS**

Run: `php artisan test --filter="form input slot menampilkan tombol shortcut" --compact`
Expected: PASS.

- [ ] **Step 6: Verifikasi manual — submit form store() masih berfungsi (regresi fungsional, bukan cuma tampilan)**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php --compact` (test lama yang meng-`post(route('admin.jam-pelajaran.store'), ...)` — cek nama file test store slot ada di file mana; kalau ternyata di file test terpisah semacam `tests/Feature/Admin/JamPelajaranCrudTest.php`, jalankan file itu juga: `find tests -iname "*JamPelajaran*"` dulu untuk memastikan).
Expected: semua PASS — perubahan Task ini HANYA visual/Alpine, atribut `name` tiap input (`hari[]`, `urutan`, `jam_mulai`, `jam_selesai`, `label`, `is_pelajaran`) TIDAK berubah, jadi `store()` tetap menerima payload yang sama persis.

- [ ] **Step 7: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/portals/lembaga/akademik/pola-jam/index.blade.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "feat(pola-jam): tambah shortcut hari, preset label datalist, live duration preview di form slot"
```

---

## Task 4: Daftar Harian — Tab Navigasi, Format Waktu, Tombol Aksi Berkontainer

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/pola-jam/index.blade.php` (state SETELAH Task 3)
- Test: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:**
- Consumes: tidak ada dari task lain.
- Produces: tidak ada interface baru.

- [ ] **Step 1: Baca ulang `index.blade.php` TERKINI**

Cari blok yang diawali `{{-- 4. Daftar Jam Pelajaran (List & Weekly Matrix) --}}`, khususnya `<div x-data="{ viewMode: 'list' }" ...>` dan `{{-- Mode 1: Daftar Harian --}}`.

- [ ] **Step 2: Tulis test yang gagal — tab navigasi hari muncul, format waktu tanpa detik**

Tambahkan di akhir `tests/Feature/Admin/PolaJamCrudTest.php`:
```php
it('daftar harian menampilkan tab navigasi per hari dan format waktu tanpa detik', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'senin', 'urutan' => 1, 'jam_mulai' => '07:00', 'jam_selesai' => '07:35']);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('hariAktif', false);
    $response->assertSee('07:00');
    $response->assertDontSee('07:00:00');
});
```

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="daftar harian menampilkan tab navigasi" --compact`
Expected: FAIL.

- [ ] **Step 4: Ubah wrapper `x-data` dari `{ viewMode: 'list' }` jadi tambah `hariAktif`**

Cari baris persis:
```blade
<div x-data="{ viewMode: 'list' }" class="divide-y divide-gray-100 bg-white">
```
Ganti menjadi:
```blade
<div x-data="{ viewMode: 'list', hariAktif: '{{ $hariAktifPola->first()?->value }}' }" class="divide-y divide-gray-100 bg-white">
```

- [ ] **Step 5: Ganti blok "Mode 1: Daftar Harian"**

Cari blok yang diawali `{{-- Mode 1: Daftar Harian --}}` sampai `</div>` penutup blok itu (tepat SEBELUM `{{-- Mode 2: Matriks Mingguan --}}`), ganti SELURUH isinya menjadi:
```blade
{{-- Mode 1: Daftar Harian --}}
<div x-show="viewMode === 'list'">
    <div class="flex gap-1 overflow-x-auto border-b border-gray-100 bg-gray-50/30 px-6 py-2">
        @foreach ($hariAktifPola as $hariTab)
            @php $adaSlotHariIni = $pola->jamPelajaran->where('hari', $hariTab)->isNotEmpty(); @endphp
            <button type="button" @click="hariAktif = '{{ $hariTab->value }}'"
                :class="hariAktif === '{{ $hariTab->value }}' ? 'bg-white text-gray-900 shadow-2xs font-bold border-gray-200' : 'text-gray-500 hover:text-gray-800 font-medium border-transparent'"
                class="shrink-0 rounded-lg border px-3 py-1.5 text-xs transition {{ ! $adaSlotHariIni ? 'opacity-40' : '' }}">
                {{ $hariTab->label() }}
                @if ($adaSlotHariIni)
                    <span class="ml-1 text-[10px] text-gray-400">({{ $pola->jamPelajaran->where('hari', $hariTab)->count() }})</span>
                @endif
            </button>
        @endforeach
    </div>
    <div class="divide-y divide-gray-100">
        @foreach (\App\Enums\Hari::cases() as $hari)
            @php $slotHariIni = $pola->jamPelajaran->where('hari', $hari)->sortBy('urutan'); @endphp
            <div x-show="hariAktif === '{{ $hari->value }}'">
                @if ($slotHariIni->isEmpty())
                    <p class="px-6 py-8 text-center text-xs text-gray-400 italic">Belum ada slot untuk hari {{ $hari->label() }}.</p>
                @else
                    <ul class="divide-y divide-gray-50">
                        @foreach ($slotHariIni as $slot)
                            <li class="flex flex-col md:flex-row md:items-center justify-between gap-4 px-6 py-3 transition hover:bg-gray-50/60">
                                <div class="flex flex-wrap items-center gap-4">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-gray-100 font-mono text-xs font-bold text-gray-700">{{ $slot->urutan }}</span>
                                    <div class="flex items-center gap-1.5 font-mono text-xs">
                                        <span class="rounded bg-brand-50 px-2 py-1 font-bold text-brand-700 ring-1 ring-inset ring-brand-500/20">{{ \Carbon\Carbon::parse($slot->jam_mulai)->format('H:i') }}</span>
                                        <span class="text-gray-400">&rarr;</span>
                                        <span class="rounded bg-gray-100 px-2 py-1 font-semibold text-gray-700 ring-1 ring-inset ring-gray-300/50">{{ \Carbon\Carbon::parse($slot->jam_selesai)->format('H:i') }}</span>
                                    </div>
                                    <span class="text-[10px] font-semibold text-gray-400">({{ \Carbon\Carbon::parse($slot->jam_mulai)->diffInMinutes(\Carbon\Carbon::parse($slot->jam_selesai)) }} mnt)</span>
                                    <span class="text-gray-300 hidden md:inline">&bull;</span>
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-sm font-bold text-gray-900">{{ $slot->label }}</span>
                                        @if ($slot->is_pelajaran)
                                            <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-[11px] font-semibold text-success-700 ring-1 ring-inset ring-success-600/20">Belajar</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-semibold text-gray-600 ring-1 ring-inset ring-gray-400/20">Non-pelajaran</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    @can('jam-pelajaran.edit')
                                        <button type="button"
                                                @click="openEditSlot({{ $slot->toJson() }}, '{{ $slot->hari->value }}', '{{ route('admin.jam-pelajaran.update', $slot) }}')"
                                                class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition">
                                            Edit
                                        </button>
                                    @endcan
                                    @can('jam-pelajaran.delete')
                                        <form method="POST" action="{{ route('admin.jam-pelajaran.destroy', $slot) }}" x-data @submit.prevent="confirmDialog('Hapus Jam Pelajaran?', @js('Apakah Anda yakin ingin menghapus slot \"' . $slot->label . '\"?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-error-200 bg-error-50/30 px-2.5 py-1 text-xs font-semibold text-error-600 hover:bg-error-50 hover:text-error-700 transition">Hapus</button>
                                        </form>
                                    @endcan
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>
</div>
```

- [ ] **Step 6: Jalankan test, pastikan PASS**

Run: `php artisan test --filter="daftar harian menampilkan tab navigasi" --compact`
Expected: PASS.

- [ ] **Step 7: Jalankan regresi file penuh**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php tests/Feature/Admin/KelasPolaJamTest.php --compact`
Expected: semua PASS.

- [ ] **Step 8: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/portals/lembaga/akademik/pola-jam/index.blade.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "feat(pola-jam): ganti daftar harian jadi tab navigasi per hari, format waktu tanpa detik"
```

---

## Task 5: Matriks Mingguan — Label Kolom Kiri (Perbaikan Kecil)

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/pola-jam/index.blade.php` (state SETELAH Task 4)
- Test: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:**
- Consumes: tidak ada dari task lain.
- Produces: tidak ada.

**PENTING — batasi diri HANYA pada perubahan berikut.** Per-sel matriks (di bawah kolom kiri yang diubah task ini) SUDAH akurat, JANGAN disentuh, JANGAN redesain warna/struktur tabel matriks lainnya.

- [ ] **Step 1: Baca ulang `index.blade.php` TERKINI**

Cari blok `{{-- Mode 2: Matriks Mingguan --}}`, tepatnya `<td class="py-3 px-3 border-r border-gray-100 text-center bg-gray-50/40 font-mono shrink-0">`.

- [ ] **Step 2: Tulis test yang gagal — label kolom kiri tidak lagi klaim waktu spesifik**

Tambahkan di akhir `tests/Feature/Admin/PolaJamCrudTest.php`:
```php
it('label kolom kiri matriks mingguan tidak mengklaim waktu spesifik satu hari untuk semua kolom', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'senin', 'urutan' => 1, 'jam_mulai' => '07:00', 'jam_selesai' => '07:35']);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'jumat', 'urutan' => 1, 'jam_mulai' => '07:00', 'jam_selesai' => '07:30']);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('lihat per hari');
});
```

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="label kolom kiri matriks mingguan" --compact`
Expected: FAIL.

- [ ] **Step 4: Ganti isi `<td>` kolom kiri**

Cari blok:
```blade
                                <td class="py-3 px-3 border-r border-gray-100 text-center bg-gray-50/40 font-mono shrink-0">
                                    <div class="font-bold text-gray-900 text-sm">Ke-{{ $urutan }}</div>
                                    @if ($sampleSlot)
                                        <div class="text-[11px] text-gray-500 font-semibold mt-0.5">{{ \Carbon\Carbon::parse($sampleSlot->jam_mulai)->format('H:i') }} - {{ \Carbon\Carbon::parse($sampleSlot->jam_selesai)->format('H:i') }}</div>
                                    @endif
                                </td>
```
Ganti menjadi:
```blade
                                <td class="py-3 px-3 border-r border-gray-100 text-center bg-gray-50/40 font-mono shrink-0">
                                    <div class="font-bold text-gray-900 text-sm">Ke-{{ $urutan }}</div>
                                    <div class="text-[11px] text-gray-400 font-medium mt-0.5">lihat per hari &rarr;</div>
                                </td>
```
Cari juga baris `@php $sampleSlot = $pola->jamPelajaran->where('urutan', $urutan)->first(); @endphp` TEPAT SEBELUM `<td>` di atas — variabel `$sampleSlot` sudah tidak dipakai lagi di blok ini setelah perubahan, hapus baris `@php ... @endphp` itu SEKALIGUS (jangan biarkan variabel unused menggantung).

- [ ] **Step 5: Jalankan test, pastikan PASS**

Run: `php artisan test --filter="label kolom kiri matriks mingguan" --compact`
Expected: PASS.

- [ ] **Step 6: Jalankan regresi file penuh**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php tests/Feature/Admin/KelasPolaJamTest.php --compact`
Expected: semua PASS — pastikan Mode Matriks masih render tanpa error PHP (variabel `$sampleSlot` yang dihapus TIDAK dipakai di tempat lain dalam file).

- [ ] **Step 7: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/portals/lembaga/akademik/pola-jam/index.blade.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "fix(pola-jam): perbaiki label kolom kiri matriks mingguan agar tidak mengklaim waktu spesifik 1 hari"
```

---

## Task 6: Modal Assign Kelas — Pencarian & Pilih Semua per Grup

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/pola-jam/index.blade.php` (state SETELAH Task 5 — HANYA bagian root `x-data` dan `openAssignModal()`, BUKAN blok manapun yang diubah Task 2-5)
- Modify: `resources/views/portals/lembaga/akademik/pola-jam/_modal-assign-kelas.blade.php`
- Test: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:**
- Consumes: state `formAssign` dan method `openAssignModal()` yang SUDAH ada sejak awal file (baris 10-11 dan 39-48 versi original, TIDAK diubah task 1-5 manapun).
- Produces: state Alpine baru `pencarianKelas` (root `x-data` `index.blade.php`) — HANYA dipakai `_modal-assign-kelas.blade.php` di task ini, tidak dikonsumsi task lain.

- [ ] **Step 1: Baca ulang `index.blade.php` TERKINI**

Cari root `x-data="{ ... }"` di baris paling atas file (dalam `<div x-data="{ showModalPola: false, ... }" class="mx-auto max-w-6xl space-y-6">`), khususnya baris `showModalAssign: false,` dan method `openAssignModal(pola, kelasIds, url) { ... }`.

- [ ] **Step 2: Tulis test yang gagal — modal assign kelas punya input pencarian dan tombol pilih-semua**

Tambahkan di akhir `tests/Feature/Admin/PolaJamCrudTest.php`:
```php
it('modal assign kelas menampilkan input pencarian dan tombol pilih semua per grup', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('Cari nama kelas...');
    $response->assertSee('Pilih Semua di Grup Ini');
    $response->assertSee('pencarianKelas', false);
});
```

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="modal assign kelas menampilkan input pencarian" --compact`
Expected: FAIL.

- [ ] **Step 4: Tambah state `pencarianKelas` di root `x-data` `index.blade.php`**

Cari baris persis:
```blade
        showModalAssign: false,
        formAssign: { polaId: null, polaNama: '', lembagaId: null, selectedKelasIds: [], actionUrl: '' },
```
Ganti menjadi:
```blade
        showModalAssign: false,
        formAssign: { polaId: null, polaNama: '', lembagaId: null, selectedKelasIds: [], actionUrl: '' },
        pencarianKelas: '',
```

- [ ] **Step 5: Reset `pencarianKelas` di `openAssignModal()`**

Cari method persis:
```blade
        openAssignModal(pola, kelasIds, url) {
            this.formAssign = {
                polaId: pola.id,
                polaNama: pola.nama,
                lembagaId: pola.lembaga_id || null,
                selectedKelasIds: Array.from(kelasIds || []).map(Number),
                actionUrl: url
            };
            this.showModalAssign = true;
        }
```
Ganti menjadi:
```blade
        openAssignModal(pola, kelasIds, url) {
            this.formAssign = {
                polaId: pola.id,
                polaNama: pola.nama,
                lembagaId: pola.lembaga_id || null,
                selectedKelasIds: Array.from(kelasIds || []).map(Number),
                actionUrl: url
            };
            this.pencarianKelas = '';
            this.showModalAssign = true;
        }
```

- [ ] **Step 6: Tambah input pencarian di `_modal-assign-kelas.blade.php`**

Cari blok header modal (`<div class="flex items-center justify-between pb-3.5 border-b border-gray-200 shrink-0">` sampai `</div>` penutupnya, TEPAT SEBELUM `<form :action="formAssign.actionUrl" ...>`), sisipkan blok berikut SETELAH `</div>` penutup header itu dan SEBELUM `<form ...>`:
```blade
<div class="mt-3 relative">
    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
    <input type="text" x-model="pencarianKelas" placeholder="Cari nama kelas..." class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500">
</div>
```

- [ ] **Step 7: Tambah filter pencarian + tombol pilih-semua per grup**

Cari blok grup kelas:
```blade
                @foreach ($groupedKelas as $groupTitle => $classes)
                    <div x-show="formAssign.lembagaId === null || {{ $classes->pluck('lembaga_id')->unique()->values()->toJson() }}.includes(formAssign.lembagaId)" 
                         class="rounded-xl border border-gray-200 overflow-hidden">
                        <div class="bg-gray-50/80 px-4 py-2.5 border-b border-gray-200 flex items-center justify-between">
                            <span class="font-display text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-1.5">
                                @if (str_contains($groupTitle, '(Aktif)'))
                                    <span class="inline-block h-2 w-2 rounded-full bg-success-500"></span>
                                    <span class="text-success-800">{{ $groupTitle }}</span>
                                @else
                                    <span class="inline-block h-2 w-2 rounded-full bg-gray-300"></span>
                                    <span>{{ $groupTitle }}</span>
                                @endif
                            </span>
                            <span class="text-[11px] font-medium text-gray-400">
                                <span x-text="($el.closest('.rounded-xl').querySelectorAll('input[type=checkbox]:not([disabled])')).length"></span> opsi kompatibel
                            </span>
                        </div>
```
Ganti menjadi:
```blade
                @foreach ($groupedKelas as $groupTitle => $classes)
                    <div
                        x-show="(formAssign.lembagaId === null || {{ $classes->pluck('lembaga_id')->unique()->values()->toJson() }}.includes(formAssign.lembagaId)) && (pencarianKelas === '' || {{ $classes->pluck('nama')->values()->toJson() }}.some(n => n.toLowerCase().includes(pencarianKelas.toLowerCase())))"
                        class="rounded-xl border border-gray-200 overflow-hidden"
                    >
                        <div class="bg-gray-50/80 px-4 py-2.5 border-b border-gray-200 flex items-center justify-between gap-2">
                            <span class="font-display text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-1.5">
                                @if (str_contains($groupTitle, '(Aktif)'))
                                    <span class="inline-block h-2 w-2 rounded-full bg-success-500"></span>
                                    <span class="text-success-800">{{ $groupTitle }}</span>
                                @else
                                    <span class="inline-block h-2 w-2 rounded-full bg-gray-300"></span>
                                    <span>{{ $groupTitle }}</span>
                                @endif
                            </span>
                            <button type="button"
                                @click="
                                    const idGrup = {{ $classes->pluck('id')->values()->toJson() }};
                                    const semuaTerpilih = idGrup.every(id => formAssign.selectedKelasIds.includes(id));
                                    formAssign.selectedKelasIds = semuaTerpilih
                                        ? formAssign.selectedKelasIds.filter(id => !idGrup.includes(id))
                                        : [...new Set([...formAssign.selectedKelasIds, ...idGrup])];
                                "
                                class="shrink-0 text-[11px] font-semibold text-brand-600 hover:text-brand-800 transition"
                            >
                                Pilih Semua di Grup Ini
                            </button>
                        </div>
```
(Bagian `<div class="p-4 bg-white grid grid-cols-2 sm:grid-cols-3 gap-3"> ... @endforeach ... </div>` dan `@endforeach` penutup grup TIDAK berubah — biarkan seperti kode saat ini, HANYA header grup di atas yang diganti.)

- [ ] **Step 8: Jalankan test, pastikan PASS**

Run: `php artisan test --filter="modal assign kelas menampilkan input pencarian" --compact`
Expected: PASS.

- [ ] **Step 9: Jalankan regresi file penuh**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php tests/Feature/Admin/KelasPolaJamTest.php --compact`
Expected: semua PASS.

- [ ] **Step 10: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/portals/lembaga/akademik/pola-jam/index.blade.php resources/views/portals/lembaga/akademik/pola-jam/_modal-assign-kelas.blade.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "feat(pola-jam): tambah pencarian kelas dan pilih-semua-per-grup di modal assign kelas"
```

---

## Task 7: Modal Pola Jam & Edit Slot — Konteks Lembaga, `<x-select>`, Durasi

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/pola-jam/_modal-pola.blade.php`
- Modify: `resources/views/portals/lembaga/akademik/pola-jam/_modal-edit-slot.blade.php`
- Test: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:**
- Consumes: variabel `$isYayasan`/`$activeLembaga` yang SUDAH tersedia lewat pewarisan scope dari `index.blade.php` (`@include('portals.lembaga.akademik.pola-jam._modal-pola')` mewariskan SEMUA variabel yang ada di scope `index.blade.php` ke partial-nya — TIDAK PERLU passing eksplisit).
- Produces: tidak ada interface baru.

**Task ini AMAN dikerjakan PARALEL dengan Task 1-6/8** kalau memakai subagent-driven-development — file objek utamanya (`_modal-pola.blade.php`, `_modal-edit-slot.blade.php`) sama sekali berbeda dari `index.blade.php` yang jadi fokus task lain.

- [ ] **Step 1: Tulis test yang gagal — badge lembaga di modal pola, `<x-select>` di modal edit slot**

Tambahkan di akhir `tests/Feature/Admin/PolaJamCrudTest.php`:
```php
it('modal tambah/edit pola jam menampilkan badge lembaga aktif untuk aktor yayasan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Pintera Cabang Utama']);
    Permission::firstOrCreate(['name' => 'pola-jam.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_admin_pola_jam', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo('pola-jam.view');
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null]);
    $manager->assignRole($role);

    $response = $this->actingAs($manager)
        ->withSession(['active_lembaga_id' => $lembaga->id])
        ->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('Untuk lembaga');
    $response->assertSee('SD Pintera Cabang Utama');
});

it('modal edit slot memakai x-select untuk field Hari dan Jenis Sesi', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    // <x-select> merender base classes tertentu (lihat resources/views/components/select.blade.php)
    // yang tidak dipakai native <select> lama -- disabled:bg-gray-50 disabled:text-gray-500 disabled:cursor-not-allowed
    // adalah base class KHAS komponen ini.
    $response->assertSee('disabled:bg-gray-50 disabled:text-gray-500 disabled:cursor-not-allowed', false);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="modal tambah/edit pola jam menampilkan badge lembaga|modal edit slot memakai x-select" --compact`
Expected: FAIL untuk kedua test.

- [ ] **Step 3: Tambah badge lembaga di `_modal-pola.blade.php`**

Cari blok:
```blade
        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200">
            <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                <x-icon name="schedule" class="h-5 w-5 text-brand-500" />
                <span x-text="modalPolaMode === 'create' ? 'Tambah Pola Jam Baru' : 'Edit Nama Pola Jam'"></span>
            </h3>
            <button @click="showModalPola = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="cancel" class="h-5 w-5" />
            </button>
        </div>
```
Ganti menjadi:
```blade
        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200">
            <div>
                <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                    <x-icon name="schedule" class="h-5 w-5 text-brand-500" />
                    <span x-text="modalPolaMode === 'create' ? 'Tambah Pola Jam Baru' : 'Edit Nama Pola Jam'"></span>
                </h3>
                @if (($isYayasan ?? false) && ($activeLembaga ?? null))
                    <p class="mt-0.5 text-xs text-gray-500">Untuk lembaga <strong class="font-semibold text-gray-700">{{ $activeLembaga->nama }}</strong>.</p>
                @endif
            </div>
            <button @click="showModalPola = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="cancel" class="h-5 w-5" />
            </button>
        </div>
```

- [ ] **Step 4: Ganti 2 `<select>` di `_modal-edit-slot.blade.php` jadi `<x-select>`**

Cari blok Hari:
```blade
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Hari <span class="text-error-500">*</span></label>
                    <select x-model="formSlot.hari" name="hari" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        @foreach (\App\Enums\Hari::cases() as $hariOpsi)
                            <option value="{{ $hariOpsi->value }}">{{ $hariOpsi->label() }}</option>
                        @endforeach
                    </select>
                </div>
```
Ganti menjadi:
```blade
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Hari <span class="text-error-500">*</span></label>
                    <x-select x-model="formSlot.hari" name="hari" required>
                        @foreach (\App\Enums\Hari::cases() as $hariOpsi)
                            <option value="{{ $hariOpsi->value }}">{{ $hariOpsi->label() }}</option>
                        @endforeach
                    </x-select>
                </div>
```
Cari blok Jenis Sesi:
```blade
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Sesi <span class="text-error-500">*</span></label>
                    <select x-model="formSlot.is_pelajaran" name="is_pelajaran" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option :value="1">Jam Belajar</option>
                        <option :value="0">Non-pelajaran</option>
                    </select>
                </div>
```
Ganti menjadi:
```blade
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Sesi <span class="text-error-500">*</span></label>
                    <x-select x-model="formSlot.is_pelajaran" name="is_pelajaran">
                        <option :value="1">Jam Belajar</option>
                        <option :value="0">Non-pelajaran</option>
                    </x-select>
                </div>
```

- [ ] **Step 5: Tambah live duration preview di `_modal-edit-slot.blade.php`**

Cari blok Jam Selesai:
```blade
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Selesai <span class="text-error-500">*</span></label>
                    <input x-model="formSlot.jam_selesai" type="time" name="jam_selesai" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 font-mono text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                </div>
```
Ganti menjadi:
```blade
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Selesai <span class="text-error-500">*</span></label>
                    <input x-model="formSlot.jam_selesai" type="time" name="jam_selesai" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 font-mono text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    <p x-show="formSlot.jam_mulai && formSlot.jam_selesai" class="mt-1 text-[11px] font-semibold text-brand-600" x-text="(() => {
                        if (!formSlot.jam_mulai || !formSlot.jam_selesai) return '';
                        const [h1, m1] = formSlot.jam_mulai.split(':').map(Number);
                        const [h2, m2] = formSlot.jam_selesai.split(':').map(Number);
                        const menit = (h2 * 60 + m2) - (h1 * 60 + m1);
                        return menit > 0 ? `Durasi: ${menit} menit` : '';
                    })()"></p>
                </div>
```

- [ ] **Step 6: Jalankan test, pastikan PASS**

Run: `php artisan test --filter="modal tambah/edit pola jam menampilkan badge lembaga|modal edit slot memakai x-select" --compact`
Expected: PASS.

- [ ] **Step 7: Jalankan regresi file penuh**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php tests/Feature/Admin/KelasPolaJamTest.php --compact`
Expected: semua PASS.

- [ ] **Step 8: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/portals/lembaga/akademik/pola-jam/_modal-pola.blade.php resources/views/portals/lembaga/akademik/pola-jam/_modal-edit-slot.blade.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "feat(pola-jam): badge konteks lembaga di modal pola, x-select + durasi di modal edit slot"
```

---

## Task 8: KPI Cards Ringkas (Total Pola Jam, Kelas Tertaut)

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/pola-jam/index.blade.php` (state SETELAH Task 1-6 selesai SEMUA — TIDAK peduli apakah Task 7 sudah selesai atau belum karena filenya beda)
- Test: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:**
- Consumes: `$polaJamList` (variabel yang SUDAH dikirim controller `index()` — TIDAK berubah).
- Produces: tidak ada.

- [ ] **Step 1: Baca ulang `index.blade.php` TERKINI**

Cari blok Header & Breadcrumb (diawali komentar `{{-- Header & Breadcrumb --}}`) sampai `</div>` penutupnya, TEPAT SEBELUM komentar `{{-- Daftar Card Pola Jam --}}`.

- [ ] **Step 2: Tulis test yang gagal — 2 KPI card muncul**

Tambahkan di akhir `tests/Feature/Admin/PolaJamCrudTest.php`:
```php
it('menampilkan KPI Total Pola Jam dan Kelas Tertaut di atas halaman', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('Total Pola Jam');
    $response->assertSee('Kelas Tertaut');
});
```

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menampilkan KPI Total Pola Jam" --compact`
Expected: FAIL.

- [ ] **Step 4: Sisipkan KPI cards**

Cari titik TEPAT SETELAH penutup `</div>` blok Header & Breadcrumb dan SEBELUM komentar `{{-- Daftar Card Pola Jam --}}`, sisipkan:
```blade
        @php
            $totalPola = $polaJamList->count();
            $totalKelasTertaut = $polaJamList->flatMap->kelas->pluck('id')->unique()->count();
        @endphp
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Pola Jam</p>
                <p class="font-display text-lg font-bold text-gray-900">{{ $totalPola }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Kelas Tertaut</p>
                <p class="font-display text-lg font-bold text-gray-900">{{ $totalKelasTertaut }}</p>
            </div>
        </div>

```

- [ ] **Step 5: Jalankan test, pastikan PASS**

Run: `php artisan test --filter="menampilkan KPI Total Pola Jam" --compact`
Expected: PASS.

- [ ] **Step 6: Jalankan regresi file penuh**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php tests/Feature/Admin/KelasPolaJamTest.php --compact`
Expected: semua PASS.

- [ ] **Step 7: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/portals/lembaga/akademik/pola-jam/index.blade.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "feat(pola-jam): tambah KPI Total Pola Jam dan Kelas Tertaut"
```

---

## Task 9: Regression Sweep Penutup

**Files:** tidak ada file baru — task ini murni verifikasi.

**WAJIB dikerjakan PALING AKHIR, setelah Task 1-8 (termasuk Task 7) semuanya selesai.**

- [ ] **Step 1: Jalankan semua test scoped modul ini**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php tests/Feature/Admin/KelasPolaJamTest.php tests/Unit/Domains/Akademik/Actions/PolaJam/DeletePolaJamActionTest.php tests/Unit/Domains/Akademik/Actions/PolaJam/DuplicatePolaJamActionTest.php tests/Unit/Models/PolaJamTest.php tests/Unit/PolaJamSeederTest.php --compact`
Expected: semua PASS.

- [ ] **Step 2: Format seluruh perubahan PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` — kalau ada yang di-fix otomatis, ulangi Step 1.

- [ ] **Step 3: Build asset frontend**

Run: `npm run build`
Expected: build sukses tanpa error.

- [ ] **Step 4: Verifikasi manual — konsistensi nama icon final**

Run: `grep -oE 'x-icon name="[a-z_]+"' resources/views/portals/lembaga/akademik/pola-jam/*.blade.php | grep -oE 'name="[a-z_]+"' | sort -u`
Run: `grep -oE "@case\('[a-z_]+'\)" resources/views/components/icon.blade.php | sort -u`
Expected: SETIAP nama dari hasil pertama ada di hasil kedua (tidak ada icon rusak tersisa di SELURUH file modul ini, termasuk 3 modal partial, bukan cuma `index.blade.php` yang dicek Task 1).

- [ ] **Step 5: Verifikasi route tidak berubah**

Run: `php artisan route:list --name=pola-jam`
Run: `php artisan route:list --name=jam-pelajaran`
Expected: semua route (`index`, `store`, `update`, `destroy`, `assign-kelas`, `duplicate` untuk pola-jam; `store`, `edit`, `update`, `destroy` untuk jam-pelajaran) masih terdaftar persis seperti sebelumnya — plan ini TIDAK PERNAH mengubah controller/routing.

- [ ] **Step 6: Tanyakan ke user apakah mau full suite**

Jangan jalankan `php artisan test` (full suite) tanpa izin eksplisit — tanyakan ke user dulu, HANYA jalankan sendirian (tidak paralel dengan proses test lain) kalau disetujui.

- [ ] **Step 7: Commit penutup (kalau Step 2 menghasilkan perubahan format)**

```bash
git add -A
git commit -m "chore(pola-jam): regression sweep penutup"
```
(kalau tidak ada perubahan file di step ini, skip commit — tidak boleh commit kosong.)

---

## Self-Review (5 Putaran)

**Putaran 1 — Cakupan spec vs task**: Semua §2.1-§2.8 dari spec punya task eksplisit (Task 1↔§2.1, Task 2↔§2.2, Task 3↔§2.3, Task 4↔§2.4, Task 5↔§2.5, Task 6↔§2.6, Task 7↔§2.7, Task 8↔§2.8). Global Constraints spec §4 semua masuk section "Global Constraints" plan ini kata-per-kata.

**Putaran 2 — Urutan & konflik file `index.blade.php`**: Ditulis catatan wajib eksplisit di awal plan (sebelum Task 1) bahwa Task 1,2,3,4,5,6,8 (7 dari 9!) SEMUA mengedit `index.blade.php` dan HARUS sekuensial — ini beda signifikan dari siklus kurikulum-assignment sebelumnya yang task-nya lebih independen, jadi ditulis SANGAT eksplisit supaya tidak terlewat kalau dieksekusi lewat subagent-driven-development (yang secara default akan MENCOBA paralelkan task independen kalau tidak diberi rambu jelas). Task 7 ditandai eksplisit AMAN paralel (file objek beda total).

**Putaran 3 — Akurasi kode "Current" vs state antar-task**: Setiap task yang mengedit `index.blade.php` diberi instruksi eksplisit Step 1 "Baca ulang TERKINI" sebelum kode "cari blok ini" — karena kode incumbent di task 2 dst SUDAH mencerminkan hasil task sebelumnya (misal Task 2 kode target sudah pakai `name="school"`/`name="groups"` hasil Task 1, BUKAN `class`/`add_circle` versi original file). Dicek satu-satu: Task 3 (form slot) sasaran blok TIDAK overlap dengan Task 2 (blok tautan kelas) atau Task 4 (blok daftar harian) — aman berurutan tanpa saling tumpang tindih within-file.

**Putaran 4 — Keamanan/regresi**: Semua task murni Blade+Alpine, TIDAK ADA perubahan `PolaJamController.php`/`JamPelajaranController.php`/domain layer (ditegaskan di Global Constraints DAN dicek ulang tiap task tidak menyentuh file itu). Test regresi tiap task menjalankan `PolaJamCrudTest.php` DAN `KelasPolaJamTest.php` (bukan cuma file baru) untuk menangkap kalau ada perubahan struktur HTML yang TIDAK SENGAJA mengubah `name` attribute form (yang akan merusak `store()`/`update()` walau controller sendiri tidak disentuh) — Task 3 Step 6 secara eksplisit menegaskan ini (atribut `name` tiap input form slot TIDAK berubah).

**Putaran 5 — Placeholder scan & konsistensi nama variabel Alpine**: Scan ulang semua task untuk red-flag ("TODO", "seperti biasa", dsb) — tidak ditemukan. Konsistensi nama state baru dicek: `hariAktif` (Task 4, lokal ke `x-data` blok Daftar Jam Pelajaran, TIDAK bentrok dengan `viewMode` yang sudah ada di `x-data` yang sama), `pencarianKelas` (Task 6, ditambah ke ROOT `x-data` `index.blade.php`, dikonsumsi `_modal-assign-kelas.blade.php` — dicek TIDAK bentrok dengan 6 state root yang sudah ada: `showModalPola`, `modalPolaMode`, `formPola`, `showModalEditSlot`, `formSlot`, `showModalAssign`, `formAssign`), `tampilkanArsip` (Task 2, lokal ke blok Tautan Kelas). Semua nama unik, tidak ada tabrakan.
