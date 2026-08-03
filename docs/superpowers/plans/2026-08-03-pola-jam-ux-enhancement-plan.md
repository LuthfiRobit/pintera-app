# Pola Jam Pro-Max UX Enhancements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement 1-click schedule duplication, eager-loaded preventive class conflict warning badges in the assign modal, and an interactive weekly timetable matrix view for academic administrators.

**Architecture:** Extend `PolaJamController` with a transactional `duplicate` action and eager-load `polaJam` on the class list query; enrich `index.blade.php` with a duplicate form button and Alpine.js tab switcher for daily cards versus a 7-column calculated weekly timetable matrix; add an inline warning badge in `_modal-assign-kelas.blade.php` using eager-loaded schedule names.

**Tech Stack:** Laravel 11, PHP 8.3, Blade, Alpine.js, Pest / PHPUnit, Tailwind CSS.

## Global Constraints
- Must comply with multi-tenant isolation (`Lembaga` scope and role-based permissions `pola-jam.create`, `pola-jam.view`, `kelas.edit`).
- Zero N+1 query regression when displaying schedule conflict indicators.
- All database modifications during duplication must run atomically inside `DB::transaction()`.

---

### Task 1: Backend 1-Click Duplication & Eager-Loaded Relational Query (TDD)

**Files:**
- Modify: `routes/admin.php`
- Modify: `app/Http/Controllers/Admin/PolaJamController.php`
- Test: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:**
- Consumes: Existing `PolaJam` and `JamPelajaran` Eloquent models.
- Produces: `POST /admin/pola-jam/{polaJam}/duplicate` (`PolaJamController@duplicate`) and enriched `$kelasList` with eager-loaded `polaJam` relationship.

- [ ] **Step 1: Write the failing tests in `tests/Feature/Admin/PolaJamCrudTest.php`**

Append the following test cases to `tests/Feature/Admin/PolaJamCrudTest.php`:

```php
it('duplicates a pola jam along with all its jam pelajaran slots without copying kelas bindings', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Pola Reguler']);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'pola_jam_id' => $pola->id]);
    
    JamPelajaran::create([
        'pola_jam_id' => $pola->id,
        'hari' => 'senin',
        'urutan' => 1,
        'label' => 'Upacara',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:35',
        'is_pelajaran' => false,
    ]);
    JamPelajaran::create([
        'pola_jam_id' => $pola->id,
        'hari' => 'senin',
        'urutan' => 2,
        'label' => 'KBM Ke-1',
        'jam_mulai' => '07:35',
        'jam_selesai' => '08:10',
        'is_pelajaran' => true,
    ]);

    $response = $this->actingAs($manager)->post(route('admin.pola-jam.duplicate', $pola));

    $response->assertRedirect(route('admin.pola-jam.index'));
    $response->assertSessionHas('status', 'Pola jam "Pola Reguler" beserta 2 slot jam berhasil diduplikasi.');

    $clonedPola = PolaJam::where('nama', 'Pola Reguler (Salinan)')->where('lembaga_id', $lembaga->id)->first();
    expect($clonedPola)->not->toBeNull();
    expect($clonedPola->jamPelajaran)->toHaveCount(2);
    expect($clonedPola->kelas)->toHaveCount(0);
});

it('rejects duplicating another lembaga\'s pola jam with 404', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsPolaJamManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $polaB = PolaJam::factory()->create(['lembaga_id' => $lembagaB->id, 'nama' => 'Pola Lembaga B']);

    $this->actingAs($manager)->post(route('admin.pola-jam.duplicate', $polaB))->assertNotFound();
    expect(PolaJam::where('nama', 'Pola Lembaga B (Salinan)')->exists())->toBeFalse();
});

it('loads polaJam relation on kelasList in index view for conflict indicator', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $pola1 = PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Pola Reguler']);
    $pola2 = PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Pola Intensif']);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'pola_jam_id' => $pola1->id, 'nama' => 'VIII-B']);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('VIII-B');
    $response->assertSee('Pola Reguler');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="PolaJamCrudTest"`
Expected: FAIL due to undefined route `admin.pola-jam.duplicate` and method `duplicate`.

- [ ] **Step 3: Write minimal implementation**

In `routes/admin.php`, under the pola-jam group around line 170, add:
```php
Route::post('pola-jam/{polaJam}/duplicate', [PolaJamController::class, 'duplicate'])->name('pola-jam.duplicate');
```

In `app/Http/Controllers/Admin/PolaJamController.php`:
Add DB facade import at top:
```php
use Illuminate\Support\Facades\DB;
```

Update `index()` method to eager-load `polaJam` on `$kelasList`:
```php
    public function index(): View
    {
        $this->authorize('pola-jam.view');

        return view('admin.pola-jam.index', [
            'polaJamList' => PolaJam::with(['jamPelajaran', 'lembaga', 'kelas.tahunAjaran'])->orderBy('nama')->get(),
            'kelasList' => Kelas::with(['tahunAjaran', 'polaJam'])->orderBy('nama')->get(),
        ]);
    }
```

Add `duplicate()` method to `PolaJamController`:
```php
    public function duplicate(PolaJam $polaJam): RedirectResponse
    {
        $this->authorize('pola-jam.create');

        $count = DB::transaction(function () use ($polaJam) {
            $newPola = PolaJam::create([
                'nama' => $polaJam->nama . ' (Salinan)',
                'lembaga_id' => $polaJam->lembaga_id,
            ]);

            foreach ($polaJam->jamPelajaran as $slot) {
                $newPola->jamPelajaran()->create([
                    'hari' => $slot->hari->value,
                    'urutan' => $slot->urutan,
                    'jam_mulai' => $slot->jam_mulai,
                    'jam_selesai' => $slot->jam_selesai,
                    'label' => $slot->label,
                    'is_pelajaran' => $slot->is_pelajaran,
                ]);
            }

            return $polaJam->jamPelajaran->count();
        });

        return redirect()->route('admin.pola-jam.index')->with('status', "Pola jam \"{$polaJam->nama}\" beserta {$count} slot jam berhasil diduplikasi.");
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter="PolaJamCrudTest"`
Expected: PASS (All tests green).

- [ ] **Step 5: Commit**

```bash
git add routes/admin.php app/Http/Controllers/Admin/PolaJamController.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "feat(akademik): add transactional pola jam duplication endpoint and eager load polaJam on kelas"
```

---

### Task 2: UI Duplicate Button & Preventive Conflict Detection Badge

**Files:**
- Modify: `resources/views/admin/pola-jam/index.blade.php`
- Modify: `resources/views/admin/pola-jam/_modal-assign-kelas.blade.php`

**Interfaces:**
- Consumes: Route `admin.pola-jam.duplicate` and `$kelasOpsi->polaJam` property.
- Produces: UI duplicate triggers and real-time amber warning tag when `$kelasOpsi->pola_jam_id` differs from `formAssign.polaId`.

- [ ] **Step 1: Implement UI Duplicate Button in `index.blade.php`**

Locate the card action header in `resources/views/admin/pola-jam/index.blade.php` (around line 125 where *Edit Nama* and *Hapus* buttons reside). Insert the duplicate button before *Edit Nama*:

```html
                                @can('pola-jam.create')
                                    <form action="{{ route('admin.pola-jam.duplicate', $pola) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="rounded-lg border border-brand-200 bg-brand-50/40 px-2.5 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100/60 hover:text-brand-800 transition flex items-center gap-1 shadow-2xs"
                                                title="Salin / Duplikasi Pola Jam">
                                            <x-icon name="content_copy" class="h-3.5 w-3.5" />
                                            <span>Duplikat</span>
                                        </button>
                                    </form>
                                @endcan
```

- [ ] **Step 2: Implement Preventive Conflict Warning in `_modal-assign-kelas.blade.php`**

Locate the class option text label inside the `@foreach ($classes as $kelasOpsi)` loop in `_modal-assign-kelas.blade.php` (around line 63). Update the text display block to include the conflict indicator badge:

```html
                                        <div class="flex flex-col">
                                            <span class="leading-tight">{{ $kelasOpsi->nama }}</span>
                                            @if ($kelasOpsi->pola_jam_id)
                                                <span x-show="formAssign.polaId !== {{ $kelasOpsi->pola_jam_id }}" 
                                                      class="mt-0.5 text-[11px] text-amber-600 font-semibold flex items-center gap-1 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200/80">
                                                    <x-icon name="warning" class="h-3 w-3 text-amber-500 shrink-0" />
                                                    <span class="truncate">Tertaut: {{ $kelasOpsi->polaJam->nama ?? 'Pola Lain' }}</span>
                                                </span>
                                            @endif
                                        </div>
```

- [ ] **Step 3: Run regression tests to verify valid Blade & HTML formatting**

Run: `php artisan test --filter="PolaJamCrudTest|JamPelajaranCrudTest"`
Expected: PASS (All tests pass without rendering error).

- [ ] **Step 4: Commit**

```bash
git add resources/views/admin/pola-jam/index.blade.php resources/views/admin/pola-jam/_modal-assign-kelas.blade.php
git commit -m "feat(akademik): render quick duplicate button on pola jam card and preventive conflict badge in assign modal"
```

---

### Task 3: Interactive Weekly Timetable Matrix View

**Files:**
- Modify: `resources/views/admin/pola-jam/index.blade.php`
- Test: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:**
- Consumes: `$pola->jamPelajaran` collection.
- Produces: Dual-mode presentation switchable between daily cards (`viewMode === 'list'`) and weekly matrix grid (`viewMode === 'matrix'`).

- [ ] **Step 1: Add automated regression test for Matrix text rendering in `tests/Feature/Admin/PolaJamCrudTest.php`**

Append to `PolaJamCrudTest.php`:
```php
it('renders timetable matrix headers and slot chips on pola jam index', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Pola Matriks']);
    JamPelajaran::create([
        'pola_jam_id' => $pola->id,
        'hari' => 'rabu',
        'urutan' => 3,
        'label' => 'Kegiatan Literasi',
        'jam_mulai' => '08:45',
        'jam_selesai' => '09:20',
        'is_pelajaran' => false,
    ]);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));
    
    $response->assertOk();
    $response->assertSee('Matriks Mingguan');
    $response->assertSee('Jam Ke- / Waktu');
    $response->assertSee('Kegiatan Literasi');
    $response->assertSee('08:45 - 09:20');
});
```

- [ ] **Step 2: Implement Timetable Toggle & Matrix Grid in `index.blade.php`**

In `index.blade.php`, wrap the slots list section (below the add slot fast form) with Alpine state `x-data="{ viewMode: 'list' }"`. Add the tab toggle in a sleek header bar and include the matrix table layout beside the grid cards:

```html
                        <!-- Daily Slots & Timetable Matrix Section -->
                        <div x-data="{ viewMode: 'list' }" class="border-t border-gray-200">
                            <div class="flex flex-wrap items-center justify-between gap-3 bg-gray-50/70 px-6 py-3 border-b border-gray-200">
                                <h4 class="font-display text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <x-icon name="schedule" class="h-4 w-4 text-brand-500" />
                                    <span>Susunan Waktu & Jadwal Hari</span>
                                </h4>
                                <div class="flex items-center rounded-lg bg-gray-200/80 p-0.5 border border-gray-300/50">
                                    <button type="button" @click="viewMode = 'list'"
                                            :class="viewMode === 'list' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-medium'"
                                            class="rounded-md px-3 py-1 text-[11px] transition flex items-center gap-1 select-none">
                                        <x-icon name="view_day" class="h-3.5 w-3.5 text-brand-600" />
                                        <span>Kartu Harian</span>
                                    </button>
                                    <button type="button" @click="viewMode = 'matrix'"
                                            :class="viewMode === 'matrix' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-medium'"
                                            class="rounded-md px-3 py-1 text-[11px] transition flex items-center gap-1 select-none">
                                        <x-icon name="table_chart" class="h-3.5 w-3.5 text-brand-600" />
                                        <span>Matriks Mingguan</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Mode 1: Daily Cards Grid -->
                            <div x-show="viewMode === 'list'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
                                <!-- Existing @foreach (['senin', ...] as $hari) loops stay here intact -->
                                <!-- ... existing card content ... -->
                            </div>

                            <!-- Mode 2: Weekly Matrix Timetable Grid -->
                            <div x-show="viewMode === 'matrix'" x-cloak class="p-6 overflow-x-auto">
                                @php
                                    $allUrutan = $pola->jamPelajaran->pluck('urutan')->unique()->sort();
                                    $daysList = [
                                        'senin' => 'Senin', 'selasa' => 'Selasa', 'rabu' => 'Rabu',
                                        'kamis' => 'Kamis', 'jumat' => 'Jum\'at', 'sabtu' => 'Sabtu'
                                    ];
                                @endphp

                                @if ($allUrutan->isEmpty())
                                    <div class="text-center py-12 bg-gray-50/50 rounded-xl border border-dashed border-gray-200">
                                        <x-icon name="info" class="mx-auto h-8 w-8 text-gray-300" />
                                        <p class="mt-2 text-xs font-medium text-gray-500">Belum ada slot jam pelajaran dalam pola ini.</p>
                                    </div>
                                @else
                                    <table class="min-w-full divide-y divide-gray-200 text-left text-xs border border-gray-200 rounded-lg overflow-hidden shadow-2xs">
                                        <thead class="bg-gray-100/80 text-gray-700 font-bold uppercase font-display text-[11px] tracking-wider">
                                            <tr>
                                                <th class="px-4 py-3 border-r border-gray-200 w-32 bg-gray-50 text-center">Jam Ke- / Waktu</th>
                                                @foreach ($daysList as $dayKey => $dayTitle)
                                                    <th class="px-4 py-3 border-r border-gray-200 min-w-[150px]">{{ $dayTitle }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 bg-white font-medium">
                                            @foreach ($allUrutan as $u)
                                                @php
                                                    $sampleSlot = $pola->jamPelajaran->where('urutan', $u)->first();
                                                @endphp
                                                <tr class="hover:bg-gray-50/60 transition">
                                                    <td class="px-3 py-3 border-r border-gray-200 bg-gray-50/30 text-center">
                                                        <span class="font-bold text-gray-800 block text-sm">Jam Ke-{{ $u }}</span>
                                                        @if ($sampleSlot)
                                                            <span class="text-[11px] font-mono text-gray-500 font-semibold">{{ substr($sampleSlot->jam_mulai, 0, 5) }} - {{ substr($sampleSlot->jam_selesai, 0, 5) }}</span>
                                                        @endif
                                                    </td>
                                                    @foreach ($daysList as $dayKey => $dayTitle)
                                                        @php
                                                            $cellSlot = $pola->jamPelajaran->where('hari.value', $dayKey)->where('urutan', $u)->first();
                                                        @endphp
                                                        <td class="px-3 py-2.5 border-r border-gray-200 align-top">
                                                            @if ($cellSlot)
                                                                <div class="group relative flex flex-col justify-between p-2 rounded-lg border {{ $cellSlot->is_pelajaran ? 'bg-brand-50/50 border-brand-200/80 text-brand-900' : 'bg-amber-50 border-amber-200 text-amber-900 font-bold' }} shadow-2xs hover:shadow-xs transition">
                                                                    <div class="flex items-center justify-between gap-1">
                                                                        <span class="text-xs {{ $cellSlot->is_pelajaran ? 'font-semibold' : 'font-bold uppercase tracking-wide text-[11px]' }} truncate">
                                                                            {{ $cellSlot->label ?? ($cellSlot->is_pelajaran ? 'Pelajaran' : 'Kegiatan') }}
                                                                        </span>
                                                                        @can('pola-jam.edit')
                                                                            <button type="button"
                                                                                    @click="openEditSlot({{ $cellSlot->toJson() }}, @js(route('admin.jam-pelajaran.update', $cellSlot)))"
                                                                                    class="opacity-60 group-hover:opacity-100 text-gray-500 hover:text-brand-600 transition p-1 rounded hover:bg-white/80"
                                                                                    title="Edit Jam">
                                                                                <x-icon name="edit" class="h-3.5 w-3.5" />
                                                                            </button>
                                                                        @endcan
                                                                    </div>
                                                                    <div class="mt-1 flex items-center justify-between text-[10px] opacity-80">
                                                                        <span class="font-mono">{{ substr($cellSlot->jam_mulai, 0, 5) }} - {{ substr($cellSlot->jam_selesai, 0, 5) }}</span>
                                                                        @if (!$cellSlot->is_pelajaran)
                                                                            <span class="inline-block px-1.5 py-0.5 rounded bg-amber-200/60 text-amber-900 text-[9px] uppercase font-bold">Non-KBM</span>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            @else
                                                                <div class="h-full w-full flex items-center justify-center min-h-[50px] text-gray-200 font-bold text-lg select-none">
                                                                    -
                                                                </div>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                        </div>
```

- [ ] **Step 3: Run verification test**

Run: `php artisan test --filter="PolaJamCrudTest"`
Expected: PASS (Including our newly added matrix rendering test).

- [ ] **Step 4: Commit**

```bash
git add resources/views/admin/pola-jam/index.blade.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "feat(akademik): add interactive weekly timetable matrix view with alpine switcher on pola jam index"
```
