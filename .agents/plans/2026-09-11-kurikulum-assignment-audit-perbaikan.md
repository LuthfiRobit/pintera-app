# Kurikulum Assignment Audit & Perbaikan — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perbaiki modul Assignment Kurikulum (`resources/views/admin/kurikulum-assignment/*`) sesuai temuan audit UI/UX: cegah salah-ketik `tingkat` lewat pill selector, tambah konfirmasi sebelum sinkronisasi massal, standarkan layout+filter+badge di index, rapikan form edit, dan perbaiki tampilan halaman resync.

**Architecture:** Perubahan murni presentational + 1 penambahan field read-only (`faseLamaNama`) + 1 cabang AJAX read-only di `index()`. Tidak ada perubahan pada domain layer (`KurikulumAssignmentResolver`, `AssignKurikulumAction`, `UpdateKurikulumAssignmentAction`) maupun aturan otorisasi/validasi yang sudah ada.

**Tech Stack:** Laravel 12 (Blade + FormRequest), Alpine.js (pola `x-data`/`dataTableFilter`/`confirmDialog` yang sudah dipakai di modul lain), Pest untuk test.

## Global Constraints

- JANGAN sentuh `KurikulumAssignmentResolver`, `AssignKurikulumAction`, `UpdateKurikulumAssignmentAction` — domain layer sudah diverifikasi solid.
- JANGAN tambah KPI "status drift global" di index — sengaja dikeluarkan dari scope (mahal, butuh loop semua kombinasi lembaga×tahun-ajaran×kelas).
- JANGAN convert `resync.blade.php` jadi full AJAX-SPA — sengaja tetap GET-submit biasa + `confirmDialog`, disproporsional untuk halaman diagnostik low-traffic.
- JANGAN tambah opsi tingkat `"13"` (SMK 4 tahun) — TIDAK ADA di `BentukPendidikan::validTingkatValues()` saat ini (SMK hanya `10`/`11`/`12`).
- Filter AJAX baru di `index()` WAJIB ditempatkan SETELAH blok tenant-scoping yang sudah ada (`if ($scope === 'yayasan') {...} elseif ($scope !== 'platform') {...}`) — BUKAN menggantikannya, supaya tidak membuka celah bypass tenant scope.
- Nilai valid `tingkat` HARUS selalu diambil dari `BentukPendidikan::validTingkatValues()` (satu-satunya source of truth) — JANGAN di-hardcode ulang di Blade/JS manapun.
- Nama icon yang dipakai HARUS ada di `resources/views/components/icon.blade.php` — icon sinkronisasi yang benar adalah `sync` (BUKAN `sync_alt`, yang tidak ada di komponen ini).

---

## Task 1: Pill Selector Anti-Typo untuk Field `tingkat`

**Files:**
- Modify: `app/Http/Controllers/Admin/KurikulumAssignmentController.php` (method `create()` dan `edit()`)
- Modify: `resources/views/admin/kurikulum-assignment/_form.blade.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Enums\BentukPendidikan::validTingkatValues(): array` (sudah ada, TIDAK diubah) — dipanggil untuk SETIAP case di `BentukPendidikan::cases()`.
- Produces: view data key `tingkatOptionsByBentuk` (bentuk `\Illuminate\Support\Collection<string, array<int,string>>`, contoh: `['SD' => ['1','2','3','4','5','6'], 'SMK' => ['10','11','12'], ...]`), dikonsumsi oleh `_form.blade.php` di Task ini dan tetap dipakai apa adanya oleh Task 4.

- [x] **Step 1: Tulis test yang gagal — controller mengirim `tingkatOptionsByBentuk` yang benar**

Tambahkan di akhir `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`:
```php
it('mengirim tingkatOptionsByBentuk yang bersumber dari BentukPendidikan::validTingkatValues() ke halaman create', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SMK']);
    $manager = actingAsKurikulumAssignmentManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.create'));

    $response->assertOk();
    $response->assertViewHas('tingkatOptionsByBentuk', function ($map) {
        return $map['SMK'] === ['10', '11', '12'] && $map['SD'] === ['1', '2', '3', '4', '5', '6'];
    });
});

it('mengirim tingkatOptionsByBentuk ke halaman edit juga', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    $assignment = KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.edit', $assignment));

    $response->assertOk();
    $response->assertViewHas('tingkatOptionsByBentuk', fn ($map) => $map['SD'] === ['1', '2', '3', '4', '5', '6']);
});

it('halaman create menampilkan pill Tingkat Tertentu (bukan input teks bebas)', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $manager = actingAsKurikulumAssignmentManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.create'));

    $response->assertOk();
    $response->assertSee('Semua Tingkat (Default Jenjang)');
    $response->assertSee('Tingkat Tertentu');
    $response->assertDontSee('Contoh: 1, 10, A (kosongkan utk catch-all)');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="tingkatOptionsByBentuk" --compact`
Expected: FAIL — `tingkatOptionsByBentuk` belum ada di view data, dan teks pill belum ada di halaman.

- [x] **Step 3: Tambahkan `tingkatOptionsByBentuk` di controller**

Di `app/Http/Controllers/Admin/KurikulumAssignmentController.php`, method `create()` (sekitar baris 92-113), tambahkan key baru ke array yang dikembalikan `view('admin.kurikulum-assignment.create', [...])`:

```php
public function create(Request $request): View|RedirectResponse
{
    $this->authorize('kurikulum-assignment.create');

    $isPlatform = $request->user()->widestScopeLevel() === 'platform';

    if (! $isPlatform && $this->resolveActiveLembagaId($request->user()) === null) {
        return redirect()->route('admin.kurikulum-assignment.index')
            ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah assignment kurikulum.']);
    }

    $activeLembagaId = $isPlatform ? null : $this->resolveActiveLembagaId($request->user());

    return view('admin.kurikulum-assignment.create', [
        'kurikulumList' => KurikulumFramework::cases(),
        'bentukPendidikanList' => BentukPendidikan::cases(),
        'tahunAjaranList' => $this->tahunAjaranListForScope($request),
        'lembagaList' => $isPlatform ? Lembaga::orderBy('nama')->get() : collect(),
        'isPlatform' => $isPlatform,
        'activeLembaga' => $activeLembagaId ? Lembaga::find($activeLembagaId) : null,
        'tingkatOptionsByBentuk' => collect(BentukPendidikan::cases())
            ->mapWithKeys(fn (BentukPendidikan $bp) => [$bp->value => $bp->validTingkatValues()]),
    ]);
}
```

Method `edit()` (sekitar baris 158-174), tambahkan key yang sama:

```php
public function edit(Request $request, KurikulumAssignment $kurikulumAssignment): View
{
    $this->authorize('kurikulum-assignment.edit');
    $this->authorizeExistingAssignmentScope($request->user(), $kurikulumAssignment->lembaga_id);

    $isPlatform = $request->user()->widestScopeLevel() === 'platform';

    return view('admin.kurikulum-assignment.edit', [
        'assignment' => $kurikulumAssignment->loadMissing([
            'tahunAjaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            'lembaga',
        ]),
        'kurikulumList' => KurikulumFramework::cases(),
        'bentukPendidikanList' => BentukPendidikan::cases(),
        'isPlatform' => $isPlatform,
        'tingkatOptionsByBentuk' => collect(BentukPendidikan::cases())
            ->mapWithKeys(fn (BentukPendidikan $bp) => [$bp->value => $bp->validTingkatValues()]),
    ]);
}
```

- [x] **Step 4: Jalankan test controller-only, pastikan 2 test `tingkatOptionsByBentuk` PASS (test pill markup masih FAIL)**

Run: `php artisan test --filter="tingkatOptionsByBentuk" --compact`
Expected: 2 PASS, 1 FAIL (test markup "Semua Tingkat (Default Jenjang)" karena Blade belum diubah).

- [x] **Step 5: Ganti `_form.blade.php` — bungkus dengan `x-data` dan ganti field Tingkat jadi pill selector**

Ganti SELURUH isi `resources/views/admin/kurikulum-assignment/_form.blade.php` menjadi:

```blade
@php
    $assignment = $assignment ?? null;
    $val = fn (string $field, $default = '') => old($field, $assignment?->$field ?? $default);
    $bentukPendidikanAwal = $assignment
        ? $assignment->bentuk_pendidikan
        : (($isPlatform ?? false) ? $val('bentuk_pendidikan', $bentukPendidikanList[0]->value ?? null) : ($activeLembaga->bentuk_pendidikan ?? null));
@endphp

<div
    x-data="{
        bentukPendidikan: @js($bentukPendidikanAwal),
        tingkatOptions: @js($tingkatOptionsByBentuk),
        modeTingkat: @js($val('tingkat') ? 'spesifik' : 'semua'),
        tingkat: @js($val('tingkat')),
        get pillOptions() { return this.tingkatOptions[this.bentukPendidikan] ?? []; },
    }"
    class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
>
    <div class="border-b border-gray-100 bg-white px-6 py-4">
        <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
            <x-icon name="group" class="h-4 w-4 text-brand-500" />
            Assignment Kurikulum
        </p>
        <p class="mt-0.5 text-xs text-gray-500">Kurikulum yang berlaku untuk jenjang &amp; tingkat pada tahun ajaran tertentu. Kelas baru akan otomatis mengikuti assignment ini saat dibuat.</p>
    </div>

    <div class="p-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
            @if ($assignment)
                {{-- Mode edit: lembaga_id immutable setelah dibuat (UpdateKurikulumAssignmentAction selalu
                     pakai nilai lama), jadi SELALU read-only untuk SEMUA scope aktor termasuk platform --
                     tidak ada gunanya (dan menyesatkan) menampilkan dropdown yang bisa diklik tapi diabaikan. --}}
                <div class="sm:col-span-6">
                    <x-input-label value="Berlaku Untuk" />
                    <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">{{ $assignment->lembaga?->nama ?? '— Global (Platform Default) —' }} <span class="text-gray-400">(tidak bisa diubah setelah dibuat)</span></p>
                </div>
            @elseif ($isPlatform ?? false)
                <div class="sm:col-span-6">
                    <x-input-label value="Berlaku Untuk" />
                    <select name="lembaga_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="" @selected($val('lembaga_id') === '')>— Platform (semua lembaga) —</option>
                        @foreach ($lembagaList as $lembaga)
                            <option value="{{ $lembaga->id }}" @selected($val('lembaga_id') == $lembaga->id)>{{ $lembaga->nama }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('lembaga_id')" class="mt-1.5" />
                </div>
            @else
                <div class="sm:col-span-6">
                    <x-input-label value="Berlaku Untuk" />
                    <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">Assignment ini akan dibuat untuk lembaga aktif Anda saat ini: <strong class="font-semibold text-gray-900">{{ $activeLembaga->nama }}</strong>.</p>
                </div>
            @endif

            @if (! $assignment)
                <div class="sm:col-span-6">
                    <x-input-label value="Tahun Ajaran" />
                    <select name="tahun_ajaran_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($tahunAjaranList as $ta)
                            <option value="{{ $ta->id }}" @selected($val('tahun_ajaran_id') == $ta->id)>{{ $ta->nama }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('tahun_ajaran_id')" class="mt-1.5" />
                </div>
            @else
                <div class="sm:col-span-6">
                    <x-input-label value="Tahun Ajaran" />
                    <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">{{ $assignment->tahunAjaran?->nama ?? '-' }} (tidak bisa diubah setelah dibuat)</p>
                </div>
            @endif

            @if ($isPlatform ?? false)
                <div class="sm:col-span-6">
                    <x-input-label value="Bentuk Pendidikan" />
                    <select name="bentuk_pendidikan" x-model="bentukPendidikan" @change="modeTingkat = 'semua'; tingkat = ''" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($bentukPendidikanList as $bp)
                            <option value="{{ $bp->value }}" @selected($val('bentuk_pendidikan') === $bp->value)>{{ $bp->value }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('bentuk_pendidikan')" class="mt-1.5" />
                </div>
            @else
                @php
                    $lembagaBentukPendidikan = $assignment ? $assignment->lembaga?->bentuk_pendidikan : ($activeLembaga->bentuk_pendidikan ?? null);
                @endphp
                <div class="sm:col-span-6">
                    <x-input-label value="Bentuk Pendidikan" />
                    <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">{{ $lembagaBentukPendidikan }} <span class="text-gray-400">(mengikuti bentuk pendidikan lembaga, tidak bisa diubah)</span></p>
                    <input type="hidden" name="bentuk_pendidikan" value="{{ $lembagaBentukPendidikan }}">
                </div>
            @endif

            <div class="sm:col-span-12">
                <x-input-label value="Tingkat" />
                <div class="mt-1.5 flex flex-wrap gap-2">
                    <button type="button" @click="modeTingkat = 'semua'; tingkat = ''"
                        :class="modeTingkat === 'semua' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                        class="rounded-full border px-3.5 py-1.5 text-xs font-semibold transition">
                        Semua Tingkat (Default Jenjang)
                    </button>
                    <button type="button" @click="modeTingkat = 'spesifik'"
                        :class="modeTingkat === 'spesifik' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                        class="rounded-full border px-3.5 py-1.5 text-xs font-semibold transition">
                        Tingkat Tertentu
                    </button>
                </div>
                <div x-show="modeTingkat === 'spesifik'" x-cloak class="mt-2.5 flex flex-wrap gap-2">
                    <template x-for="opsi in pillOptions" :key="opsi">
                        <button type="button" @click="tingkat = opsi"
                            :class="tingkat === opsi ? 'border-brand-600 bg-brand-600 text-white' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                            class="rounded-lg border px-3.5 py-1.5 text-sm font-semibold transition"
                            x-text="opsi"
                        ></button>
                    </template>
                </div>
                <input type="hidden" name="tingkat" :value="tingkat">
                <x-input-error :messages="$errors->get('tingkat')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-12">
                <x-input-label value="Kurikulum" />
                <select name="kurikulum" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($kurikulumList as $k)
                        <option value="{{ $k->value }}" @selected($val('kurikulum') === $k->value)>{{ $k->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('kurikulum')" class="mt-1.5" />
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4">
        <a href="{{ route('admin.kurikulum-assignment.index') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-200/50 hover:text-gray-900">Batal</a>
        <x-primary-button type="submit">{{ $submitText ?? 'Simpan' }}</x-primary-button>
    </div>
</div>
```

**Catatan penting** (BERBEDA dari draf awal spec — dikoreksi di sini): spec menyebut variabel bantu `$lembagaBentukPendidikanUntukPill` yang diturunkan dari `$assignment->lembaga?->bentuk_pendidikan`. Itu SALAH untuk assignment GLOBAL (`lembaga_id === null`) di mode edit — `$assignment->lembaga` bernilai `null` untuk assignment global, sehingga `bentukPendidikan` Alpine ikut `null` dan pill kosong sama sekali walau assignment itu tetap punya `bentuk_pendidikan` sendiri yang valid. Plan ini pakai `$assignment->bentuk_pendidikan` langsung (field pada baris assignment itu sendiri, SELALU terisi terlepas dari `lembaga_id` null atau tidak) — benar untuk kedua kasus.

- [x] **Step 6: Jalankan seluruh test file, pastikan semua PASS**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: semua test PASS (termasuk test lama yang sudah ada sebelum plan ini — pastikan tidak ada regresi).

- [x] **Step 7: Format PHP yang diubah**

Run: `vendor/bin/pint --dirty --format agent`

- [x] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/KurikulumAssignmentController.php resources/views/admin/kurikulum-assignment/_form.blade.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "feat(kurikulum-assignment): ganti input tingkat jadi pill selector anti-typo"
```

---

## Task 2: `confirmDialog()` Sebelum Sinkronisasi Massal di Resync

**Files:**
- Modify: `resources/views/admin/kurikulum-assignment/resync.blade.php`
- Test: `tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php`

**Interfaces:**
- Consumes: Alpine global `confirmDialog(title, message, options)` (dipakai identik di `index.blade.php:78-83` modul ini sendiri, dan di puluhan halaman lain — TIDAK perlu didaftarkan ulang, sudah tersedia global).
- Produces: Alpine state `terpilih` (array of string kelas-id) pada `<form>` sinkronisasi — dikonsumsi lagi oleh Task 5 (floating bulk bar) di file yang sama.

- [x] **Step 1: Tulis test yang gagal — halaman resync merender wiring confirmDialog dan checkbox x-model**

Tambahkan di akhir `tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php`:
```php
it('halaman resync membungkus submit sinkronisasi dengan confirmDialog (bukan submit langsung)', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13']);
    KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()->update(['kurikulum' => 'merdeka']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.resync', [
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id,
    ]));

    $response->assertOk();
    $response->assertSee('confirmDialog', false);
    $response->assertSee('x-model="terpilih"', false);
});
```

Cek import yang dibutuhkan di atas file test (kalau belum ada): `use App\Domains\Akademik\Models\KurikulumAssignment;`, `use App\Models\Kelas;`, `use App\Models\Lembaga;`, `use App\Models\TahunAjaran;`, dan helper `actingAsKurikulumAssignmentManager` — kalau helper ini belum ada di file `ResyncKurikulumFaseControllerTest.php` (dia didefinisikan di `KurikulumAssignmentControllerTest.php`), copy definisinya persis dari sana:
```php
function actingAsKurikulumAssignmentManager(Lembaga $lembaga): User
{
    foreach (['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}
```
(kalau fungsi dengan nama sama sudah ter-declare di file lain dalam test suite yang sama, Pest/PHP akan fatal error "cannot redeclare" — sebelum menambahkan, jalankan `grep -rn "function actingAsKurikulumAssignmentManager" tests/` dan HANYA tambahkan definisi ini jika belum ada di file manapun yang ikut ter-load bareng file ini.)

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="membungkus submit sinkronisasi" --compact`
Expected: FAIL — halaman belum punya `confirmDialog` ataupun `x-model="terpilih"`.

- [x] **Step 3: Ubah `resync.blade.php` — tambah `x-data`, `confirmDialog`, checkbox `x-model`**

Ganti blok form sinkronisasi (baris 36-70 di file saat ini) dari:
```blade
@if ($lembagaId !== null && $tahunAjaranId !== null)
    <form method="POST" action="{{ route('admin.kurikulum-assignment.resync.apply') }}" class="rounded-2xl border border-gray-200 bg-white shadow-sm">
        @csrf
        <input type="hidden" name="lembaga_id" value="{{ $lembagaId }}">
        <input type="hidden" name="tahun_ajaran_id" value="{{ $tahunAjaranId }}">

        @if (empty($diff))
            <p class="p-6 text-sm text-gray-500">Tidak ada kelas yang perlu disinkronkan -- semua kelas di kombinasi ini sudah sesuai dengan assignment terbaru.</p>
        @else
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600"><input type="checkbox" onclick="document.querySelectorAll('.resync-row').forEach(c => c.checked = this.checked)"></th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Kelas</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Kurikulum: Lama → Seharusnya</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Fase: Lama → Seharusnya</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($diff as $row)
                        <tr>
                            <td class="px-4 py-3"><input type="checkbox" name="kelas_ids[]" value="{{ $row['kelas']->id }}" class="resync-row"></td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row['kelas']->nama }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $row['kurikulumLama'] ?? '-' }} → {{ $row['kurikulumBaru'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $row['faseLamaId'] ?? '-' }} → {{ $row['faseBaruNama'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="p-4">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Sinkronkan yang Dicentang</button>
            </div>
        @endif
    </form>
@endif
```
menjadi (bagian tabel/diff/floating-bar akan disempurnakan lagi di Task 5 — step ini FOKUS hanya pada `x-data`+`confirmDialog`+checkbox, struktur lain TETAP seperti current dulu supaya test Step 1 lulus tanpa mengantisipasi Task 5):
```blade
@if ($lembagaId !== null && $tahunAjaranId !== null)
    <form
        method="POST"
        action="{{ route('admin.kurikulum-assignment.resync.apply') }}"
        class="rounded-2xl border border-gray-200 bg-white shadow-sm"
        x-data="{ terpilih: [] }"
        @submit.prevent="confirmDialog(
            'Sinkronkan Kurikulum/Fase Kelas Terpilih?',
            `Kurikulum dan fase pada ${terpilih.length} kelas terpilih akan diperbarui ke aturan terbaru. Pastikan guru dan wali kelas sudah mengetahui perubahan ini sebelum melanjutkan.`,
            { confirmLabel: 'Ya, Sinkronkan Sekarang', isDanger: false }
        ).then(confirmed => { if (confirmed) $el.submit() })"
    >
        @csrf
        <input type="hidden" name="lembaga_id" value="{{ $lembagaId }}">
        <input type="hidden" name="tahun_ajaran_id" value="{{ $tahunAjaranId }}">

        @if (empty($diff))
            <p class="p-6 text-sm text-gray-500">Tidak ada kelas yang perlu disinkronkan -- semua kelas di kombinasi ini sudah sesuai dengan assignment terbaru.</p>
        @else
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">
                            <input type="checkbox" @click="terpilih = $event.target.checked ? @js(collect($diff)->pluck('kelas.id')->map(fn ($v) => (string) $v)->all()) : []">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Kelas</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Kurikulum: Lama → Seharusnya</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Fase: Lama → Seharusnya</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($diff as $row)
                        <tr>
                            <td class="px-4 py-3"><input type="checkbox" name="kelas_ids[]" value="{{ $row['kelas']->id }}" x-model="terpilih"></td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row['kelas']->nama }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $row['kurikulumLama'] ?? '-' }} → {{ $row['kurikulumBaru'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $row['faseLamaId'] ?? '-' }} → {{ $row['faseBaruNama'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="p-4">
                <button type="submit" :disabled="terpilih.length === 0" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">Sinkronkan yang Dicentang</button>
            </div>
        @endif
    </form>
@endif
```

- [x] **Step 4: Jalankan test, pastikan PASS**

Run: `php artisan test tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php --compact`
Expected: semua PASS.

- [x] **Step 5: Commit**

```bash
git add resources/views/admin/kurikulum-assignment/resync.blade.php tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php
git commit -m "feat(kurikulum-assignment): tambah confirmDialog sebelum sinkronisasi massal"
```

---

## Task 3: Index — Kontainer, KPI, Filter AJAX, Badge Hierarki, Tooltip Read-Only

**Files:**
- Modify: `app/Http/Controllers/Admin/KurikulumAssignmentController.php` (method `index()`)
- Modify: `resources/views/admin/kurikulum-assignment/index.blade.php`
- Create: `resources/views/admin/kurikulum-assignment/_daftar.blade.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Consumes: Alpine global `dataTableFilter(config)` (`resources/js/data-table-filter.js`, sudah terdaftar di `resources/js/app.js` via `Alpine.data('dataTableFilter', dataTableFilter)` — dipakai identik dengan pola `piket-guru/index.blade.php`, TIDAK perlu registrasi baru).
- Produces: partial view `admin.kurikulum-assignment._daftar` menerima `$assignmentList` (Collection hasil query yang SUDAH di-scope tenant + filter, tiap item punya properti dinamis `canManage` seperti sebelumnya).

- [x] **Step 1: Tulis test yang gagal — index() punya cabang ajax dan menerima filter tahun_ajaran_id/bentuk_pendidikan**

Tambahkan di akhir `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`:
```php
it('index() mengembalikan partial _daftar untuk request ajax', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.index'), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk();
    $response->assertViewIs('admin.kurikulum-assignment._daftar');
});

it('index() memfilter berdasarkan tahun_ajaran_id dan bentuk_pendidikan tanpa membuka data lembaga lain', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta1 = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $ta2 = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    $a1 = KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta1->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    $a2 = KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta2->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '2', 'kurikulum' => 'merdeka']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.index', ['tahun_ajaran_id' => $ta1->id]));

    $response->assertOk();
    $response->assertViewHas('assignmentList', fn ($list) => $list->count() === 1 && $list->first()->id === $a1->id);
});

it('filter index() TIDAK bisa dipakai lembaga-scope actor untuk melihat assignment lembaga lain (tetap ter-scope tenant)', function () {
    $lembagaSaya = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $lembagaLain = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $taLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $manager = actingAsKurikulumAssignmentManager($lembagaSaya);
    KurikulumAssignment::create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $taLain->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.index', ['bentuk_pendidikan' => 'SD']));

    $response->assertOk();
    $response->assertViewHas('assignmentList', fn ($list) => $list->isEmpty());
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="index\(\) mengembalikan partial|index\(\) memfilter|filter index\(\) TIDAK bisa" --compact`
Expected: FAIL — belum ada cabang ajax maupun filter query.

- [x] **Step 3: Ubah `index()` di controller — tambah filter (SETELAH tenant-scoping) dan cabang ajax**

Ganti method `index()` (baris 32-68 saat ini) menjadi:
```php
public function index(Request $request): View
{
    $this->authorize('kurikulum-assignment.view');

    $actor = $request->user();
    $scope = $actor->widestScopeLevel();
    $activeLembagaId = $scope === 'yayasan' ? $this->resolveActiveLembagaId($actor) : null;
    $query = KurikulumAssignment::with(['lembaga', 'tahunAjaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]);

    if ($scope === 'yayasan') {
        if ($activeLembagaId !== null) {
            $query->where(function ($q) use ($activeLembagaId) {
                $q->whereNull('lembaga_id')->orWhere('lembaga_id', $activeLembagaId);
            });
        } else {
            $lembagaIds = Lembaga::where('yayasan_id', $actor->yayasan_id)->pluck('id');
            $query->where(function ($q) use ($lembagaIds) {
                $q->whereNull('lembaga_id')->orWhereIn('lembaga_id', $lembagaIds);
            });
        }
    } elseif ($scope !== 'platform') {
        $query->where(function ($q) use ($actor) {
            $q->whereNull('lembaga_id')->orWhere('lembaga_id', $actor->lembaga_id);
        });
    }

    // Filter AJAX -- WAJIB setelah blok tenant-scoping di atas, hanya mempersempit
    // hasil yang SUDAH ter-scope, tidak pernah membukanya.
    if ($tahunAjaranId = $request->query('tahun_ajaran_id')) {
        $query->where('tahun_ajaran_id', $tahunAjaranId);
    }
    if ($bentukPendidikan = $request->query('bentuk_pendidikan')) {
        $query->where('bentuk_pendidikan', $bentukPendidikan);
    }

    $assignmentList = $query->orderByDesc('tahun_ajaran_id')->orderBy('bentuk_pendidikan')->orderByRaw('tingkat IS NULL')->orderBy('tingkat')->get()
        ->each(function (KurikulumAssignment $assignment) use ($actor) {
            $assignment->canManage = $this->canManageAssignment($actor, $assignment->lembaga_id);
        });

    if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
        return view('admin.kurikulum-assignment._daftar', [
            'assignmentList' => $assignmentList,
        ]);
    }

    return view('admin.kurikulum-assignment.index', [
        'assignmentList' => $assignmentList,
        'isYayasan' => $scope === 'yayasan',
        'activeLembaga' => $activeLembagaId ? Lembaga::find($activeLembagaId) : null,
        'tahunAjaranList' => $this->tahunAjaranListForScope($request),
        'bentukPendidikanList' => BentukPendidikan::cases(),
        'filters' => [
            'tahun_ajaran_id' => $request->query('tahun_ajaran_id'),
            'bentuk_pendidikan' => $request->query('bentuk_pendidikan'),
        ],
    ]);
}
```

- [x] **Step 4: Jalankan test, pastikan test controller PASS**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: semua PASS (termasuk test lama).

- [x] **Step 5: Ekstrak tabel jadi `_daftar.blade.php`**

Create `resources/views/admin/kurikulum-assignment/_daftar.blade.php` — isi PERSIS tabel yang sekarang ada di `index.blade.php:43-120` (card pembungkus + table), TANPA perubahan visual di step ini (badge hierarki & tooltip baru ditambahkan di Step 6):
```blade
<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Aturan Kurikulum</p>
        <span class="text-xs text-gray-500">{{ $assignmentList->count() }} aturan</span>
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
                                                Edit Aturan
                                            </span>
                                        </x-dropdown-link>
                                    @endcan
                                    @can('kurikulum-assignment.delete')
                                        <form
                                            method="POST"
                                            action="{{ route('admin.kurikulum-assignment.destroy', $a) }}"
                                            x-data
                                            @submit.prevent="confirmDialog(
                                                'Hapus Aturan Kurikulum?',
                                                @js('Hapus assignment '.$a->bentuk_pendidikan.($a->tingkat ? ' tingkat '.$a->tingkat : ' (semua tingkat)').' untuk '.($a->tahunAjaran->nama ?? 'tahun ajaran ini').'?'.($a->lembaga_id === null ? ' PERINGATAN: ini assignment GLOBAL (Platform Default) — dipakai sebagai cadangan oleh lembaga mana pun yang belum punya assignment sendiri untuk kombinasi ini, dan TIDAK ADA pengecekan otomatis sebelum dihapus.' : '')),
                                                { confirmLabel: 'Ya, Hapus', isDanger: true }
                                            ).then(confirmed => { if (confirmed) $el.submit() })"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-error-600 transition duration-150 ease-in-out hover:bg-error-50 focus:bg-error-50 focus:outline-none">
                                                <x-icon name="delete" class="h-4 w-4" />
                                                Hapus Aturan
                                            </button>
                                        </form>
                                    @endcan
                                </x-table-actions>
                            @else
                                <x-tooltip text="Dikelola oleh Platform Admin. Buat aturan baru untuk menimpa ini khusus lembaga Anda.">
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-400">
                                        <x-icon name="lock" class="h-3.5 w-3.5" />
                                        Read-only
                                    </span>
                                </x-tooltip>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3.5">
                            @if ($a->lembaga_id === null)
                                <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">Standar Platform</span>
                            @else
                                <span class="inline-flex rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700">{{ $a->lembaga->nama ?? 'Lembaga #'.$a->lembaga_id }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3.5 text-gray-600">{{ $a->tahunAjaran->nama ?? '-' }}</td>
                        <td class="whitespace-nowrap px-4 py-3.5 font-semibold text-gray-900">{{ $a->bentuk_pendidikan }}</td>
                        <td class="whitespace-nowrap px-4 py-3.5 text-gray-600">
                            @if ($a->tingkat)
                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">Tingkat {{ $a->tingkat }}</span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                                    <x-icon name="bolt" class="h-3 w-3" />
                                    Semua Tingkat (Default Jenjang)
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-5 py-3.5 text-gray-900">{{ $a->kurikulum->label() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-500">
                            <p class="text-sm">Belum ada aturan kurikulum yang dikonfigurasi.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
```
(Wording "Assignment"→"Aturan" dan "Platform Default"→"Standar Platform" serta badge hierarki/tooltip SENGAJA sudah termasuk langsung di sini karena partial ini baru dibuat di task ini — bukan menyalahi urutan Task 6 "wording terakhir", karena Task 6 nanti tinggal verifikasi tidak ada teks lama yang tersisa, bukan mengubah file yang belum ada.)

- [x] **Step 6: Ganti `index.blade.php` — kontainer, KPI, filter AJAX, include partial**

Ganti SELURUH isi `resources/views/admin/kurikulum-assignment/index.blade.php` menjadi:
```blade
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Kurikulum</h1>
                    @if ($isYayasan ?? false)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                            <x-icon name="apartment" class="h-3.5 w-3.5" />
                            {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-gray-500">Kurikulum yang berlaku per jenjang, tingkat, dan tahun ajaran. Kelas baru mengikuti ini otomatis saat dibuat.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('kurikulum-assignment.view')
                    <x-link-button href="{{ route('admin.kurikulum-assignment.resync') }}" variant="ghost">
                        Sinkronisasi Kurikulum Kelas
                    </x-link-button>
                @endcan
                @can('kurikulum-assignment.create')
                    <x-link-button href="{{ route('admin.kurikulum-assignment.create') }}">
                        <x-icon name="plus" class="h-4 w-4" />
                        Tambah Aturan Kurikulum
                    </x-link-button>
                @endcan
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Aturan</p>
                <p class="font-display text-lg font-bold text-gray-900">{{ $assignmentList->count() }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Cakupan</p>
                <p class="font-display text-lg font-bold text-gray-900">
                    {{ $assignmentList->contains(fn ($a) => $a->lembaga_id !== null) ? 'Ada Aturan Mandiri' : 'Standar Platform' }}
                </p>
            </div>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card"
            x-data="dataTableFilter({
                filters: {
                    tahun_ajaran_id: @js($filters['tahun_ajaran_id'] ?? ''),
                    bentuk_pendidikan: @js($filters['bentuk_pendidikan'] ?? '')
                },
                perPage: 20,
                indexUrlBase: @js(route('admin.kurikulum-assignment.index'))
            })"
        >
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                    Filter Data
                </p>
                <button
                    type="button"
                    x-show="filters.tahun_ajaran_id || filters.bentuk_pendidikan"
                    @click="filters.tahun_ajaran_id = ''; filters.bentuk_pendidikan = ''; for (let key in tomSelects) { if (tomSelects[key]) tomSelects[key].clear(true); } muatUlangDaftar();"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 hover:text-brand-800 transition"
                >
                    <x-icon name="close" class="h-3.5 w-3.5" />
                    Reset Filter
                </button>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Tahun Ajaran</label>
                    <select x-ref="taSelect" x-init="initFilterSelect($refs.taSelect, 'tahun_ajaran_id', true)" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Tahun Ajaran</option>
                        @foreach ($tahunAjaranList as $ta)
                            <option value="{{ $ta->id }}" @selected(($filters['tahun_ajaran_id'] ?? null) == $ta->id)>{{ $ta->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Bentuk Pendidikan</label>
                    <select x-ref="bpSelect" x-init="initFilterSelect($refs.bpSelect, 'bentuk_pendidikan', false)" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Bentuk Pendidikan</option>
                        @foreach ($bentukPendidikanList as $bp)
                            <option value="{{ $bp->value }}" @selected(($filters['bentuk_pendidikan'] ?? null) === $bp->value)>{{ $bp->value }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div x-ref="tableContainer" class="mt-4">
                @include('admin.kurikulum-assignment._daftar')
            </div>
        </div>
    </div>
</x-app-layout>
```

- [x] **Step 7: Jalankan test controller lagi (pastikan view baru tidak error render) dan build asset**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: semua PASS (test lama seperti `denies access to a user without kurikulum-assignment.view permission` yang meng-GET index tidak boleh error render Blade).

Run: `npm run build`
Expected: build sukses tanpa error (memverifikasi `dataTableFilter`/`initFilterSelect`/`confirmDialog` sudah ter-bundle, tidak ada typo Alpine directive).

- [x] **Step 8: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/KurikulumAssignmentController.php resources/views/admin/kurikulum-assignment/index.blade.php resources/views/admin/kurikulum-assignment/_daftar.blade.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "feat(kurikulum-assignment): index dapat filter AJAX, KPI ringkas, badge hierarki fallback"
```

---

## Task 4: Form Edit — Metadata Card, Callout Dampak, Breadcrumb Create/Edit

**Files:**
- Modify: `resources/views/admin/kurikulum-assignment/_form.blade.php` (lanjutan Task 1 — HARUS dikerjakan setelah Task 1 selesai)
- Modify: `resources/views/admin/kurikulum-assignment/create.blade.php`
- Modify: `resources/views/admin/kurikulum-assignment/edit.blade.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Consumes: struktur `_form.blade.php` hasil Task 1 (blok "Berlaku Untuk"/"Tahun Ajaran"/"Tingkat"/"Kurikulum" dengan `x-data` yang sudah ada — TIDAK diubah lagi field Tingkat/Kurikulum-nya di task ini, hanya bagian metadata immutable + callout yang ditambah).

**Catatan penting**: field **Bentuk Pendidikan** SENGAJA TIDAK ikut masuk ke metadata card read-only, walaupun spec awal menyebutnya immutable. Alasan (dikonfirmasi dari kode `KurikulumAssignmentController@update` baris 187-190): untuk aktor **platform**, `bentuk_pendidikan` BOLEH diubah lewat dropdown bahkan di mode edit (`if ($request->user()->widestScopeLevel() !== 'platform') { $bentukPendidikan = Lembaga::find(...)->bentuk_pendidikan; }` — hanya non-platform yang dipaksa ikut lembaga). Kalau field ini dipaksa jadi badge read-only, aktor platform kehilangan kemampuan yang sudah ada. Blok Bentuk Pendidikan (`@if ($isPlatform ?? false) ... @else ... @endif`) TETAP seperti hasil Task 1, HANYA dipindah urutannya ke bawah metadata card.

- [x] **Step 1: Tulis test yang gagal — halaman edit menampilkan metadata card & callout, TIDAK lagi teks lama**

Tambahkan di akhir `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`:
```php
it('halaman edit menampilkan metadata card ringkas dan callout dampak resync', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    $assignment = KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.edit', $assignment));

    $response->assertOk();
    $response->assertSee('Identitas Aturan (terkunci, tidak bisa diubah)');
    $response->assertSee('Sinkronisasi Kurikulum Kelas');
    $response->assertDontSee('(tidak bisa diubah setelah dibuat)');
});

it('halaman create dan edit menampilkan breadcrumb Pengaturan Kurikulum', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $manager = actingAsKurikulumAssignmentManager($lembaga);

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.create'))
        ->assertOk()->assertSee('Pengaturan Kurikulum')->assertSee('Tambah Aturan');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="metadata card ringkas|breadcrumb Pengaturan Kurikulum" --compact`
Expected: FAIL.

- [x] **Step 3: Ubah `_form.blade.php` — pindahkan metadata immutable ke card, tambah callout**

Ganti isi `<div class="p-6"> ... </div>` (bagian dalam wrapper `x-data` dari Task 1) menjadi struktur berikut. SELURUH file `_form.blade.php` setelah step ini:
```blade
@php
    $assignment = $assignment ?? null;
    $val = fn (string $field, $default = '') => old($field, $assignment?->$field ?? $default);
    $bentukPendidikanAwal = $assignment
        ? $assignment->bentuk_pendidikan
        : (($isPlatform ?? false) ? $val('bentuk_pendidikan', $bentukPendidikanList[0]->value ?? null) : ($activeLembaga->bentuk_pendidikan ?? null));
@endphp

<div
    x-data="{
        bentukPendidikan: @js($bentukPendidikanAwal),
        tingkatOptions: @js($tingkatOptionsByBentuk),
        modeTingkat: @js($val('tingkat') ? 'spesifik' : 'semua'),
        tingkat: @js($val('tingkat')),
        get pillOptions() { return this.tingkatOptions[this.bentukPendidikan] ?? []; },
    }"
    class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
>
    <div class="border-b border-gray-100 bg-white px-6 py-4">
        <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
            <x-icon name="group" class="h-4 w-4 text-brand-500" />
            Aturan Kurikulum
        </p>
        <p class="mt-0.5 text-xs text-gray-500">Kurikulum yang berlaku untuk jenjang &amp; tingkat pada tahun ajaran tertentu. Kelas baru akan otomatis mengikuti aturan ini saat dibuat.</p>
    </div>

    <div class="p-6">
        @if ($assignment)
            <div class="mb-5 rounded-xl border border-gray-100 bg-gray-50 p-4">
                <p class="mb-2 text-xs font-semibold text-gray-500">Identitas Aturan (terkunci, tidak bisa diubah)</p>
                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-purple-200 bg-purple-50 px-2.5 py-1 text-xs font-medium text-purple-700">
                        <x-icon name="apartment" class="h-3.5 w-3.5" />
                        {{ $assignment->lembaga?->nama ?? 'Global (Platform Default)' }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                        <x-icon name="calendar_month" class="h-3.5 w-3.5" />
                        {{ $assignment->tahunAjaran?->nama ?? '-' }}
                    </span>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
            @if (! $assignment)
                @if ($isPlatform ?? false)
                    <div class="sm:col-span-6">
                        <x-input-label value="Berlaku Untuk" />
                        <select name="lembaga_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="" @selected($val('lembaga_id') === '')>— Platform (semua lembaga) —</option>
                            @foreach ($lembagaList as $lembaga)
                                <option value="{{ $lembaga->id }}" @selected($val('lembaga_id') == $lembaga->id)>{{ $lembaga->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('lembaga_id')" class="mt-1.5" />
                    </div>
                @else
                    <div class="sm:col-span-6">
                        <x-input-label value="Berlaku Untuk" />
                        <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">Assignment ini akan dibuat untuk lembaga aktif Anda saat ini: <strong class="font-semibold text-gray-900">{{ $activeLembaga->nama }}</strong>.</p>
                    </div>
                @endif

                <div class="sm:col-span-6">
                    <x-input-label value="Tahun Ajaran" />
                    <select name="tahun_ajaran_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($tahunAjaranList as $ta)
                            <option value="{{ $ta->id }}" @selected($val('tahun_ajaran_id') == $ta->id)>{{ $ta->nama }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('tahun_ajaran_id')" class="mt-1.5" />
                </div>
            @endif

            @if ($isPlatform ?? false)
                <div class="sm:col-span-6">
                    <x-input-label value="Bentuk Pendidikan" />
                    <select name="bentuk_pendidikan" x-model="bentukPendidikan" @change="modeTingkat = 'semua'; tingkat = ''" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($bentukPendidikanList as $bp)
                            <option value="{{ $bp->value }}" @selected($val('bentuk_pendidikan') === $bp->value)>{{ $bp->value }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('bentuk_pendidikan')" class="mt-1.5" />
                </div>
            @else
                @php
                    $lembagaBentukPendidikan = $assignment ? $assignment->lembaga?->bentuk_pendidikan : ($activeLembaga->bentuk_pendidikan ?? null);
                @endphp
                <div class="sm:col-span-6">
                    <x-input-label value="Bentuk Pendidikan" />
                    <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">{{ $lembagaBentukPendidikan }} <span class="text-gray-400">(mengikuti bentuk pendidikan lembaga, tidak bisa diubah)</span></p>
                    <input type="hidden" name="bentuk_pendidikan" value="{{ $lembagaBentukPendidikan }}">
                </div>
            @endif

            <div class="sm:col-span-12">
                <x-input-label value="Tingkat" />
                <div class="mt-1.5 flex flex-wrap gap-2">
                    <button type="button" @click="modeTingkat = 'semua'; tingkat = ''"
                        :class="modeTingkat === 'semua' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                        class="rounded-full border px-3.5 py-1.5 text-xs font-semibold transition">
                        Semua Tingkat (Default Jenjang)
                    </button>
                    <button type="button" @click="modeTingkat = 'spesifik'"
                        :class="modeTingkat === 'spesifik' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                        class="rounded-full border px-3.5 py-1.5 text-xs font-semibold transition">
                        Tingkat Tertentu
                    </button>
                </div>
                <div x-show="modeTingkat === 'spesifik'" x-cloak class="mt-2.5 flex flex-wrap gap-2">
                    <template x-for="opsi in pillOptions" :key="opsi">
                        <button type="button" @click="tingkat = opsi"
                            :class="tingkat === opsi ? 'border-brand-600 bg-brand-600 text-white' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                            class="rounded-lg border px-3.5 py-1.5 text-sm font-semibold transition"
                            x-text="opsi"
                        ></button>
                    </template>
                </div>
                <input type="hidden" name="tingkat" :value="tingkat">
                <x-input-error :messages="$errors->get('tingkat')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-12">
                <x-input-label value="Kurikulum" />
                <select name="kurikulum" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($kurikulumList as $k)
                        <option value="{{ $k->value }}" @selected($val('kurikulum') === $k->value)>{{ $k->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('kurikulum')" class="mt-1.5" />
            </div>

            @if ($assignment)
                <div class="sm:col-span-12 flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 text-xs text-blue-800">
                    <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" />
                    <p>Perubahan ini hanya berlaku otomatis untuk <strong>kelas baru</strong> yang dibuat setelah ini. Kelas yang sudah ada TIDAK berubah otomatis — gunakan menu <strong>Sinkronisasi Kurikulum Kelas</strong> untuk menyelaraskannya secara sadar.</p>
                </div>
            @endif
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4">
        <a href="{{ route('admin.kurikulum-assignment.index') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-200/50 hover:text-gray-900">Batal</a>
        <x-primary-button type="submit">{{ $submitText ?? 'Simpan' }}</x-primary-button>
    </div>
</div>
```

- [x] **Step 4: Tambah breadcrumb di `create.blade.php`**

Ganti isi `resources/views/admin/kurikulum-assignment/create.blade.php` menjadi:
```blade
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="font-display text-lg font-bold text-gray-900">Tambah Aturan Kurikulum</h1>
                @if (! ($isPlatform ?? false))
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                        <x-icon name="apartment" class="h-3.5 w-3.5" />
                        {{ $activeLembaga->nama }}
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.kurikulum-assignment.index') }}" class="hover:text-gray-700">Pengaturan Kurikulum</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span>
                <b class="font-semibold text-gray-700">Tambah Aturan</b>
            </p>
        </div>

        <form method="POST" action="{{ route('admin.kurikulum-assignment.store') }}">
            @csrf
            @include('admin.kurikulum-assignment._form', ['kurikulumList' => $kurikulumList, 'bentukPendidikanList' => $bentukPendidikanList, 'tahunAjaranList' => $tahunAjaranList, 'lembagaList' => $lembagaList, 'isPlatform' => $isPlatform, 'tingkatOptionsByBentuk' => $tingkatOptionsByBentuk, 'submitText' => 'Simpan Aturan'])
        </form>
    </div>
</x-app-layout>
```

- [x] **Step 5: Tambah breadcrumb di `edit.blade.php`**

Ganti isi `resources/views/admin/kurikulum-assignment/edit.blade.php` menjadi:
```blade
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Aturan Kurikulum</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.kurikulum-assignment.index') }}" class="hover:text-gray-700">Pengaturan Kurikulum</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span>
                <b class="font-semibold text-gray-700">Edit Aturan</b>
            </p>
        </div>
        <a href="{{ route('admin.kurikulum-assignment.resync') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:text-blue-700">
            <x-icon name="sync" class="h-4 w-4" />
            Sinkronisasi Kurikulum Kelas
        </a>

        <form method="POST" action="{{ route('admin.kurikulum-assignment.update', $assignment) }}">
            @csrf
            @method('PUT')
            @include('admin.kurikulum-assignment._form', ['assignment' => $assignment, 'kurikulumList' => $kurikulumList, 'bentukPendidikanList' => $bentukPendidikanList, 'isPlatform' => $isPlatform, 'tingkatOptionsByBentuk' => $tingkatOptionsByBentuk, 'submitText' => 'Simpan Perubahan'])
        </form>
    </div>
</x-app-layout>
```

- [x] **Step 6: Jalankan test, pastikan PASS**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: semua PASS.

- [x] **Step 7: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/admin/kurikulum-assignment/_form.blade.php resources/views/admin/kurikulum-assignment/create.blade.php resources/views/admin/kurikulum-assignment/edit.blade.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "feat(kurikulum-assignment): metadata card + callout dampak di edit, breadcrumb create/edit"
```

---

## Task 5: Resync — Empty State, `faseLamaNama`, Visual Diff Chip, Zero-Drift State, Floating Bulk Bar

**Files:**
- Modify: `app/Domains/Akademik/Actions/Kelas/ResyncKurikulumFaseKelasAction.php`
- Modify: `resources/views/admin/kurikulum-assignment/resync.blade.php` (lanjutan Task 2 — HARUS dikerjakan setelah Task 2 selesai, memakai state Alpine `terpilih` yang sama)
- Test: `tests/Feature/Akademik/ResyncKurikulumFaseKelasTest.php`, `tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php`

**Interfaces:**
- Consumes: Alpine state `terpilih` dari Task 2 (array kelas-id terpilih pada `<form>` yang sama).
- Produces: `ResyncKurikulumFaseKelasAction::hitungDiff()` mengembalikan array dengan key baru `faseLamaNama: ?string` di setiap baris (selain key lama yang TETAP ada: `kelas`, `kurikulumLama`, `kurikulumBaru`, `faseLamaId`, `faseBaruId`, `faseBaruNama`).

- [x] **Step 1: Tulis test yang gagal — `hitungDiff()` mengembalikan `faseLamaNama`**

Tambahkan di akhir `tests/Feature/Akademik/ResyncKurikulumFaseKelasTest.php`:
```php
it('hitungDiff menyertakan nama fase lama, bukan cuma id mentah', function () {
    [$lembaga, $ta] = siapkanResyncFixture();
    $faseLama = Fase::firstOrCreate(['kode' => 'a'], ['nama' => 'Fase A', 'urutan' => 1]);

    KurikulumAssignment::create([
        'lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13',
    ]);
    $kelas = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13', 'fase_id' => $faseLama->id,
    ]);
    KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()->update(['kurikulum' => 'merdeka']);

    $action = app(ResyncKurikulumFaseKelasAction::class);
    $diff = $action->hitungDiff($lembaga->id, $ta->id);

    expect($diff)->toHaveCount(1);
    expect($diff[0]['faseLamaNama'])->toBe('Fase A');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="hitungDiff menyertakan nama fase lama" --compact`
Expected: FAIL — key `faseLamaNama` belum ada (undefined array key).

- [x] **Step 3: Tambah `faseLamaNama` di `ResyncKurikulumFaseKelasAction::hitungDiff()`**

Di `app/Domains/Akademik/Actions/Kelas/ResyncKurikulumFaseKelasAction.php`, ubah docblock method (baris 21-22) dan body (baris 58-65):
```php
    /**
     * @return array<int, array{kelas: Kelas, kurikulumLama: ?string, kurikulumBaru: ?string, faseLamaId: ?int, faseLamaNama: ?string, faseBaruId: ?int, faseBaruNama: ?string}>
     */
    public function hitungDiff(int $lembagaId, int $tahunAjaranId): array
    {
        $lembaga = Lembaga::findOrFail($lembagaId);
        $kelasList = Kelas::where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $tahunAjaranId)->get();

        $diff = [];

        foreach ($kelasList as $kelas) {
            try {
                $kurikulumBaru = $this->kurikulumResolver->resolve(
                    tahunAjaranId: $tahunAjaranId,
                    bentukPendidikan: $lembaga->bentuk_pendidikan,
                    tingkat: $kelas->tingkat,
                    lembagaId: $lembagaId,
                );
            } catch (KurikulumAssignmentNotFoundException) {
                continue;
            }

            $faseBaru = $this->faseResolver->resolve(
                bentukPendidikan: $lembaga->bentuk_pendidikan,
                tingkat: $kelas->tingkat,
                lembagaId: $lembagaId,
            );

            $kurikulumLamaValue = $kelas->kurikulum?->value;
            $kurikulumBaruValue = $kurikulumBaru->value;
            $faseLamaId = $kelas->fase_id;
            $faseBaruId = $faseBaru?->id;

            if ($kurikulumLamaValue === $kurikulumBaruValue && $faseLamaId === $faseBaruId) {
                continue;
            }

            $diff[] = [
                'kelas' => $kelas,
                'kurikulumLama' => $kurikulumLamaValue,
                'kurikulumBaru' => $kurikulumBaruValue,
                'faseLamaId' => $faseLamaId,
                'faseLamaNama' => $kelas->fase?->nama,
                'faseBaruId' => $faseBaruId,
                'faseBaruNama' => $faseBaru?->nama,
            ];
        }

        return $diff;
    }
```
(HANYA 2 baris yang berubah: docblock `@return`, dan penambahan `'faseLamaNama' => $kelas->fase?->nama,` — sisanya identik dengan file saat ini.)

- [x] **Step 4: Jalankan test action, pastikan PASS**

Run: `php artisan test tests/Feature/Akademik/ResyncKurikulumFaseKelasTest.php --compact`
Expected: semua PASS (termasuk test lama, `faseLamaNama` tidak mengubah kondisi `continue`/skip yang sudah ada).

- [x] **Step 5: Tulis test yang gagal — halaman resync merender diff chip, zero-drift state, dan empty state awal**

Tambahkan di akhir `tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php`:
```php
it('menampilkan empty state instruksional sebelum lembaga/tahun ajaran dipilih', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $manager = actingAsKurikulumAssignmentManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.resync'));

    $response->assertOk();
    $response->assertSee('Pilih Lembaga & Tahun Ajaran untuk Memindai');
});

it('menampilkan zero-drift success state kalau tidak ada perbedaan', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'merdeka']);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'merdeka']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.resync', [
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id,
    ]));

    $response->assertOk();
    $response->assertSee('Semua Kelas Sudah Selaras');
});

it('menampilkan nama fase lama (bukan id mentah) dan floating bulk bar saat ada drift', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13', 'fase_id' => null]);
    KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()->update(['kurikulum' => 'merdeka']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.resync', [
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id,
    ]));

    $response->assertOk();
    $response->assertSee('Tanpa Fase');
    $response->assertSee('terpilih.length', false);
});
```

- [x] **Step 6: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="empty state instruksional|zero-drift success state|nama fase lama.*floating" --compact`
Expected: FAIL — belum ada teks/markup tersebut.

- [x] **Step 7: Ubah `resync.blade.php` — empty state, diff chip, zero-drift state, floating bar, styling select, wording**

Ganti SELURUH isi `resources/views/admin/kurikulum-assignment/resync.blade.php` menjadi:
```blade
<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Sinkronisasi Kurikulum Kelas</h1>
                <p class="text-xs text-gray-500">Alat koreksi manual untuk kelas yang kurikulum/fase tersimpannya sudah tidak sesuai dengan aturan kurikulum terbaru. Tidak ada yang berubah otomatis -- pilih kelas yang mau disinkronkan.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.kurikulum-assignment.index') }}" class="hover:text-gray-700">Pengaturan Kurikulum</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span>
                <b class="font-semibold text-gray-700">Sinkronisasi</b>
            </p>
        </div>

        <form method="GET" action="{{ route('admin.kurikulum-assignment.resync') }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            @if ($isPlatformOrYayasan)
                <div>
                    <x-input-label value="Lembaga" />
                    <select name="lembaga_id" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500" onchange="this.form.submit()">
                        <option value="">— Pilih Lembaga —</option>
                        @foreach ($lembagaList as $l)
                            <option value="{{ $l->id }}" @selected($lembagaId === $l->id)>{{ $l->nama }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <x-input-label value="Tahun Ajaran" />
                <select name="tahun_ajaran_id" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">— Pilih Tahun Ajaran —</option>
                    @foreach ($tahunAjaranList as $ta)
                        <option value="{{ $ta->id }}" @selected($tahunAjaranId === $ta->id)>{{ $ta->nama }}</option>
                    @endforeach
                </select>
            </div>
            <x-primary-button type="submit">Pindai Keselarasan</x-primary-button>
        </form>

        @if ($lembagaId !== null && $tahunAjaranId !== null)
            <form
                method="POST"
                action="{{ route('admin.kurikulum-assignment.resync.apply') }}"
                class="rounded-2xl border border-gray-200 bg-white shadow-sm"
                x-data="{ terpilih: [] }"
                @submit.prevent="confirmDialog(
                    'Sinkronkan Kurikulum/Fase Kelas Terpilih?',
                    `Kurikulum dan fase pada ${terpilih.length} kelas terpilih akan diperbarui ke aturan terbaru. Pastikan guru dan wali kelas sudah mengetahui perubahan ini sebelum melanjutkan.`,
                    { confirmLabel: 'Ya, Sinkronkan Sekarang', isDanger: false }
                ).then(confirmed => { if (confirmed) $el.submit() })"
            >
                @csrf
                <input type="hidden" name="lembaga_id" value="{{ $lembagaId }}">
                <input type="hidden" name="tahun_ajaran_id" value="{{ $tahunAjaranId }}">

                @if (empty($diff))
                    <div class="flex flex-col items-center justify-center gap-2 p-10 text-center">
                        <x-icon name="check_circle" class="h-8 w-8 text-success-500" />
                        <p class="font-display text-sm font-bold text-gray-900">Semua Kelas Sudah Selaras</p>
                        <p class="text-xs text-gray-500">Tidak ada kelas di kombinasi ini yang perlu disinkronkan.</p>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">
                                    <input type="checkbox" @click="terpilih = $event.target.checked ? @js(collect($diff)->pluck('kelas.id')->map(fn ($v) => (string) $v)->all()) : []">
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Kelas</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Kurikulum: Lama → Seharusnya</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Fase: Lama → Seharusnya</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($diff as $row)
                                <tr>
                                    <td class="px-4 py-3"><input type="checkbox" name="kelas_ids[]" value="{{ $row['kelas']->id }}" x-model="terpilih"></td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row['kelas']->nama }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $row['kurikulumLama'] ?? '—' }}</span>
                                            <x-icon name="arrow_forward" class="h-3.5 w-3.5 text-gray-300" />
                                            <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700">{{ $row['kurikulumBaru'] ?? '—' }}</span>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $row['faseLamaNama'] ?? 'Tanpa Fase' }}</span>
                                            <x-icon name="arrow_forward" class="h-3.5 w-3.5 text-gray-300" />
                                            <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700">{{ $row['faseBaruNama'] ?? 'Tanpa Fase' }}</span>
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div x-show="terpilih.length > 0" x-cloak x-transition class="sticky bottom-0 flex items-center justify-between gap-3 border-t border-gray-200 bg-white/95 px-5 py-3.5 backdrop-blur">
                        <p class="text-xs font-medium text-gray-600"><span x-text="terpilih.length"></span> kelas dipilih untuk disinkronkan</p>
                        <button type="submit" :disabled="terpilih.length === 0" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">Terapkan Sinkronisasi</button>
                    </div>
                @endif
            </form>
        @else
            <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-16 text-center">
                <x-icon name="sync" class="h-10 w-10 text-gray-300" />
                <p class="mt-3 font-display text-sm font-bold text-gray-900">Pilih Lembaga & Tahun Ajaran untuk Memindai</p>
                <p class="mt-1 max-w-sm text-xs text-gray-500">Sistem akan memeriksa apakah ada kelas yang kurikulum atau fasenya berbeda dari aturan kurikulum terbaru.</p>
            </div>
        @endif
    </div>
</x-app-layout>
```

**Catatan**: icon dipakai `sync` (BUKAN `sync_alt`, yang tidak ada di `resources/views/components/icon.blade.php` — akan menyebabkan Blade fatal error `Undefined array key` di komponen icon kalau dipaksakan). Tombol submit lama di bawah tabel (di luar floating bar) SUDAH DIHAPUS, digantikan tombol di floating bar sesuai spec.

- [x] **Step 8: Jalankan test, pastikan PASS**

Run: `php artisan test tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php tests/Feature/Akademik/ResyncKurikulumFaseKelasTest.php --compact`
Expected: semua PASS.

Run: `npm run build`
Expected: build sukses.

- [x] **Step 9: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Akademik/Actions/Kelas/ResyncKurikulumFaseKelasAction.php resources/views/admin/kurikulum-assignment/resync.blade.php tests/Feature/Akademik/ResyncKurikulumFaseKelasTest.php tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php
git commit -m "feat(kurikulum-assignment): resync tampilkan nama fase lama, diff chip, floating bulk bar, empty/zero-drift state"
```

---

## Task 6: Standardisasi Wording — Verifikasi Akhir

**Files:**
- Modify (verifikasi, kemungkinan tidak ada sisa perubahan): `resources/views/admin/kurikulum-assignment/{index,_daftar,create,edit,resync}.blade.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`, `tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php`

**Interfaces:**
- Consumes: tidak ada interface baru — task ini murni verifikasi tekstual, semua wording target SUDAH ditulis langsung di kode Task 1-5 (lihat catatan di Task 3 Step 5).

Tabel wording lengkap (referensi, SEMUA baris ini seharusnya SUDAH benar setelah Task 1-5 — task ini mengonfirmasi lewat grep, bukan menulis ulang dari nol):

| Sebelum | Sesudah | Sudah diterapkan di task mana |
|---|---|---|
| "Cek & Perbaiki Kurikulum/Fase" (tombol index) | "Sinkronisasi Kurikulum Kelas" | Task 3 |
| "Cek & Perbaiki Kurikulum/Fase Kelas" (judul resync) | "Sinkronisasi Kurikulum Kelas" | Task 5 |
| "Cek & Perbaiki Kurikulum/Fase Kelas" (link di edit) | "Sinkronisasi Kurikulum Kelas" | Task 4 |
| "Tambah Assignment" | "Tambah Aturan Kurikulum" | Task 3 |
| "Tambah Assignment Kurikulum" (H1 create) | "Tambah Aturan Kurikulum" | Task 4 |
| "Edit Assignment Kurikulum" (H1 edit) | "Edit Aturan Kurikulum" | Task 4 |
| "Daftar Assignment Kurikulum" | "Daftar Aturan Kurikulum" | Task 3 |
| "Edit Assignment" / "Hapus Assignment" (dropdown) | "Edit Aturan" / "Hapus Aturan" | Task 3 |
| "Cek Drift" (tombol form resync) | "Pindai Keselarasan" | Task 5 |
| "Sinkronkan yang Dicentang" | "Terapkan Sinkronisasi" (floating bar) | Task 5 |
| "Platform Default" (badge) | "Standar Platform" | Task 3 |

- [x] **Step 1: Tulis test yang gagal — grep negatif memastikan tidak ada sisa wording lama**

Tambahkan di akhir `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`:
```php
it('tidak ada sisa wording lama "Assignment"/"Platform Default"/"Cek Drift" di halaman index, create, edit', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    $assignment = KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $index = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.index'));
    $index->assertOk();
    $index->assertDontSee('Platform Default');
    $index->assertDontSee('Tambah Assignment');
    $index->assertDontSee('Cek & Perbaiki Kurikulum/Fase');
    $index->assertSee('Standar Platform');
    $index->assertSee('Tambah Aturan Kurikulum');
    $index->assertSee('Sinkronisasi Kurikulum Kelas');

    $create = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.create'));
    $create->assertOk()->assertDontSee('Tambah Assignment Kurikulum')->assertSee('Tambah Aturan Kurikulum');

    $edit = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.edit', $assignment));
    $edit->assertOk()->assertDontSee('Edit Assignment Kurikulum')->assertSee('Edit Aturan Kurikulum');
});
```

Tambahkan di akhir `tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php`:
```php
it('tidak ada sisa wording lama "Cek Drift"/"Sinkronkan yang Dicentang" di halaman resync', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $manager = actingAsKurikulumAssignmentManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.resync'));

    $response->assertOk();
    $response->assertDontSee('Cek Drift');
    $response->assertSee('Pindai Keselarasan');
});
```

- [x] **Step 2: Jalankan test**

Run: `php artisan test --filter="tidak ada sisa wording lama" --compact`
Expected: SEHARUSNYA sudah PASS langsung (semua wording sudah benar sejak Task 1-5). Kalau ADA yang FAIL, berarti ada teks yang terlewat saat Task 1-5 — perbaiki file terkait sampai PASS (grep `Assignment` dan `Cek Drift` di kelima file `resources/views/admin/kurikulum-assignment/*.blade.php` untuk menemukan sisa yang terlewat).

- [x] **Step 3: Commit (kalau ada perbaikan sisa wording; kalau step 2 langsung PASS tanpa perubahan file, commit hanya test barunya)**

```bash
git add tests/Feature/Akademik/KurikulumAssignmentControllerTest.php tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php resources/views/admin/kurikulum-assignment/
git commit -m "test(kurikulum-assignment): kunci standardisasi wording dengan test regresi negatif"
```

---

## Task 7: Regression Sweep Penutup

**Files:** tidak ada file baru — task ini murni verifikasi.

- [x] **Step 1: Jalankan semua test scoped modul ini**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php tests/Feature/Akademik/ResyncKurikulumFaseKelasTest.php tests/Feature/Admin/KurikulumAssignmentDestroyGuardTest.php tests/Unit/Models/KurikulumAssignmentTest.php tests/Unit/Services/KurikulumAssignmentResolverTest.php --compact`
Expected: semua PASS.

- [x] **Step 2: Format seluruh perubahan PHP**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` atau daftar file yang di-fix otomatis — kalau ada yang di-fix, ulangi Step 1.

- [x] **Step 3: Build asset frontend**

Run: `npm run build`
Expected: build sukses tanpa error/warning baru terkait Alpine directive.

- [x] **Step 4: Verifikasi visual — buka halaman index dengan `php artisan route:list --name=kurikulum-assignment`**

Run: `php artisan route:list --name=kurikulum-assignment`
Expected: semua route (`index`, `create`, `store`, `edit`, `update`, `destroy`, `resync`, `resync.apply`) masih terdaftar persis seperti sebelumnya — TIDAK ada route yang berubah nama/hilang (plan ini murni ubah controller method body + view, bukan routing).

- [ ] **Step 5: Tanyakan ke user apakah mau full suite**

Jangan jalankan `php artisan test` (full suite) tanpa izin eksplisit — tanyakan ke user dulu, HANYA jalankan sendirian (tidak paralel dengan proses test lain) kalau disetujui.

- [ ] **Step 6: Commit penutup (kalau Step 2 menghasilkan perubahan format)**

```bash
git add -A
git commit -m "chore(kurikulum-assignment): regression sweep penutup"
```
(kalau tidak ada perubahan file di step ini, skip commit — tidak boleh commit kosong.)

---

## Self-Review (5 Putaran)

**Putaran 1 — Cakupan spec vs task**: Semua §2.1-§2.7 dari spec punya task yang eksplisit mengimplementasikannya (Task 1↔§2.1, Task 2+5↔§2.2+§2.6, Task 3↔§2.3, Task 4↔§2.4+§2.5, Task 6↔§2.7). Global constraints spec §4 semua masuk ke section "Global Constraints" plan ini kata-per-kata.

**Putaran 2 — Koreksi kesalahan turunan dari spec**: Ditemukan 2 kesalahan spec yang HARUS dikoreksi di plan ini (bukan rancangan ulang, tapi bug nyata kalau diikuti mentah-mentah):
1. Variabel `$lembagaBentukPendidikanUntukPill` di spec §2.1 salah untuk assignment GLOBAL mode edit (`$assignment->lembaga` null → pill kosong). Diganti pakai `$assignment->bentuk_pendidikan` langsung di Task 1 Step 5 — dicatat eksplisit sebagai "Catatan penting" di task itu.
2. Icon `sync_alt` dipakai spec §2.6 untuk empty state TIDAK ADA di `resources/views/components/icon.blade.php` (dicek langsung, hanya ada `sync`). Diganti `sync` di Task 5 Step 7 + ditambahkan ke Global Constraints supaya tidak terulang.
3. (Tambahan, ditemukan saat menyusun Task 4) Field **Bentuk Pendidikan** di spec §2.4 disebut "immutable" masuk metadata card — TAPI kode `KurikulumAssignmentController@update` baris 187-190 membuktikan aktor **platform** BOLEH mengubahnya bahkan di mode edit (hanya non-platform yang dikunci). Task 4 mengoreksi ini: metadata card HANYA berisi "Berlaku Untuk" + "Tahun Ajaran", Bentuk Pendidikan tetap dropdown/hidden-input seperti struktur Task 1, cuma dipindah urutan.

**Putaran 3 — Urutan & ketergantungan antar task**: Task 4 (edit `_form.blade.php`) diberi label eksplisit "HARUS setelah Task 1" karena keduanya mengedit file yang sama secara berurutan (Task 1 taruh pill selector, Task 4 taruh metadata card di sekitarnya) — kalau dikerjakan sebagai subagent paralel, salah satu commit akan menimpa punya yang lain. Task 5 (resync lanjutan) diberi label "HARUS setelah Task 2" dengan alasan sama (state `terpilih` diperkenalkan Task 2, dipakai Task 5). Task 6 sengaja terakhir sebelum sweep karena dia hanya VERIFIKASI (grep-style test), bukan menulis wording baru — semua wording target sudah ditulis langsung di kode Task 1-5 (dicatat eksplisit supaya implementer Task 6 tidak bingung "kok gak ada yang perlu diubah").

**Putaran 4 — Keamanan/regresi tenant-scope**: Task 3 Step 3 (filter AJAX di `index()`) ditulis dengan filter `where()` DITEMPATKAN SETELAH blok `if ($scope === 'yayasan') {...} elseif ($scope !== 'platform') {...}` yang sudah ada — ditambahkan test eksplisit `'filter index() TIDAK bisa dipakai lembaga-scope actor untuk melihat assignment lembaga lain'` yang memverifikasi ini bukan cuma lewat pembacaan kode, tapi lewat assertion nyata (lembaga-scope actor filter by `bentuk_pendidikan=SD` tetap dapat 0 hasil untuk assignment milik lembaga lain). Task 5 (`hitungDiff()`) hanya menambah 1 key baca-saja (`faseLamaNama`), tidak mengubah kondisi `continue`/skip yang sudah ada — tidak berisiko mengubah kelas mana yang dianggap "perlu di-resync".

**Putaran 5 — Placeholder scan & konsistensi tipe/nama**: Scan ulang semua task untuk pola red-flag ("TODO", "seperti biasa", "tambahkan validasi yang sesuai") — tidak ditemukan, semua step berisi kode lengkap siap tempel. Konsistensi nama dicek: `tingkatOptionsByBentuk` (Task 1 produce → Task 4 consume, sama persis di kedua tempat termasuk saat di-`@include` dari `create.blade.php`/`edit.blade.php`), `terpilih` (Task 2 produce → Task 5 consume, sama persis), `faseLamaNama` (Task 5 Action produce → Task 5 Blade consume di file yang sama, konsisten). Tidak ditemukan mismatch nama/tipe antar task.
