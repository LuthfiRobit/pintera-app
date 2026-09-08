# Spec: Perbaikan Kritis & Kejujuran Wording — Menu Kurikulum Assignment

> **Branch**: `akademik-v2` (setara `rbac-v2`)
> **Tanggal**: 8 September 2026
> **Latar belakang**: Audit menu "Pengaturan Kurikulum" (Kurikulum Assignment) atas permintaan user yang menilai halaman ini "sangat ambigu". Beda dari menu-menu lain di rangkaian audit ini, halaman ini punya **2 sumbu scope yang tercampur**: scope AKTOR (platform/yayasan/lembaga) DAN scope ASSIGNMENT itu sendiri (`lembaga_id = null` = "Platform Default"/global fallback, vs `lembaga_id = X` = spesifik 1 lembaga, meng-override nilai global untuk lembaga itu). Audit ini menemukan **1 bug crash (500) yang belum pernah terdeteksi**, 2 bug logic view/backend yang tidak sinkron, dan beberapa gap wording — SEMUANYA murni di lapisan Controller + Blade view + test file miliknya sendiri.
>
> **Batas blast-radius dikonfirmasi eksplisit**: `KurikulumAssignmentResolver` (dipakai `CreateKelasAction`/modul Kelas) dan `ResyncKurikulumFaseKelasAction` (tool "Cek & Perbaiki Kurikulum/Fase") HANYA membaca data (`lembaga_id`, `tahun_ajaran_id`, `bentuk_pendidikan`, `tingkat`, `kurikulum`) lewat query `SELECT` biasa — TIDAK PEDULI siapa boleh klik Edit/Hapus di UI atau bagaimana error ditampilkan. Spec ini TIDAK mengubah `KurikulumAssignment` model, `KurikulumAssignmentResolver`, `AssignKurikulumAction`, `UpdateKurikulumAssignmentAction`, `CreateKurikulumAssignmentAction`, DTO, atau skema tabel — NILAI yang tersimpan di database sama sekali tidak berubah, cuma SIAPA yang boleh sampai ke titik menulis dan BAGAIMANA itu ditampilkan.

## Ringkasan Temuan (6 item, 2 kelompok)

| # | Kelompok | Severity | Ringkasan |
|---|---|---|---|
| A.1 | Backend/Crash | 🔴 **Kritis** | Halaman **edit CRASH (500)** untuk aktor platform-scope pada assignment APA PUN — `edit()` tidak mengirim `$lembagaList` tapi `_form.blade.php` unconditional `@foreach ($lembagaList...)` untuk platform. Tidak terdeteksi test manapun (tidak ada test yang benar-benar GET halaman edit sebagai platform-scope). |
| A.2 | Backend | 🔴 Tinggi | `$canManage` di `index.blade.php` dihitung ulang independen dari backend, HASILNYA BEDA — aktor yayasan-scope melihat tombol Edit/Hapus AKTIF di baris "Platform Default" (global), padahal backend `authorizeExistingAssignmentScope()` pasti `abort(403)` |
| A.3 | Backend | 🟡 Sedang | `store()` untuk yayasan-scope tanpa lembaga aktif melempar `abort(422)` MENTAH (lewat `resolveLembagaId()`→`resolveLembagaIdUntukYayasan()`), BUKAN `back()->withErrors()` ramah seperti pola yang sudah diperbaiki di Kelas/TahunAjaran/MataPelajaran — kehilangan seluruh isian form |
| B.1 | Frontend | 🟡 Sedang | Halaman create tidak punya badge/indikasi nama lembaga yang sedang dipakai, padahal teksnya cuma bilang "lembaga yang sedang aktif" tanpa menyebut namanya |
| B.2 | Frontend | 🟡 Sedang | `create()` (GET) tidak divalidasi lembaga aktif sebelum render form — pola sama seperti bug yang sudah diperbaiki di Kelas |
| B.3 | Frontend | 🟢 Rendah | Index TIDAK PERNAH menyempit walau aktor yayasan-scope switch lembaga (SENGAJA, by design — selalu agregat seluruh yayasan + global) — tidak dijelaskan di halaman, berpotensi membingungkan karena PERILAKU INI BEDA dari kebanyakan menu lain yang baru saja diaudit (Kelas/TahunAjaran/MataPelajaran SEMUANYA menyempit saat switch) |

## Keputusan yang Diambil

1. **Kepemilikan assignment "Platform Default" (global) TETAP eksklusif Platform Admin** — dikonfirmasi eksplisit oleh user. Backend `authorizeExistingAssignmentScope()` TIDAK diubah logikanya. Perbaikan A.2 murni menyinkronkan VIEW ke logika backend yang SUDAH BENAR, bukan mengubah wewenang.
2. **A.1 (crash) diperbaiki dengan restrukturisasi kondisi di `_form.blade.php`**, BUKAN sekadar menambah `lembagaList` ke `edit()`. Alasan: dropdown "Berlaku Untuk" yang bisa diklik di mode edit itu SENDIRI menyesatkan — `lembaga_id` immutable setelah dibuat (`UpdateKurikulumAssignmentAction`/`update()` SELALU pakai `$kurikulumAssignment->lembaga_id` yang lama, mengabaikan apa pun yang dipilih di dropdown). Jadi solusi yang benar BUKAN "kasih platform admin `lembagaList` supaya dropdownnya jalan", tapi **mode edit SELALU tampil read-only untuk SEMUA scope aktor termasuk platform** — ini menutup crash SEKALIGUS menghapus UI yang secara desain memang tidak seharusnya ada.
3. **Setelah A.2 diperbaiki dengan benar, label "Read-only (Platform)" OTOMATIS SELALU AKURAT — TIDAK PERLU fix terpisah.** Analisis: `index()`'s query SUDAH memfilter agar aktor non-platform HANYA PERNAH melihat baris global ATAU baris lembaga yang secara backend PASTI bisa mereka kelola (yayasan-scope: lembaga apa pun dalam yayasannya sendiri — query `whereIn('lembaga_id', $lembagaIds)` sudah menjamin ini; lembaga-scope: cuma lembaganya sendiri — query `where('lembaga_id', $actor->lembaga_id)` sudah menjamin ini). Jadi SATU-SATUNYA baris yang bisa `canManage = false` untuk aktor non-platform adalah baris global — label "(Platform)" SELALU benar secara struktural, bukan kebetulan. **Tidak ada task terpisah untuk ini di spec.**
4. **TIDAK ADA badge "Semua Lembaga vs 1 Lembaga" di halaman INDEX** (beda dari Tahun Ajaran/Kelas/Mata Pelajaran) — dikonfirmasi lewat pembacaan `index()`: query untuk yayasan-scope TIDAK PERNAH memeriksa `session('active_lembaga_id')` sama sekali, SELALU agregat penuh (`whereIn('lembaga_id', $lembagaIds)` = SEMUA lembaga yayasan, bukan cuma yang aktif). Menambahkan badge di sini akan BERBOHONG (menyiratkan daftar menyempit saat switch, padahal tidak pernah). Sebagai gantinya (Item B.3), cukup 1 baris catatan statis penjelas.
5. **Badge nama lembaga (B.1) HANYA di halaman CREATE**, bukan index — halaman create BENAR-BENAR selalu 1 lembaga spesifik (dijamin guard B.2), beda dari index yang sengaja agregat.
6. **"3-state Scope badge" (membedakan visual "lembaga saya" vs "lembaga lain di yayasan saya") DIPERTIMBANGKAN tapi TIDAK dimasukkan** — dianalisis TIDAK relevan: untuk aktor yayasan-scope, SEMUA baris lembaga-spesifik yang mereka lihat SUDAH PASTI bisa mereka kelola (poin 3 di atas), jadi "milik saya vs bukan" tidak eksis sebagai konsep yang berguna di sini (beda dari kekhawatiran awal audit). Dicatat di "Di Luar Scope".

---

## Kelompok A — Backend

### A.1 — Crash Halaman Edit untuk Platform-Scope (KRITIS)

**File**: `resources/views/admin/kurikulum-assignment/_form.blade.php` (baris 17-38)

Kode saat ini:
```blade
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
@elseif (! $assignment)
    <div class="sm:col-span-6">
        <x-input-label value="Berlaku Untuk" />
        <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">Assignment ini akan dibuat untuk lembaga yang sedang aktif di sesi Anda.</p>
    </div>
@else
    <div class="sm:col-span-6">
        <x-input-label value="Berlaku Untuk" />
        <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">{{ $assignment->lembaga?->nama ?? '— Global (semua lembaga) —' }}</p>
    </div>
@endif
```

**Akar masalah**: urutan pengecekan SALAH — `$isPlatform` dicek DULUAN, sebelum tahu apakah ini mode create atau edit. Untuk platform-scope MENGEDIT assignment apa pun, jatuh ke cabang pertama yang butuh `$lembagaList` — variabel yang `edit()` TIDAK PERNAH kirim (cuma `create()` yang mengirimnya) → `foreach()` di `null` → `TypeError` fatal.

Fix — balik urutan pengecekan, `$assignment` (mode edit vs create) dicek PALING DULUAN:
```blade
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
```

**Efek samping yang DISENGAJA**: cabang `@elseif ($isPlatform ?? false)` (dropdown `$lembagaList`) sekarang HANYA PERNAH dieksekusi saat `! $assignment` (mode create) — `edit()` TIDAK PERLU DIUBAH untuk mengirim `$lembagaList` sama sekali, karena cabang itu sudah tidak mungkin tereksekusi dari edit lagi. Ini SEKALIGUS menutup crash DAN menghapus UI yang menyesatkan (Keputusan #2).

Teks placeholder create-mode (`@else` terakhir) diganti sekalian (Item B.1's `$activeLembaga` — lihat B.2 di bawah untuk asal variabel ini) supaya menyebut NAMA lembaga eksplisit, bukan cuma "lembaga yang sedang aktif".

---

### A.2 — `$canManage` Tidak Sinkron dengan Backend

**File 1**: `app/Http/Controllers/Admin/KurikulumAssignmentController.php`

Tambah private method baru (mirror PERSIS kondisi `authorizeExistingAssignmentScope()`, TAPI return bool alih-alih abort — TIDAK memanggil/mengubah `authorizeExistingAssignmentScope()` itu sendiri, method itu TETAP dipakai apa adanya oleh `edit()`/`update()`/`destroy()`):
```php
private function canManageAssignment(User $actor, ?int $existingLembagaId): bool
{
    if ($actor->widestScopeLevel() === 'platform') {
        return true;
    }

    if ($existingLembagaId === null) {
        return false;
    }

    if ($actor->widestScopeLevel() === 'yayasan') {
        return Lembaga::where('id', $existingLembagaId)->where('yayasan_id', $actor->yayasan_id)->exists();
    }

    return $existingLembagaId === $actor->lembaga_id;
}
```

Ganti method `index()`:
```php
public function index(Request $request): View
{
    $this->authorize('kurikulum-assignment.view');

    $scope = $request->user()->widestScopeLevel();
    $query = KurikulumAssignment::with(['lembaga', 'tahunAjaran']);

    if ($scope === 'yayasan') {
        $lembagaIds = Lembaga::where('yayasan_id', $request->user()->yayasan_id)->pluck('id');
        $query->where(function ($q) use ($lembagaIds) {
            $q->whereNull('lembaga_id')->orWhereIn('lembaga_id', $lembagaIds);
        });
    } elseif ($scope !== 'platform') {
        $query->where(function ($q) use ($request) {
            $q->whereNull('lembaga_id')->orWhere('lembaga_id', $request->user()->lembaga_id);
        });
    }

    return view('admin.kurikulum-assignment.index', [
        'assignmentList' => $query->orderByDesc('tahun_ajaran_id')->orderBy('bentuk_pendidikan')->orderByRaw('tingkat IS NULL')->orderBy('tingkat')->get(),
        'isPlatformOrYayasan' => in_array($scope, ['platform', 'yayasan'], true),
    ]);
}
```
menjadi:
```php
public function index(Request $request): View
{
    $this->authorize('kurikulum-assignment.view');

    $actor = $request->user();
    $scope = $actor->widestScopeLevel();
    $query = KurikulumAssignment::with(['lembaga', 'tahunAjaran']);

    if ($scope === 'yayasan') {
        $lembagaIds = Lembaga::where('yayasan_id', $actor->yayasan_id)->pluck('id');
        $query->where(function ($q) use ($lembagaIds) {
            $q->whereNull('lembaga_id')->orWhereIn('lembaga_id', $lembagaIds);
        });
    } elseif ($scope !== 'platform') {
        $query->where(function ($q) use ($actor) {
            $q->whereNull('lembaga_id')->orWhere('lembaga_id', $actor->lembaga_id);
        });
    }

    $assignmentList = $query->orderByDesc('tahun_ajaran_id')->orderBy('bentuk_pendidikan')->orderByRaw('tingkat IS NULL')->orderBy('tingkat')->get()
        ->each(function (KurikulumAssignment $assignment) use ($actor) {
            $assignment->canManage = $this->canManageAssignment($actor, $assignment->lembaga_id);
        });

    return view('admin.kurikulum-assignment.index', [
        'assignmentList' => $assignmentList,
        'isYayasan' => $scope === 'yayasan',
    ]);
}
```

**Catatan**: `isPlatformOrYayasan` DIHAPUS dari view data (SATU-SATUNYA pemakaiannya di file ini adalah `$canManage` yang dihapus) — DIGANTI `isYayasan`, dipakai Item B.3 (catatan statis). Dikonfirmasi lewat grep bahwa nama variabel `isPlatformOrYayasan` DIPAKAI ULANG di controller/view LAIN (`FaseDefaultMappingController`, `ResyncKurikulumFaseController`, `fase-mapping/index.blade.php`, `kurikulum-assignment/resync.blade.php`) — TIDAK TERKAIT, file-file itu instance VARIABEL SENDIRI dengan nama kebetulan sama, TIDAK disentuh perubahan ini sama sekali.

**File 2**: `resources/views/admin/kurikulum-assignment/index.blade.php` (baris 57-77)

Kode saat ini:
```blade
<td class="whitespace-nowrap px-6 py-3.5 text-right text-sm">
    @php
        $canManage = $isPlatformOrYayasan || ($a->lembaga_id !== null && $a->lembaga_id === auth()->user()->lembaga_id);
    @endphp
    @if ($canManage)
```

Fix:
```blade
<td class="whitespace-nowrap px-6 py-3.5 text-right text-sm">
    @if ($a->canManage)
```

(Sisa isi `@if`/`@else` di bawahnya TIDAK BERUBAH — termasuk teks "Read-only (Platform)", lihat Keputusan #3 kenapa label ini tidak perlu diubah.)

---

### A.3 — `store()`: Guard Ramah, Bukan `abort(422)` Mentah

**File**: `app/Http/Controllers/Admin/KurikulumAssignmentController.php` (method `store()`)

Kode saat ini:
```php
$validated = $request->validated();
$tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;
$lembagaIdDiminta = $request->user()->widestScopeLevel() === 'platform' ? ($validated['lembaga_id'] ?? null) : null;
$lembagaId = $this->resolveLembagaId($request->user(), $lembagaIdDiminta);
```

Fix:
```php
$validated = $request->validated();
$tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;
$lembagaIdDiminta = $request->user()->widestScopeLevel() === 'platform' ? ($validated['lembaga_id'] ?? null) : null;

if ($request->user()->widestScopeLevel() === 'yayasan' && $this->resolveActiveLembagaId($request->user()) === null) {
    return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah assignment kurikulum.'])->withInput();
}

$lembagaId = $this->resolveLembagaId($request->user(), $lembagaIdDiminta);
```

**Kenapa aman**: `resolveLembagaId()` (dipanggil setelah guard) TETAP DIPAKAI APA ADANYA — guard baru ini HANYA mencegat 1 skenario spesifik (yayasan-scope + `resolveActiveLembagaId()` null) SEBELUM sampai ke `resolveLembagaId()`'s internal `resolveLembagaIdUntukYayasan()` yang mem-`abort_if()`. Untuk platform (selalu punya `$lembagaIdDiminta` eksplisit atau `null`=global, valid) dan lembaga-scope (`resolveLembagaId()` langsung return `$actor->lembaga_id`, tidak pernah null) — jalur lama TIDAK BERUBAH SAMA SEKALI, guard baru ini TIDAK PERNAH ter-trigger untuk mereka.

**Test existing WAJIB DIUBAH** (bukan ditambah baru): `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` baris ±195 ("yayasan tanpa active_lembaga_id di sesi ditolak dengan pesan jelas saat membuat assignment") — saat ini `->assertStatus(422)`. Ganti jadi `->assertRedirect()->assertSessionHasErrors('lembaga_id')` (pola sama seperti fix Kelas/TahunAjaran/MataPelajaran).

---

## Kelompok B — Frontend

### B.1 & B.2 — Guard `create()` + Badge/Nama Lembaga (Digabung, 1 Method)

**File**: `app/Http/Controllers/Admin/KurikulumAssignmentController.php` (method `create()`)

Kode saat ini:
```php
public function create(Request $request): View
{
    $this->authorize('kurikulum-assignment.create');

    $isPlatform = $request->user()->widestScopeLevel() === 'platform';

    return view('admin.kurikulum-assignment.create', [
        'kurikulumList' => KurikulumFramework::cases(),
        'bentukPendidikanList' => BentukPendidikan::cases(),
        'tahunAjaranList' => $this->tahunAjaranListForScope($request),
        'lembagaList' => $isPlatform ? Lembaga::orderBy('nama')->get() : collect(),
        'isPlatform' => $isPlatform,
    ]);
}
```

Fix:
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
    ]);
}
```

**Efek samping yang DISENGAJA**: guard ini membuat `tahunAjaranListForScope()`'s cabang "yayasan tanpa lembaga aktif → agregat semua lembaga yayasan" (baris ±190-198 method itu) TIDAK PERNAH tereksekusi lagi dari `create()` (yayasan-scope sekarang PASTI sudah punya lembaga aktif sebelum sampai ke situ) — dropdown Tahun Ajaran otomatis TIDAK PERNAH lagi menampilkan opsi campur-lembaga tanpa label untuk yayasan-scope. `tahunAjaranListForScope()` method itu sendiri TIDAK PERLU diubah kodenya (cabang itu jadi dead-code-in-practice untuk `create()`, tapi method ini generik/private, biarkan apa adanya — tidak ada risiko, tidak ada urgensi menghapus baris yang tidak tereksekusi).

**File**: `resources/views/admin/kurikulum-assignment/create.blade.php` (baris 7)

Kode saat ini:
```blade
<h1 class="font-display text-lg font-bold text-gray-900">Tambah Assignment Kurikulum</h1>
```

Fix:
```blade
<div class="flex flex-wrap items-center gap-2.5">
    <h1 class="font-display text-lg font-bold text-gray-900">Tambah Assignment Kurikulum</h1>
    @if (! ($isPlatform ?? false))
        <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
            <x-icon name="apartment" class="h-3.5 w-3.5" />
            {{ $activeLembaga->nama }}
        </span>
    @endif
</div>
```

Badge HANYA untuk non-platform (guard di atas menjamin `$activeLembaga` tidak null di titik ini) — platform TIDAK dapat badge (mereka belum tentu membuat untuk 1 lembaga tertentu, baru ditentukan lewat dropdown "Berlaku Untuk" saat mengisi form, bukan ditentukan di muka).

`_form.blade.php`'s teks "Assignment ini akan dibuat untuk lembaga aktif Anda saat ini: **{{ $activeLembaga->nama }}**." SUDAH termasuk dalam fix A.1 di atas (1 file yang sama, disatukan supaya tidak menyentuh blok `@if`/`@elseif`/`@else` yang sama 2 kali secara terpisah).

---

### B.3 — Catatan Statis: Index Selalu Agregat, Tidak Terpengaruh Switcher

**File**: `resources/views/admin/kurikulum-assignment/index.blade.php` (baris 11-15)

Kode saat ini:
```blade
<div>
    <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Kurikulum</h1>
    <p class="text-xs text-gray-500">Kurikulum yang berlaku per jenjang, tingkat, dan tahun ajaran. Kelas baru mengikuti ini otomatis saat dibuat.</p>
</div>
```

Fix:
```blade
<div>
    <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Kurikulum</h1>
    <p class="text-xs text-gray-500">Kurikulum yang berlaku per jenjang, tingkat, dan tahun ajaran. Kelas baru mengikuti ini otomatis saat dibuat.</p>
    @if ($isYayasan ?? false)
        <p class="mt-1 text-xs text-gray-400">Daftar ini selalu menampilkan SEMUA lembaga di yayasan Anda beserta assignment global — tidak menyempit walau Anda mengganti lembaga aktif lewat pengalih lembaga di pojok kanan atas.</p>
    @endif
</div>
```

`$isYayasan` sudah tersedia dari fix A.2 di atas (1 method `index()` yang sama, tidak ada perubahan controller tambahan untuk item ini).

---

## Di Luar Scope / Backlog Terpisah

1. **"3-state Scope badge" (bedakan visual "lembaga saya" vs "lembaga lain di yayasan saya")** — DIPERTIMBANGKAN, TIDAK dimasukkan. Dianalisis: untuk aktor yayasan-scope, SEMUA baris lembaga-spesifik yang mereka lihat (dijamin filter query `index()`) SUDAH PASTI bisa mereka kelola — tidak ada skenario "lihat tapi tidak bisa kelola karena lembaga lain" yang nyata terjadi di data yang benar-benar tampil. Badge "milik saya vs bukan" tidak akan pernah punya kasus "bukan" untuk ditampilkan.
2. **Mengubah `index()` supaya IKUT menyempit saat yayasan-scope switch lembaga** (menyamakan perilaku dengan Kelas/TahunAjaran/MataPelajaran) — DIPERTIMBANGKAN, TIDAK dimasukkan karena ini PERUBAHAN PERILAKU FUNGSIONAL (bukan cuma UI/wording), berpotensi mengejutkan alur kerja existing (admin yayasan yang terbiasa index ini SELALU agregat penuh untuk membandingkan assignment lintas lembaga sekaligus). Kalau user MENGHENDAKI perilaku ini diubah, itu keputusan produk terpisah yang perlu didiskusikan eksplisit — spec ini cukup MENJELASKAN perilaku existing (Item B.3), bukan mengubahnya.
3. **`resync.blade.php`/`ResyncKurikulumFaseController`** — TIDAK diaudit di spec ini (fitur terpisah, cuma di-link dari index). Backlog audit terpisah kalau diperlukan.
4. **Konsistensi nama variabel `isPlatformOrYayasan`** dipakai ulang (nama sama, arti beda) di `FaseDefaultMappingController`/`ResyncKurikulumFaseController` — di luar scope, tidak disentuh.

---

## Ringkasan Test yang Wajib Ditambahkan/Diperbarui

| Item | Test |
|---|---|
| A.1 | Feature test BARU — `actingAsPlatformScopeKurikulumManager()` (helper SUDAH ADA di test file), GET `admin.kurikulum-assignment.edit` untuk assignment APA PUN (global maupun lembaga-spesifik), assert `assertOk()` (BUKAN 500). Ini test regresi paling penting di seluruh spec — sebelumnya TIDAK ADA test yang mengeksekusi jalur ini sama sekali. |
| A.2 | Feature test BARU — yayasan-scope, assert `assertViewHas('assignmentList', ...)` baris global punya `canManage === false`, baris lembaga miliknya sendiri (di yayasan yang sama) punya `canManage === true`. Feature test BARU kedua — assert HTML index TIDAK mengandung link "Edit"/form "Hapus" yang mengarah ke assignment global untuk aktor yayasan-scope (assertDontSee pada `route('admin.kurikulum-assignment.edit', $assignmentGlobal)` sebagai href). |
| A.3 | **UBAH** test existing baris ±195 (`assertStatus(422)` → `assertRedirect()` + `assertSessionHasErrors('lembaga_id')`) |
| B.1/B.2 | Feature test BARU — yayasan-scope tanpa lembaga aktif, GET `admin.kurikulum-assignment.create`, assert redirect ke index + `assertSessionHasErrors('lembaga_id')`. Feature test BARU kedua — yayasan-scope DENGAN lembaga aktif, assert `assertSee($lembaga->nama)` di halaman create. |
| B.3 | Feature test BARU — yayasan-scope, assert index `assertSee` catatan statis; lembaga-scope, assert `assertDontSee` (karena `isYayasan` false). |

Regresi wajib dijalankan PENUH (bukan cuma filter): `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` (SEMUA test existing, termasuk 6 test scope-boundary yang sudah ada — tidak satu pun boleh berubah perilakunya), `tests/Feature/Admin/KurikulumAssignmentDestroyGuardTest.php`, `tests/Unit/Models/KurikulumAssignmentTest.php`, `tests/Unit/Services/KurikulumAssignmentResolverTest.php` (TIDAK disentuh, harus tetap hijau tanpa perubahan sama sekali — bukti blast-radius benar-benar terkurung).
