# Jenis Tagihan Form UX Clarity Pass — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix 6 UX-clarity gaps in `resources/views/admin/jenis-tagihan/form.blade.php` — all pure presentation (labels/microcopy/one display-only query column), zero backend logic changes.

**Architecture:** 6 independent, additive changes across 3 files (`JenisTagihanController.php`, `form.blade.php`, `jenis-tagihan-form.js`). No task depends on another's code, but several touch the same blade file — safe to run sequentially in one session, unsafe to parallelize file edits.

**Tech Stack:** Laravel 12, Blade, Alpine.js, TomSelect (already integrated), Pest.

## Global Constraints

- **No backend logic changes.** Validation rules, `syncBillingConfig()`, `JenisTagihanSasaranMatcher`, `TagihanBillingGenerator` — none of these are touched. Every change in this plan is either a Blade text/attribute addition or one additional `select()`/`with()` column for display purposes only.
- **Every task's verification step MUST run `php artisan test` for the relevant file(s) — not just `npm run build`.** This is an explicit, repeated requirement from the user, given the prior UI rework (`.agents/logs/2026-08-11-jenis-tagihan-ui-ux.md`) marked itself done after only running `npm run build`, leaving 3 tests silently stale until an unrelated audit caught it. The final task in this plan runs the full project suite as proof, not assumption.
- Only ever run `php artisan test` in the foreground, one command at a time — never in the background, never concurrent with another test run (this project had a real incident with shared-test-DB corruption from concurrent runs).
- Reuse the exact helper-text style already established in this same file: `<p class="mt-1.5 text-[10px] text-gray-400 leading-tight">...</p>` (seen under the kriteria value TomSelect, `form.blade.php:194`) — don't introduce a new size/color for helper text.
- `:value="fieldOpt"` (the raw key sent to the server in kriteria field `<select>`s) must never change — only `x-text` (the displayed label) changes in Task 3.

---

### Task 1: Kelas TomSelect — show tahun ajaran to disambiguate same-named classes

**Files:**
- Modify: `app/Http/Controllers/Admin/JenisTagihanController.php`
- Test: `tests/Feature/Admin/JenisTagihanFormPageTest.php`

**Interfaces:**
- Consumes: `Kelas::tahunAjaran()` (existing `BelongsTo` relation), `TahunAjaran::$nama` (existing column).
- Produces: `referenceOptions.kelas` option labels now include tahun ajaran — no consumer outside this form.

- [ ] **Step 1: Write the failing test** — append to `tests/Feature/Admin/JenisTagihanFormPageTest.php`:

```php
it('shows the tahun ajaran alongside the kelas name in the sasaran kriteria options to disambiguate same-named classes', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $tahunAjaranLama = \App\Models\TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => false,
    ]);
    $tahunAjaranBaru = \App\Models\TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    // Two classes with the SAME name in different tahun ajaran — the exact ambiguity this fixes.
    \App\Models\Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranLama->id, 'nama' => '7A']);
    \App\Models\Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranBaru->id, 'nama' => '7A']);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    // Assert on the view data the controller passes, not the rendered/escaped HTML — Blade's
    // @js() JSON-encodes this string for embedding (slash-escaping details are an implementation
    // Detail of that encoding, not what this fix is actually about). This directly tests the
    // controller's query/eager-load change, which is the substantive part of this fix.
    $kelasList = $response->viewData('kelasList');
    expect($kelasList)->toHaveCount(2);
    expect($kelasList->pluck('nama')->unique()->all())->toBe(['7A']);
    expect($kelasList->pluck('tahunAjaran.nama')->sort()->values()->all())->toBe(['2025/2026', '2026/2027']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/JenisTagihanFormPageTest.php --filter="disambiguate"`
Expected: FAIL — current query is `get(['id', 'nama'])`, which doesn't select `tahun_ajaran_id` at all, so `tahunAjaran` resolves to `null` on each model and `pluck('tahunAjaran.nama')` produces `[null, null]` instead of `['2025/2026', '2026/2027']` (this codebase does not enable `Model::preventLazyLoading()`, confirmed via `app/Providers/`, so this is a value mismatch, not a lazy-loading exception).

- [ ] **Step 3: Update `referenceData()` in `app/Http/Controllers/Admin/JenisTagihanController.php`**

Find:
```php
            'kelasList' => Kelas::where('lembaga_id', $lembagaId)->orderBy('nama')->get(['id', 'nama']),
```
Replace with:
```php
            'kelasList' => Kelas::where('lembaga_id', $lembagaId)->with('tahunAjaran')->orderBy('nama')->get(['id', 'nama', 'tahun_ajaran_id']),
```

- [ ] **Step 4: Update the `referenceOptions.kelas` mapping in `resources/views/admin/jenis-tagihan/form.blade.php`**

Find (inside the `x-data="jenisTagihanForm({...})"` config, `referenceOptions` block):
```php
                kelas: @js($kelasList->map(fn ($k) => ['value' => $k->id, 'label' => $k->nama])),
```
Replace with:
```php
                kelas: @js($kelasList->map(fn ($k) => ['value' => $k->id, 'label' => $k->nama.' ('.$k->tahunAjaran->nama.')'])),
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JenisTagihanFormPageTest.php`
Expected: PASS (all tests in file, including the new one)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/JenisTagihanController.php resources/views/admin/jenis-tagihan/form.blade.php tests/Feature/Admin/JenisTagihanFormPageTest.php
git commit -m "fix(ui): show tahun ajaran alongside kelas name in sasaran/tarif kriteria options"
```

---

### Task 2: Mode Otomatis fields — add explanatory helper text

**Files:**
- Modify: `resources/views/admin/jenis-tagihan/form.blade.php`
- Test: `tests/Feature/Admin/JenisTagihanFormPageTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new consumed elsewhere.

- [ ] **Step 1: Write the failing test** — append to `tests/Feature/Admin/JenisTagihanFormPageTest.php`:

```php
it('explains what each mode otomatis field controls', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    $response->assertSee('Tanggal setiap bulan saat tagihan otomatis dibuat');
    $response->assertSee('Jumlah hari setelah tanggal generate sampai batas waktu pembayaran');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/JenisTagihanFormPageTest.php --filter="explains what each"`
Expected: FAIL — no helper text exists yet under these fields.

- [ ] **Step 3: Add helper text in `resources/views/admin/jenis-tagihan/form.blade.php`**

Find the `otomatis`-mode block (inside `<template x-if="form.mode === 'otomatis'">`):
```blade
                                <div>
                                    <x-input-label value="Tanggal Mulai" />
                                    <x-text-input type="date" name="tanggal_mulai" :value="old('tanggal_mulai', optional($jenisTagihan?->tanggal_mulai)->toDateString())" class="mt-1.5" />
                                </div>
                                <div>
                                    <x-input-label value="Tanggal Selesai (opsional)" />
                                    <x-text-input type="date" name="tanggal_selesai" :value="old('tanggal_selesai', optional($jenisTagihan?->tanggal_selesai)->toDateString())" class="mt-1.5" />
                                </div>
                                <div>
                                    <x-input-label value="Tanggal Generate (hari ke-)" />
                                    <x-text-input type="number" min="1" max="31" name="tanggal_generate" :value="old('tanggal_generate', $jenisTagihan?->tanggal_generate)" class="mt-1.5" />
                                </div>
                                <div>
                                    <x-input-label value="Hari Jatuh Tempo (setelah generate)" />
                                    <x-text-input type="number" min="0" name="hari_jatuh_tempo" :value="old('hari_jatuh_tempo', $jenisTagihan?->hari_jatuh_tempo)" class="mt-1.5" />
                                </div>
```
Replace with:
```blade
                                <div>
                                    <x-input-label value="Tanggal Mulai" />
                                    <x-text-input type="date" name="tanggal_mulai" :value="old('tanggal_mulai', optional($jenisTagihan?->tanggal_mulai)->toDateString())" class="mt-1.5" />
                                    <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Tanggal jenis tagihan ini mulai aktif digenerate otomatis.</p>
                                </div>
                                <div>
                                    <x-input-label value="Tanggal Selesai (opsional)" />
                                    <x-text-input type="date" name="tanggal_selesai" :value="old('tanggal_selesai', optional($jenisTagihan?->tanggal_selesai)->toDateString())" class="mt-1.5" />
                                    <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Kosongkan jika tidak ada batas akhir.</p>
                                </div>
                                <div>
                                    <x-input-label value="Tanggal Generate (hari ke-)" />
                                    <x-text-input type="number" min="1" max="31" name="tanggal_generate" :value="old('tanggal_generate', $jenisTagihan?->tanggal_generate)" class="mt-1.5" />
                                    <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Tanggal setiap bulan saat tagihan otomatis dibuat (mis. isi 1 untuk tanggal 1 tiap bulan).</p>
                                </div>
                                <div>
                                    <x-input-label value="Hari Jatuh Tempo (setelah generate)" />
                                    <x-text-input type="number" min="0" name="hari_jatuh_tempo" :value="old('hari_jatuh_tempo', $jenisTagihan?->hari_jatuh_tempo)" class="mt-1.5" />
                                    <p class="mt-1.5 text-[10px] text-gray-400 leading-tight">Jumlah hari setelah tanggal generate sampai batas waktu pembayaran.</p>
                                </div>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JenisTagihanFormPageTest.php`
Expected: PASS (all tests in file)

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/jenis-tagihan/form.blade.php tests/Feature/Admin/JenisTagihanFormPageTest.php
git commit -m "fix(ui): explain what each mode otomatis field controls"
```

---

### Task 3: Kriteria field select — show human labels instead of raw keys

**Files:**
- Modify: `resources/js/jenis-tagihan-form.js`
- Modify: `resources/views/admin/jenis-tagihan/form.blade.php`
- Test: `tests/Feature/Admin/JenisTagihanSasaranFormTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `fieldLabels` lookup on the Alpine component — no consumer outside this form.

- [ ] **Step 1: Write the failing test** — append to `tests/Feature/Admin/JenisTagihanSasaranFormTest.php`:

```php
it('shows human-readable labels for kriteria fields instead of raw keys', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    // The fieldLabels lookup object must be embedded so Alpine can resolve human labels.
    $response->assertSee("jenis_kelamin: 'Jenis Kelamin'", false);
    $response->assertSee("status_siswa: 'Status Siswa'", false);
    $response->assertSee("tahun_ajaran: 'Tahun Ajaran'", false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/JenisTagihanSasaranFormTest.php --filter="human-readable labels"`
Expected: FAIL — `fieldLabels` doesn't exist yet.

- [ ] **Step 3: Add `fieldLabels` to `resources/js/jenis-tagihan-form.js`**

Find:
```js
    return {
        kriteriaFields: ['lembaga', 'tahun_ajaran', 'tingkat', 'kelas', 'jenis_kelamin', 'status_siswa'],
```
Replace with:
```js
    return {
        kriteriaFields: ['lembaga', 'tahun_ajaran', 'tingkat', 'kelas', 'jenis_kelamin', 'status_siswa'],
        fieldLabels: {
            lembaga: 'Lembaga', tahun_ajaran: 'Tahun Ajaran', tingkat: 'Tingkat',
            kelas: 'Kelas', jenis_kelamin: 'Jenis Kelamin', status_siswa: 'Status Siswa',
        },
```

- [ ] **Step 4: Swap the displayed label in `resources/views/admin/jenis-tagihan/form.blade.php`**

There are TWO occurrences of this exact line (one in the Sasaran section, one in the Tarif section) — replace BOTH:

Find:
```blade
<template x-for="fieldOpt in kriteriaFields" :key="fieldOpt"><option :value="fieldOpt" x-text="fieldOpt" :selected="fieldOpt === kriteria.field"></option></template>
```
Replace with:
```blade
<template x-for="fieldOpt in kriteriaFields" :key="fieldOpt"><option :value="fieldOpt" x-text="fieldLabels[fieldOpt] ?? fieldOpt" :selected="fieldOpt === kriteria.field"></option></template>
```

Do not change `:value="fieldOpt"` in either occurrence — only `x-text`.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JenisTagihanSasaranFormTest.php`
Expected: PASS (all tests in file)

- [ ] **Step 6: Commit**

```bash
git add resources/js/jenis-tagihan-form.js resources/views/admin/jenis-tagihan/form.blade.php tests/Feature/Admin/JenisTagihanSasaranFormTest.php
git commit -m "fix(ui): show human-readable kriteria field labels instead of raw keys"
```

---

### Task 4: Explain AND (within a Grup) vs OR (between Grup cards)

**Files:**
- Modify: `resources/views/admin/jenis-tagihan/form.blade.php`
- Test: `tests/Feature/Admin/JenisTagihanSasaranFormTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new consumed elsewhere.

- [ ] **Step 1: Write the failing test** — append to `tests/Feature/Admin/JenisTagihanSasaranFormTest.php`:

```php
it('explains the and/or relationship between kriteria rows and grup cards for both sasaran and tarif', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    // Appears once per section (Sasaran, Tarif) = 2 occurrences each string.
    $response->assertSeeInOrder([
        'Semua kriteria di atas harus terpenuhi bersamaan (DAN).',
        'Setiap Grup adalah alternatif terpisah — siswa cukup cocok salah satu (ATAU).',
        'Semua kriteria di atas harus terpenuhi bersamaan (DAN).',
        'Setiap Grup adalah alternatif terpisah — siswa cukup cocok salah satu (ATAU).',
    ]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/JenisTagihanSasaranFormTest.php --filter="and/or relationship"`
Expected: FAIL — no such text exists yet.

- [ ] **Step 3: Add the DAN note inside each Grup card, before "+ Tambah Kriteria"**

There are TWO occurrences (Sasaran section, Tarif section) — replace BOTH:

Find (Sasaran section):
```blade
                                    <button type="button" class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700" @click="grup.kriteria.push(newKriteria())">
                                        <x-icon name="add" class="h-3.5 w-3.5" /> Tambah Kriteria
                                    </button>
                                </div>
                            </template>
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-brand-600 hover:bg-gray-50" @click="form.sasaran.push(newGrup())">
                                <x-icon name="add_circle" class="h-4 w-4" /> Tambah Grup Sasaran Baru
                            </button>
                        </div>
                    </template>
```
Replace with:
```blade
                                    <p class="text-[10px] text-gray-400 leading-tight">Semua kriteria di atas harus terpenuhi bersamaan (DAN).</p>
                                    <button type="button" class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700" @click="grup.kriteria.push(newKriteria())">
                                        <x-icon name="add" class="h-3.5 w-3.5" /> Tambah Kriteria
                                    </button>
                                </div>
                            </template>
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-brand-600 hover:bg-gray-50" @click="form.sasaran.push(newGrup())">
                                <x-icon name="add_circle" class="h-4 w-4" /> Tambah Grup Sasaran Baru
                            </button>
                            <p class="text-[10px] text-gray-400 leading-tight">Setiap Grup adalah alternatif terpisah — siswa cukup cocok salah satu (ATAU).</p>
                        </div>
                    </template>
```

Find (Tarif section):
```blade
                                <button type="button" class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700" @click="grup.kriteria.push(newKriteria())">
                                    <x-icon name="add" class="h-3.5 w-3.5" /> Tambah Kriteria
                                </button>
                            </div>
                        </template>
                        <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-brand-600 hover:bg-gray-50" @click="form.tarif.push(newGrup())">
                            <x-icon name="add_circle" class="h-4 w-4" /> Tambah Grup Tarif Baru
                        </button>
                    </div>
                </div>
            </template>
```
Replace with:
```blade
                                <p class="text-[10px] text-gray-400 leading-tight">Semua kriteria di atas harus terpenuhi bersamaan (DAN).</p>
                                <button type="button" class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700" @click="grup.kriteria.push(newKriteria())">
                                    <x-icon name="add" class="h-3.5 w-3.5" /> Tambah Kriteria
                                </button>
                            </div>
                        </template>
                        <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-brand-600 hover:bg-gray-50" @click="form.tarif.push(newGrup())">
                            <x-icon name="add_circle" class="h-4 w-4" /> Tambah Grup Tarif Baru
                        </button>
                        <p class="text-[10px] text-gray-400 leading-tight">Setiap Grup adalah alternatif terpisah — siswa cukup cocok salah satu (ATAU).</p>
                    </div>
                </div>
            </template>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JenisTagihanSasaranFormTest.php`
Expected: PASS (all tests in file)

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/jenis-tagihan/form.blade.php tests/Feature/Admin/JenisTagihanSasaranFormTest.php
git commit -m "fix(ui): explain AND-within-grup vs OR-between-grup for sasaran and tarif kriteria"
```

---

### Task 5: Tarif Berdimensi — explain the numbered badges are priority order

**Files:**
- Modify: `resources/views/admin/jenis-tagihan/form.blade.php`
- Test: `tests/Feature/Admin/JenisTagihanSasaranFormTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new consumed elsewhere.

- [ ] **Step 1: Write the failing test** — append to `tests/Feature/Admin/JenisTagihanSasaranFormTest.php`:

```php
it('explains that tarif grup cards are evaluated in priority order', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    $response->assertSee('Diproses berurutan dari atas — Grup pertama yang cocok dengan siswa akan dipakai nominalnya.');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/JenisTagihanSasaranFormTest.php --filter="priority order"`
Expected: FAIL — no such text exists yet.

- [ ] **Step 3: Add the caption under the "Tarif Berdimensi" header in `resources/views/admin/jenis-tagihan/form.blade.php`**

Find:
```blade
                    <div class="border-b border-gray-100 pb-3 flex justify-between items-center">
                        <p class="font-display text-sm font-bold text-gray-900">Tarif Berdimensi</p>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-gray-500">Opsional</span>
                    </div>
                    
                    <div class="space-y-4 pt-1">
```
Replace with:
```blade
                    <div class="border-b border-gray-100 pb-3 flex justify-between items-center">
                        <p class="font-display text-sm font-bold text-gray-900">Tarif Berdimensi</p>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-gray-500">Opsional</span>
                    </div>
                    <p class="text-[10px] text-gray-400 leading-tight">Diproses berurutan dari atas — Grup pertama yang cocok dengan siswa akan dipakai nominalnya.</p>

                    <div class="space-y-4 pt-1">
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JenisTagihanSasaranFormTest.php`
Expected: PASS (all tests in file)

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/jenis-tagihan/form.blade.php tests/Feature/Admin/JenisTagihanSasaranFormTest.php
git commit -m "fix(ui): explain tarif berdimensi grup cards are evaluated in priority order"
```

---

### Task 6: Keringanan "Nilai Potongan" — reactive placeholder showing the unit (Rp vs %)

**Files:**
- Modify: `resources/views/admin/jenis-tagihan/form.blade.php`
- Test: `tests/Feature/Admin/JenisTagihanKeringananFormTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new consumed elsewhere.

- [ ] **Step 1: Write the failing test** — append to `tests/Feature/Admin/JenisTagihanKeringananFormTest.php`:

```php
it('shows a reactive placeholder on nilai potongan indicating rupiah vs percent', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    $response->assertSee(":placeholder=\"rule.tipe_potongan === 'persen' ? 'Contoh: 20 (%)' : 'Contoh: 50000 (Rupiah)'\"", false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/JenisTagihanKeringananFormTest.php --filter="reactive placeholder"`
Expected: FAIL — the input currently has a static `placeholder="Nilai Potongan"`.

- [ ] **Step 3: Make the placeholder reactive in `resources/views/admin/jenis-tagihan/form.blade.php`**

Find:
```blade
                                <input type="number" min="0" :max="rule.tipe_potongan === 'persen' ? 100 : null" step="0.01" :name="'keringanan[' + ri + '][nilai]'" x-model="rule.nilai" placeholder="Nilai Potongan" class="rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
```
Replace with:
```blade
                                <input type="number" min="0" :max="rule.tipe_potongan === 'persen' ? 100 : null" step="0.01" :name="'keringanan[' + ri + '][nilai]'" x-model="rule.nilai" :placeholder="rule.tipe_potongan === 'persen' ? 'Contoh: 20 (%)' : 'Contoh: 50000 (Rupiah)'" class="rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500">
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JenisTagihanKeringananFormTest.php`
Expected: PASS (all tests in file)

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/jenis-tagihan/form.blade.php tests/Feature/Admin/JenisTagihanKeringananFormTest.php
git commit -m "fix(ui): show reactive rupiah/percent placeholder on keringanan nilai potongan input"
```

---

### Task 7: Full regression verification + handoff log

**Files:** none (verification-only task)

**This task exists specifically to avoid repeating the prior rework's mistake** (marking UI work done after only `npm run build`, without ever running `php artisan test` — see Global Constraints).

- [ ] **Step 1: Build the frontend assets**

```bash
npm run build
```
Expected: builds cleanly, no errors.

- [ ] **Step 2: Run every test file touched by this plan**

```bash
php artisan test tests/Feature/Admin/JenisTagihanFormPageTest.php tests/Feature/Admin/JenisTagihanSasaranFormTest.php tests/Feature/Admin/JenisTagihanKeringananFormTest.php tests/Feature/Admin/JenisTagihanFormTest.php
```
Expected: all PASS, no failures.

- [ ] **Step 3: Run the full project suite** (single foreground run, never background, never concurrent with another `php artisan test` process)

```bash
php artisan test
```
Expected: no NEW failures beyond the established pre-existing baseline (`LembagaCrudTest`, `RoleBuilderTest` x4, `RoleFormAuditBannerTest` — 6 total, confirmed unrelated to Keuangan/jenis-tagihan in `.agents/logs/keuangan-audit-fixes-01-04.md`). This is the step the prior UI rework skipped — do not skip it here.

- [ ] **Step 4: Write the handoff log**

Write `.agents/logs/2026-08-11-jenis-tagihan-form-ux-clarity.md` covering: all 6 fixes, confirmation that `php artisan test` was actually run (paste the final pass/fail numbers, not just "should be fine"), and current git state. Explicitly note this plan's Stage 5 was followed correctly, in contrast to the prior rework's gap.
