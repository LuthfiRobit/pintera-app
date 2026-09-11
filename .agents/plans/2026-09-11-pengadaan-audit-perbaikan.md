# Perbaikan Audit Menyeluruh Modul Pengadaan & LPJ Sarpras Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup 14 temuan audit menyeluruh (2 IDOR Critical, 3 bug UI Critical, 1 High, 4 Medium, 4 Low) di modul Pengadaan & LPJ Sarpras — mencakup semua role (pengaju lembaga, approver kepsek/bendahara yayasan, auditor LPJ).

**Architecture:** 12 task independen-sebisa-mungkin murni di domain Pengadaan (`app/Domains/Pengadaan/*`, `app/Http/Controllers/{Lembaga,Yayasan}/Pengadaan/*`, view+komponen terkait, plus 1 file shared `app/Domains/Shared/Context/TenantContext.php` dan 1 komponen shared `resources/views/components/badge.blade.php`). TIDAK menyentuh `app/Domains/Workflow/*`.

**Tech Stack:** Laravel 12 (PHP 8.3), Pest + PHPUnit (test file Pengadaan pakai PHPUnit class-based `Tests\TestCase`, BUKAN Pest function-style — ikuti konvensi file yang sudah ada), Blade, Alpine.js.

## Global Constraints

- §2.1 dan §2.2 murni menambah guard yang seharusnya sudah ada sejak awal — TIDAK ADA perilaku sah yang berubah, hanya menutup akses yang seharusnya tidak pernah diizinkan.
- §2.2 (fail-closed `TenantContext::activeYayasanId()`) BERISIKO OPERASIONAL TINGGI — bisa mengunci akses akun existing yang datanya bermasalah (`yayasan_id` null padahal seharusnya yayasan-scope). Ini perubahan perilaku yang DISENGAJA (menutup celah), BUKAN regresi untuk dibatalkan. Task 2 WAJIB menjalankan query dampak dan MELAPORKAN hasilnya (bukan blocker implementasi).
- §2.7 mengubah validasi file LPJ jadi kondisional — submit LPJ PERTAMA KALI (belum ada `$proposal->lpj`) TETAP wajib upload semua foto. HANYA resubmit setelah `RevisionRequired` yang jadi lebih longgar. JANGAN sampai longgar juga untuk submit pertama kali.
- §2.4 (tambah tone ke `<x-badge>`) HARUS murni ADDITIVE ke array `$tones` — JANGAN ubah/hapus 6 tone existing (`brass/green/red/amber/blue/slate`), dipakai domain lain di luar Pengadaan.
- §2.10 JANGAN menambah method `destroy()` baru — solusi HARUS `->except(['destroy'])` di route resource.
- 3 item SENGAJA TIDAK masuk scope plan ini: validasi nominal `RecordDisbursementAction` (butuh keputusan produk), preview thumbnail LPJ (nice-to-have), refactor `lpj/create.blade.php` inline `<script>` ke `resources/js/*.js` (utang teknis terpisah).
- JANGAN sentuh `app/Domains/Workflow/*` sama sekali.

---

## Task 1: Perbaikan LPJ IDOR (Guard Kepemilikan Lembaga)

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Pengadaan/LpjPengadaanController.php:28-76`
- Test: `tests/Feature/Pengadaan/CrossTenantIsolationTest.php` (tambah 1 method di akhir class, sebelum `}` penutup)

**Interfaces:**
- Consumes: `TenantContext::activeLembagaId()` (sudah ada, dipakai sama seperti `stagingInventory()`/`convertInventory()` di file yang sama).
- Produces: tidak ada interface baru.

- [ ] **Step 1: Tulis test yang gagal — user lembaga lain tidak bisa akses `lpj.create`/`lpj.store`**

Tambahkan method baru di `tests/Feature/Pengadaan/CrossTenantIsolationTest.php`, SEBELUM baris `}` penutup class (setelah method `test_kartu_ringkasan_stats_pengajuan_pengadaan_menghitung_agregat_semua_lembaga_saat_yayasan_mode_semua_lembaga`):

```php

    public function test_lembaga_lain_tidak_bisa_akses_lpj_create_dan_store(): void
    {
        $this->proposalA2->update(['status' => StatusPengajuan::Disbursed]);

        $adminB1 = User::factory()->create(['lembaga_id' => $this->lembagaB1->id]);
        $adminB1->givePermissionTo(['pengadaan.lpj.submit']);

        $this->actingAs($adminB1)
            ->get(route('admin.pengadaan.lpj.create', $this->proposalA2))
            ->assertNotFound();

        $this->actingAs($adminB1)
            ->post(route('admin.pengadaan.lpj.store', $this->proposalA2), [])
            ->assertNotFound();

        $this->assertDatabaseMissing('lpj_pengadaan', [
            'pengajuan_pengadaan_id' => $this->proposalA2->id,
        ]);
    }
```

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Feature/Pengadaan/CrossTenantIsolationTest.php --filter="test_lembaga_lain_tidak_bisa_akses_lpj_create_dan_store"`
Expected: FAIL pada assertion `->assertNotFound()` pertama — `get(route('admin.pengadaan.lpj.create', ...))` mengembalikan 200 (halaman form ter-render), bukan 404, karena belum ada guard kepemilikan.

- [ ] **Step 3: Tambah guard di `create()` dan `store()`**

Di `app/Http/Controllers/Lembaga/Pengadaan/LpjPengadaanController.php`, method `create()` saat ini:

```php
    public function create(PengajuanPengadaan $proposal): View
    {
        $this->authorize('pengadaan.lpj.submit');

        if ($proposal->status !== StatusPengajuan::Disbursed) {
            abort(403, 'LPJ hanya dapat diisi untuk proposal yang telah dicairkan dananya.');
        }

        $proposal->load(['items' => fn ($q) => $q->where('status_item', StatusItemPengajuan::Approved)->with(['kategori', 'ruangan']), 'lpj.items']);

        return view('portals.lembaga.pengadaan.lpj.create', compact('proposal'));
    }
```

Ubah jadi (tambah `abort_unless` SETELAH `authorize()`, SEBELUM cek status):

```php
    public function create(PengajuanPengadaan $proposal): View
    {
        $this->authorize('pengadaan.lpj.submit');
        abort_unless($proposal->lembaga_id === $this->tenantContext->activeLembagaId(), 404);

        if ($proposal->status !== StatusPengajuan::Disbursed) {
            abort(403, 'LPJ hanya dapat diisi untuk proposal yang telah dicairkan dananya.');
        }

        $proposal->load(['items' => fn ($q) => $q->where('status_item', StatusItemPengajuan::Approved)->with(['kategori', 'ruangan']), 'lpj.items']);

        return view('portals.lembaga.pengadaan.lpj.create', compact('proposal'));
    }
```

Method `store()` saat ini dimulai:

```php
    public function store(StoreLpjRequest $request, PengajuanPengadaan $proposal): RedirectResponse
    {
        $items = $request->validated()['items'];
```

Ubah jadi (tambah `abort_unless` di baris pertama method):

```php
    public function store(StoreLpjRequest $request, PengajuanPengadaan $proposal): RedirectResponse
    {
        abort_unless($proposal->lembaga_id === $this->tenantContext->activeLembagaId(), 404);

        $items = $request->validated()['items'];
```

Sisa kedua method TIDAK berubah dari kode existing.

**PENTING — Task 7 akan mengubah lagi badan method `store()` sepenuhnya (menambah logika `$existingItems` dsb).** Task 1 HARUS selesai dan di-commit LEBIH DULU sebelum Task 7 dimulai, supaya baris `abort_unless` ini sudah ada sebagai baseline yang tidak boleh hilang saat Task 7 menulis ulang badan method.

- [ ] **Step 4: Jalankan test untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Feature/Pengadaan/CrossTenantIsolationTest.php --compact`
Expected: PASS — semua test di file ini (termasuk yang baru) hijau.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Lembaga/Pengadaan/LpjPengadaanController.php tests/Feature/Pengadaan/CrossTenantIsolationTest.php
git commit -m "fix(pengadaan): cegah IDOR pada create/store LPJ lintas lembaga

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: `TenantContext::activeYayasanId()` Fail-Closed + Bersihkan 4 Controller

**Files:**
- Modify: `app/Domains/Shared/Context/TenantContext.php:44-70`
- Modify: `app/Http/Controllers/Yayasan/Pengadaan/AuditLpjController.php:26`
- Modify: `app/Http/Controllers/Yayasan/Pengadaan/DisbursementPengadaanController.php:27`
- Modify: `app/Http/Controllers/Yayasan/Pengadaan/ApprovalPengadaanController.php:28`
- Modify: `app/Http/Controllers/Yayasan/Sarpras/RekapAsetGlobalController.php:26`
- Test: `tests/Feature/Pengadaan/CrossTenantIsolationTest.php` (tambah 1 method)

**Interfaces:**
- Consumes: tidak ada dari task lain.
- Produces: `TenantContext::activeYayasanId(): ?int` — signature TIDAK berubah, hanya nilai kembalian untuk 1 kondisi (fallback) yang berubah dari `int` (yayasan pertama) jadi `null`.

- [ ] **Step 1: Tulis test yang gagal — user tanpa yayasan_id ditolak (bukan melihat data yayasan lain)**

Tambahkan method baru di `tests/Feature/Pengadaan/CrossTenantIsolationTest.php`, setelah method yang ditambahkan Task 1:

```php

    public function test_user_yayasan_scope_tanpa_yayasan_id_ditolak_bukan_fallback_ke_yayasan_pertama(): void
    {
        $yayasanRole = Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $yayasanRole->givePermissionTo(['pengadaan.approval.yayasan', 'pengadaan.disbursement.manage', 'pengadaan.lpj.verify']);

        $userTanpaYayasan = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => null]);
        $userTanpaYayasan->assignRole($yayasanRole);

        $this->actingAs($userTanpaYayasan)
            ->get(route('admin.pengadaan.inbox.index'))
            ->assertForbidden();

        $this->actingAs($userTanpaYayasan)
            ->get(route('admin.pengadaan.disbursement.index'))
            ->assertForbidden();

        $this->actingAs($userTanpaYayasan)
            ->get(route('admin.pengadaan.audit-lpj.index'))
            ->assertForbidden();
    }
```

**Catatan**: `$this->yayasanA` (dibuat di `setUp()`) akan jadi `Yayasan::first()` di test ini (karena dibuat lebih dulu) — kalau bug BELUM diperbaiki, ketiga `assertForbidden()` akan gagal karena request malah SUKSES (200) menampilkan data `$this->yayasanA`, membuktikan fail-open-nya.

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Feature/Pengadaan/CrossTenantIsolationTest.php --filter="test_user_yayasan_scope_tanpa_yayasan_id_ditolak"`
Expected: FAIL — ketiga `assertForbidden()` gagal karena masing-masing endpoint mengembalikan 200, bukan 403.

- [ ] **Step 3: Ubah `TenantContext::activeYayasanId()` jadi fail-closed**

Di `app/Domains/Shared/Context/TenantContext.php`, method `activeYayasanId()` saat ini:

```php
    public function activeYayasanId(): ?int
    {
        $user = $this->user();

        if (! $user) {
            return null;
        }

        if ($user->yayasan_id) {
            return $user->yayasan_id;
        }

        if ($user->lembaga && $user->lembaga->yayasan_id) {
            return $user->lembaga->yayasan_id;
        }

        $activeLembagaId = $this->activeLembagaId();
        if ($activeLembagaId) {
            $lembaga = \App\Models\Lembaga::find($activeLembagaId);

            if ($lembaga?->yayasan_id) {
                return $lembaga->yayasan_id;
            }
        }

        return \App\Models\Yayasan::first()?->id;
    }
```

Ubah baris terakhir (`return \App\Models\Yayasan::first()?->id;`) jadi `return null;`:

```php
    public function activeYayasanId(): ?int
    {
        $user = $this->user();

        if (! $user) {
            return null;
        }

        if ($user->yayasan_id) {
            return $user->yayasan_id;
        }

        if ($user->lembaga && $user->lembaga->yayasan_id) {
            return $user->lembaga->yayasan_id;
        }

        $activeLembagaId = $this->activeLembagaId();
        if ($activeLembagaId) {
            $lembaga = \App\Models\Lembaga::find($activeLembagaId);

            if ($lembaga?->yayasan_id) {
                return $lembaga->yayasan_id;
            }
        }

        return null;
    }
```

- [ ] **Step 4: Bersihkan 4 controller yang punya fallback duplikat**

Di `app/Http/Controllers/Yayasan/Pengadaan/AuditLpjController.php`, method `index()` baris 26 saat ini:

```php
        $yayasanId = $this->tenantContext->activeYayasanId() ?? \App\Models\Yayasan::first()?->id;
```

Ubah jadi:

```php
        $yayasanId = $this->tenantContext->activeYayasanId();
        abort_if($yayasanId === null, 403, 'Akun Anda belum terhubung ke yayasan manapun. Hubungi Super Admin Platform untuk memperbaiki data akun Anda.');
```

Terapkan perubahan BARIS IDENTIK (baris `$yayasanId = $this->tenantContext->activeYayasanId() ?? \App\Models\Yayasan::first()?->id;` diganti 2 baris di atas) di 3 file berikut, masing-masing pada baris yang disebutkan:
- `app/Http/Controllers/Yayasan/Pengadaan/DisbursementPengadaanController.php:27`
- `app/Http/Controllers/Yayasan/Pengadaan/ApprovalPengadaanController.php:28`
- `app/Http/Controllers/Yayasan/Sarpras/RekapAsetGlobalController.php:26`

Untuk KEEMPAT file, pastikan `use App\Models\Yayasan;` yang mungkin hanya dipakai untuk baris fallback ini DIPERIKSA — kalau `Yayasan` tidak dipakai lagi di tempat lain pada file yang sama, HAPUS import yang tidak terpakai (tapi kalau masih dipakai bagian lain file, biarkan).

- [ ] **Step 5: Jalankan test untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Feature/Pengadaan/CrossTenantIsolationTest.php --compact`
Expected: PASS — semua test di file ini hijau, termasuk yang ditambahkan Task 1 dan Task 2.

- [ ] **Step 6: Jalankan query dampak operasional dan CATAT hasilnya di commit message / laporan**

Run: `php artisan tinker --execute 'echo App\Models\User::whereNull("yayasan_id")->whereHas("roles", fn($q) => $q->where("scope_level", "yayasan"))->count();'`

Ini BUKAN blocker — jalankan di database dev/lokal, catat angkanya (0 atau lebih). Kalau angkanya > 0, itu informasi penting untuk dilaporkan ke user (akun-akun ini akan kehilangan akses ke fitur Pengadaan/Rekap Aset Yayasan setelah fix ini, dan perlu diperbaiki `yayasan_id`-nya secara manual oleh Super Admin Platform). Sertakan angka ini di pesan commit Step 7 sebagai catatan.

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Shared/Context/TenantContext.php app/Http/Controllers/Yayasan/Pengadaan/AuditLpjController.php app/Http/Controllers/Yayasan/Pengadaan/DisbursementPengadaanController.php app/Http/Controllers/Yayasan/Pengadaan/ApprovalPengadaanController.php app/Http/Controllers/Yayasan/Sarpras/RekapAsetGlobalController.php tests/Feature/Pengadaan/CrossTenantIsolationTest.php
git commit -m "fix(shared): fail-closed TenantContext::activeYayasanId(), hapus fallback fail-open lintas 4 controller

Dampak operasional: [ISI ANGKA DARI STEP 6 DI SINI] akun yayasan-scope tanpa yayasan_id ditemukan di database dev.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 3: Perbaikan Filter AJAX Rusak (Proposal & Inbox)

**Files:**
- Modify: `resources/views/portals/lembaga/pengadaan/proposal/index.blade.php:149`
- Modify: `resources/views/portals/yayasan/pengadaan/inbox/index.blade.php:90`

**Interfaces:**
- Consumes: `dataTableFilter()` Alpine component (`resources/js/data-table-filter.js`, TIDAK diubah task ini) — komponen ini membaca `this.$refs.tableContainer`.
- Produces: tidak ada interface baru.

**PENTING — Task 11 akan mengubah lagi file yang SAMA (`proposal/index.blade.php`, `inbox/index.blade.php`) untuk perbaikan wording/`<x-select>`/a11y. Task 3 HARUS selesai dan di-commit LEBIH DULU sebelum Task 11 dimulai.**

- [ ] **Step 1: Ubah `proposal/index.blade.php`**

Baris 149 saat ini:

```blade
            <div id="wadah-daftar-tabel" class="relative">
```

Ubah jadi:

```blade
            <div x-ref="tableContainer" class="relative">
```

- [ ] **Step 2: Ubah `inbox/index.blade.php`**

Baris 90 saat ini:

```blade
            <div id="wadah-daftar-tabel" class="relative">
```

Ubah jadi:

```blade
            <div x-ref="tableContainer" class="relative">
```

- [ ] **Step 3: Verifikasi manual — tidak ada automated test untuk perilaku JS Alpine murni**

Jalankan `npm run build`. Login sebagai pengaju lembaga, buka `/admin/pengadaan/proposal`, ketik kata kunci di kolom "Cari Pengajuan" — konfirmasi tabel ter-refresh TANPA toast "Gagal memuat data.". Login sebagai approver yayasan, buka `/admin/pengadaan/inbox`, ketik di kolom pencarian — konfirmasi hal yang sama.

- [ ] **Step 4: Jalankan test Feature existing yang menyentuh 2 halaman ini untuk memastikan tidak ada regresi**

Run: `vendor/bin/pest tests/Feature/Pengadaan/PengadaanControllerTest.php tests/Feature/Pengadaan/PengadaanViewTest.php --compact`
Expected: semua PASS (perubahan murni atribut HTML, tidak ada test yang seharusnya terpengaruh — tapi WAJIB dijalankan untuk konfirmasi).

- [ ] **Step 5: Commit**

```bash
git add resources/views/portals/lembaga/pengadaan/proposal/index.blade.php resources/views/portals/yayasan/pengadaan/inbox/index.blade.php
git commit -m "fix(pengadaan): perbaiki filter AJAX rusak di Daftar Proposal dan Inbox Approval

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 4: Tambah Tone Badge yang Hilang (`<x-badge>`)

**Files:**
- Modify: `resources/views/components/badge.blade.php:4-11`

**Interfaces:**
- Consumes: tidak ada.
- Produces: `<x-badge tone="purple|rose|indigo|...">` sekarang mengenali 3 tone tambahan. Signature komponen (`@props(['tone' => 'slate'])`) TIDAK berubah.

- [ ] **Step 1: Ubah array `$tones`**

Isi file saat ini:

```blade
@props(['tone' => 'slate'])

@php
    $tones = [
        'brass' => 'bg-brand-50 text-brand-600',
        'green' => 'bg-success-50 text-success-700',
        'red' => 'bg-error-50 text-error-700',
        'amber' => 'bg-warning-50 text-warning-700',
        'blue' => 'bg-blue-100 text-blue-700',
        'slate' => 'bg-gray-100 text-gray-600',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ' . ($tones[$tone] ?? $tones['slate'])]) }}>
    {{ $slot }}
</span>
```

Ubah jadi (TAMBAH 3 baris baru ke array, JANGAN ubah/hapus 6 baris existing):

```blade
@props(['tone' => 'slate'])

@php
    $tones = [
        'brass' => 'bg-brand-50 text-brand-600',
        'green' => 'bg-success-50 text-success-700',
        'red' => 'bg-error-50 text-error-700',
        'amber' => 'bg-warning-50 text-warning-700',
        'blue' => 'bg-blue-100 text-blue-700',
        'slate' => 'bg-gray-100 text-gray-600',
        'purple' => 'bg-purple-100 text-purple-700',
        'rose' => 'bg-rose-100 text-rose-700',
        'indigo' => 'bg-indigo-100 text-indigo-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ' . ($tones[$tone] ?? $tones['slate'])]) }}>
    {{ $slot }}
</span>
```

- [ ] **Step 2: Verifikasi manual — tidak ada automated test untuk warna badge (murni visual)**

Buka `/admin/pengadaan/proposal`, cari/buat proposal berstatus `Ditolak` (Rejected), `Sedang Direview` (InReview), atau `Dana Cair` (Disbursed) — konfirmasi badge-nya TIDAK abu-abu (ungu untuk InReview, merah muda untuk Rejected, indigo untuk Disbursed).

- [ ] **Step 3: Jalankan test Feature existing yang menyentuh badge status Pengadaan untuk memastikan tidak ada regresi**

Run: `vendor/bin/pest tests/Feature/Pengadaan --compact`
Expected: semua PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/views/components/badge.blade.php
git commit -m "fix(ui): tambah tone purple/rose/indigo yang hilang di komponen x-badge

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 5: Perbaikan Value Urgensi Salah di Form Edit Proposal

**Files:**
- Modify: `resources/views/portals/lembaga/pengadaan/proposal/edit.blade.php:88-92`
- Test: `tests/Feature/Pengadaan/PengadaanValidationTest.php` (tambah 1 method di akhir class — kalau file/class tidak ditemukan pada path ini, cari nama file yang benar dengan `Glob` pattern `**/Pengadaan*ValidationTest.php` sebelum melanjutkan, JANGAN buat file baru tanpa mengecek dulu)

**Interfaces:**
- Consumes: `App\Domains\Pengadaan\Enums\TingkatUrgensi` (enum existing, cases: `Biasa='biasa'`, `Mendesak='mendesak'`, `Kritis='kritis'`).
- Produces: tidak ada interface baru.

- [ ] **Step 1: Tulis test yang gagal — edit proposal Kritis, opsi kritis harus ter-render dengan selected**

Tambahkan method di `tests/Feature/Pengadaan/PengadaanValidationTest.php` (baca file ini dulu untuk memahami pola `setUp()`-nya — kemungkinan besar mirip `LpjValidationTest.php` yang sudah dibaca sebelumnya di sesi audit, pakai `PermissionSeeder`+`RoleSeeder`, buat Yayasan/Lembaga/User manual). Tambahkan di akhir class:

```php

    public function test_form_edit_menampilkan_opsi_kritis_dengan_value_yang_benar_untuk_proposal_urgensi_kritis(): void
    {
        $proposal = PengajuanPengadaan::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'nomor_pengajuan' => 'PR/2026/09/TEST-URGENSI',
            'judul_pengajuan' => 'Perbaikan Atap Bocor',
            'tingkat_urgensi' => TingkatUrgensi::Kritis,
            'total_estimasi' => 1000000,
            'status' => StatusPengajuan::RevisionRequired,
            'created_by_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.pengadaan.proposal.edit', $proposal));

        $response->assertOk();
        $response->assertSee('value="kritis" selected', false);
        $response->assertDontSee('value="darurat"', false);
    }
```

**Catatan**: sesuaikan nama variabel (`$this->yayasan`, `$this->lembaga`, `$this->user`) dan import (`use App\Domains\Pengadaan\Enums\TingkatUrgensi;`, `use App\Domains\Pengadaan\Enums\StatusPengajuan;`, `use App\Domains\Pengadaan\Models\PengajuanPengadaan;`) dengan yang SUDAH ADA di `setUp()` file target — JANGAN duplikasi setup yang sudah ada, JANGAN asumsikan nama variabel tanpa membaca file dulu.

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Feature/Pengadaan/PengadaanValidationTest.php --filter="test_form_edit_menampilkan_opsi_kritis"`
Expected: FAIL pada `assertSee('value="kritis" selected', false)` — value ini tidak pernah muncul karena kode existing hanya punya `value="darurat"` (yang tidak pernah dapat atribut `selected` karena tidak match `tingkat_urgensi->value` yang sebenarnya `'kritis'`).

- [ ] **Step 3: Perbaiki value di `edit.blade.php`**

Baris 88-92 saat ini:

```blade
                            <option value="biasa" {{ old('tingkat_urgensi', $proposal->tingkat_urgensi->value) == 'biasa' ? 'selected' : '' }}>Biasa / Rutin</option>
                            <option value="mendesak" {{ old('tingkat_urgensi', $proposal->tingkat_urgensi->value) == 'mendesak' ? 'selected' : '' }}>Mendesak / Prioritas</option>
                            <option value="darurat" {{ old('tingkat_urgensi', $proposal->tingkat_urgensi->value) == 'darurat' ? 'selected' : '' }}>Darurat (Segera)</option>
```

Ubah jadi:

```blade
                            <option value="biasa" {{ old('tingkat_urgensi', $proposal->tingkat_urgensi->value) == 'biasa' ? 'selected' : '' }}>Biasa / Rutin</option>
                            <option value="mendesak" {{ old('tingkat_urgensi', $proposal->tingkat_urgensi->value) == 'mendesak' ? 'selected' : '' }}>Mendesak</option>
                            <option value="kritis" {{ old('tingkat_urgensi', $proposal->tingkat_urgensi->value) == 'kritis' ? 'selected' : '' }}>Kritis / Darurat</option>
```

- [ ] **Step 4: Jalankan test untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Feature/Pengadaan/PengadaanValidationTest.php --compact`
Expected: PASS — semua test di file ini hijau.

- [ ] **Step 5: Commit**

```bash
git add resources/views/portals/lembaga/pengadaan/proposal/edit.blade.php tests/Feature/Pengadaan/PengadaanValidationTest.php
git commit -m "fix(pengadaan): perbaiki value urgensi 'darurat' yang salah jadi 'kritis' di form edit proposal

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 6: Perbaikan Pesan Salah di Audit LPJ untuk Status RevisionRequired

**Files:**
- Modify: `resources/views/portals/yayasan/pengadaan/audit-lpj/show.blade.php:115-142`
- Test: `tests/Feature/Pengadaan/PengadaanControllerTest.php` (tambah 1 method — cek dulu isi file untuk pola setup yang benar, kemungkinan mirip `LpjValidationTest.php`/`CrossTenantIsolationTest.php`)

**Interfaces:**
- Consumes: `App\Domains\Pengadaan\Enums\StatusLpj` (cases: `Draft`, `Submitted`, `RevisionRequired`, `Verified`).
- Produces: tidak ada interface baru.

- [ ] **Step 1: Tulis test yang gagal — LPJ RevisionRequired tidak boleh menampilkan pesan "sudah diverifikasi"**

Tambahkan di `tests/Feature/Pengadaan/PengadaanControllerTest.php` (sesuaikan setup dengan pola file, gunakan factory Yayasan/Lembaga/User/PengajuanPengadaan/LpjPengadaan yang konsisten dengan test lain di file yang sama):

```php

    public function test_audit_lpj_show_tidak_menampilkan_pesan_sudah_diverifikasi_untuk_status_revision_required(): void
    {
        $yayasan = Yayasan::create(['nama' => 'Yayasan Audit Test']);
        $lembaga = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'Sekolah Audit Test', 'npsn' => '99998888', 'status_aktif' => true]);
        $bendahara = User::factory()->create(['yayasan_id' => $yayasan->id]);
        $role = Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $role->givePermissionTo(['pengadaan.lpj.verify']);
        $bendahara->assignRole($role);

        $proposal = PengajuanPengadaan::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'nomor_pengajuan' => 'PR/2026/09/AUDIT-TEST',
            'judul_pengajuan' => 'Pengadaan Test Audit',
            'tingkat_urgensi' => 'biasa',
            'total_estimasi' => 1000000,
            'nominal_pencairan' => 1000000,
            'status' => StatusPengajuan::Disbursed,
        ]);

        $lpj = LpjPengadaan::create([
            'pengajuan_pengadaan_id' => $proposal->id,
            'status_lpj' => StatusLpj::RevisionRequired,
            'catatan_verifikasi' => 'Nota barang ke-2 tidak terbaca, mohon unggah ulang.',
            'verified_by_user_id' => $bendahara->id,
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($bendahara)->get(route('admin.pengadaan.audit-lpj.show', $lpj));

        $response->assertOk();
        $response->assertDontSee('LPJ ini telah selesai diverifikasi');
        $response->assertSee('Nota barang ke-2 tidak terbaca, mohon unggah ulang.');
    }
```

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Feature/Pengadaan/PengadaanControllerTest.php --filter="test_audit_lpj_show_tidak_menampilkan_pesan_sudah_diverifikasi"`
Expected: FAIL — `assertDontSee('LPJ ini telah selesai diverifikasi')` gagal karena teks itu MUNCUL (kode existing jatuh ke `@else` untuk status apapun selain `Submitted`, termasuk `RevisionRequired`).

- [ ] **Step 3: Pecah jadi 3 cabang eksplisit**

Baris 115-142 saat ini:

```blade
        {{-- Verification Decision Form --}}
        @if ($lpj->status_lpj === \App\Domains\Pengadaan\Enums\StatusLpj::Submitted)
            <form action="{{ route('admin.pengadaan.audit-lpj.verify', $lpj) }}" method="POST" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                @csrf

                <h2 class="font-display text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">Keputusan Verifikasi Audit Yayasan</h2>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan Audit / Evaluasi</label>
                    <textarea name="catatan_verifikasi" rows="2" placeholder="Catatan keabsahan nota atau instruksi bila ada kekurangan bukti..." class="w-full rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500"></textarea>
                    <x-input-error :messages="$errors->get('catatan_verifikasi')" class="mt-1" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <x-secondary-button type="submit" name="is_approved" value="0">
                        <x-icon name="assignment_late" class="h-4 w-4 mr-1 text-amber-600" /> Minta Perbaikan LPJ
                    </x-secondary-button>

                    <x-primary-button type="submit" name="is_approved" value="1">
                        <x-icon name="check_circle" class="h-4 w-4 mr-1" /> Verifikasi & Setujui LPJ
                    </x-primary-button>
                </div>
            </form>
        @else
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 text-xs text-emerald-900 flex items-center gap-2">
                <x-icon name="verified" class="h-5 w-5 text-emerald-600" />
                <span>LPJ ini telah selesai diverifikasi oleh <b>{{ $lpj->verifiedBy->name ?? 'Auditor Yayasan' }}</b> pada {{ $lpj->verified_at?->translatedFormat('d F Y H:i') }}.</span>
            </div>
        @endif
```

Ubah jadi:

```blade
        {{-- Verification Decision Form --}}
        @if ($lpj->status_lpj === \App\Domains\Pengadaan\Enums\StatusLpj::Submitted)
            <form action="{{ route('admin.pengadaan.audit-lpj.verify', $lpj) }}" method="POST" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                @csrf

                <h2 class="font-display text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">Keputusan Verifikasi Audit Yayasan</h2>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan Audit / Evaluasi</label>
                    <textarea name="catatan_verifikasi" rows="2" placeholder="Catatan keabsahan nota atau instruksi bila ada kekurangan bukti..." class="w-full rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500"></textarea>
                    <x-input-error :messages="$errors->get('catatan_verifikasi')" class="mt-1" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <x-secondary-button type="submit" name="is_approved" value="0">
                        <x-icon name="assignment_late" class="h-4 w-4 mr-1 text-amber-600" /> Minta Perbaikan LPJ
                    </x-secondary-button>

                    <x-primary-button type="submit" name="is_approved" value="1">
                        <x-icon name="check_circle" class="h-4 w-4 mr-1" /> Verifikasi & Setujui LPJ
                    </x-primary-button>
                </div>
            </form>
        @elseif ($lpj->status_lpj === \App\Domains\Pengadaan\Enums\StatusLpj::RevisionRequired)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-xs text-amber-900 flex items-start gap-2">
                <x-icon name="assignment_late" class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
                <div class="space-y-1">
                    <p class="font-bold">LPJ ini diminta perbaikan, menunggu unggah ulang dari sekolah.</p>
                    @if ($lpj->catatan_verifikasi)
                        <p>Catatan Anda: <b>{{ $lpj->catatan_verifikasi }}</b></p>
                    @endif
                    <p class="text-amber-700">Diproses oleh <b>{{ $lpj->verifiedBy->name ?? 'Auditor Yayasan' }}</b> pada {{ $lpj->verified_at?->translatedFormat('d F Y H:i') }}.</p>
                </div>
            </div>
        @else
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 text-xs text-emerald-900 flex items-center gap-2">
                <x-icon name="verified" class="h-5 w-5 text-emerald-600" />
                <span>LPJ ini telah selesai diverifikasi oleh <b>{{ $lpj->verifiedBy->name ?? 'Auditor Yayasan' }}</b> pada {{ $lpj->verified_at?->translatedFormat('d F Y H:i') }}.</span>
            </div>
        @endif
```

- [ ] **Step 4: Jalankan test untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Feature/Pengadaan/PengadaanControllerTest.php --compact`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/portals/yayasan/pengadaan/audit-lpj/show.blade.php tests/Feature/Pengadaan/PengadaanControllerTest.php
git commit -m "fix(pengadaan): perbaiki pesan salah 'sudah diverifikasi' untuk LPJ berstatus RevisionRequired

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 7: LPJ RevisionRequired — Tampilkan Catatan & Izinkan Resubmit Parsial Tanpa Upload Ulang Semua File

**Files:**
- Modify: `resources/views/portals/lembaga/pengadaan/proposal/show.blade.php:53-98`
- Modify: `app/Http/Requests/Pengadaan/StoreLpjRequest.php`
- Modify: `app/Http/Controllers/Lembaga/Pengadaan/LpjPengadaanController.php` (method `create()` eager-load, method `store()` badan penuh)
- Modify: `resources/views/portals/lembaga/pengadaan/lpj/create.blade.php`
- Test: `tests/Feature/Pengadaan/LpjValidationTest.php` (tambah 1 method paling penting)

**Interfaces:**
- Consumes: `LpjPengadaanController::store()` dari Task 1 (SUDAH punya `abort_unless` di baris pertama — Task 7 MEMPERTAHANKAN baris itu, menambah logika baru SETELAHNYA, TIDAK menghapusnya).
- Produces: tidak ada interface baru untuk task lain.

**PRASYARAT: Task 1 WAJIB sudah selesai & di-commit sebelum task ini dimulai** (Task 1 menambah `abort_unless` di baris pertama `store()` — task ini menulis ulang SISA badan method, guard itu harus tetap ada di baris pertama).

- [ ] **Step 1: Tulis test yang gagal — resubmit LPJ setelah RevisionRequired tanpa upload ulang item yang tidak berubah harus sukses & tidak menghilangkan file lama**

Tambahkan di `tests/Feature/Pengadaan/LpjValidationTest.php`, di akhir class:

```php

    public function test_resubmit_lpj_setelah_revision_required_tidak_wajib_upload_ulang_item_yang_tidak_diubah(): void
    {
        $item2 = PengajuanPengadaanItem::create([
            'pengajuan_pengadaan_id' => $this->proposal->id,
            'kategori_aset_id' => $this->kategori->id,
            'target_ruangan_id' => $this->ruangan->id,
            'nama_barang' => 'Layar Proyektor',
            'qty' => 1,
            'satuan' => 'unit',
            'estimasi_harga_satuan' => 500000,
            'total_estimasi' => 500000,
            'tipe_pencatatan' => \App\Domains\Sarpras\Enums\TipePencatatanAset::Unit,
            'status_item' => StatusItemPengajuan::Approved,
        ]);

        // Submit pertama: kedua item lengkap dengan file.
        $this->actingAs($this->user)->post(route('admin.pengadaan.lpj.store', $this->proposal), [
            'items' => [
                [
                    'pengajuan_item_id' => $this->item->id,
                    'harga_satuan_riil' => 5000000,
                    'total_riil' => 5000000,
                    'foto_nota' => UploadedFile::fake()->create('nota1.pdf', 200, 'application/pdf'),
                    'foto_fisik' => UploadedFile::fake()->image('barang1.jpg'),
                ],
                [
                    'pengajuan_item_id' => $item2->id,
                    'harga_satuan_riil' => 500000,
                    'total_riil' => 500000,
                    'foto_nota' => UploadedFile::fake()->create('nota2.pdf', 200, 'application/pdf'),
                    'foto_fisik' => UploadedFile::fake()->image('barang2.jpg'),
                ],
            ],
        ]);

        $lpj = \App\Domains\Pengadaan\Models\LpjPengadaan::where('pengajuan_pengadaan_id', $this->proposal->id)->firstOrFail();
        $itemLpjPertama = $lpj->items()->where('pengajuan_item_id', $this->item->id)->firstOrFail();
        $fotoNotaPathLama = $itemLpjPertama->foto_nota_path;
        $fotoFisikPathLama = $itemLpjPertama->foto_fisik_barang_path;
        $this->assertNotNull($fotoNotaPathLama);

        // Yayasan minta revisi.
        app(\App\Domains\Pengadaan\Actions\VerifyLpjAction::class)->execute($lpj, $this->user->id, false, 'Nota item 2 buram.');

        // Resubmit: HANYA item 2 yang diganti file-nya, item 1 TIDAK mengirim foto sama sekali.
        $response = $this->actingAs($this->user)->post(route('admin.pengadaan.lpj.store', $this->proposal), [
            'items' => [
                [
                    'pengajuan_item_id' => $this->item->id,
                    'harga_satuan_riil' => 5000000,
                    'total_riil' => 5000000,
                ],
                [
                    'pengajuan_item_id' => $item2->id,
                    'harga_satuan_riil' => 500000,
                    'total_riil' => 500000,
                    'foto_nota' => UploadedFile::fake()->create('nota2-ulang.pdf', 200, 'application/pdf'),
                    'foto_fisik' => UploadedFile::fake()->image('barang2-ulang.jpg'),
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.pengadaan.proposal.show', $this->proposal));
        $response->assertSessionDoesntHaveErrors();

        $itemLpjPertamaSetelahResubmit = $lpj->fresh()->items()->where('pengajuan_item_id', $this->item->id)->firstOrFail();
        $this->assertSame($fotoNotaPathLama, $itemLpjPertamaSetelahResubmit->foto_nota_path);
        $this->assertSame($fotoFisikPathLama, $itemLpjPertamaSetelahResubmit->foto_fisik_barang_path);
    }
```

Tambah `use App\Domains\Pengadaan\Actions\VerifyLpjAction;` ke bagian import file kalau belum ada.

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Feature/Pengadaan/LpjValidationTest.php --filter="test_resubmit_lpj_setelah_revision_required_tidak_wajib_upload_ulang"`
Expected: FAIL — `assertSessionDoesntHaveErrors()` gagal karena `items.0.foto_nota`/`items.0.foto_fisik` masih divalidasi `required` walau tidak dikirim.

- [ ] **Step 3: Ubah `StoreLpjRequest` — validasi kondisional**

Isi file saat ini:

```php
<?php

namespace App\Http\Requests\Pengadaan;

use App\Domains\Pengadaan\DataTransferObjects\LpjPengadaanData;
use Illuminate\Foundation\Http\FormRequest;

class StoreLpjRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pengadaan.lpj.submit') ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.pengajuan_item_id' => ['required', 'exists:pengajuan_pengadaan_item,id'],
            'items.*.harga_satuan_riil' => ['required', 'numeric', 'min:0'],
            'items.*.total_riil' => ['required', 'numeric', 'min:0'],
            'items.*.foto_nota' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'items.*.foto_fisik' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'bukti_kembali_sisa' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $proposal = $this->route('proposal');
            if (! $proposal) {
                return;
            }

            $nominalPencairan = (float) $proposal->nominal_pencairan;
            $items = $this->input('items', []);
            $totalRiil = 0;

            foreach ($items as $item) {
                $totalRiil += (float) ($item['total_riil'] ?? 0);
            }

            $sisaKas = $nominalPencairan - $totalRiil;
            if ($sisaKas > 0 && ! $this->hasFile('bukti_kembali_sisa')) {
                $validator->errors()->add(
                    'bukti_kembali_sisa',
                    'Terdapat sisa dana kas sebesar Rp ' . number_format($sisaKas, 0, ',', '.') . '. Bukti transfer/setoran pengembalian sisa kas ke Yayasan wajib dilampirkan.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Rincian realisasi belanja LPJ wajib diisi.',
            'items.*.harga_satuan_riil.required' => 'Harga satuan riil aktual wajib diisi.',
            'items.*.harga_satuan_riil.min' => 'Harga satuan riil tidak boleh negatif.',
            'items.*.total_riil.required' => 'Total belanja riil wajib diisi.',
            'items.*.foto_nota.required' => 'Scan nota/faktur pembelian untuk setiap item barang wajib diunggah.',
            'items.*.foto_nota.mimes' => 'Berkas scan nota fisik harus berformat JPG, JPEG, PNG, atau PDF.',
            'items.*.foto_nota.max' => 'Ukuran berkas scan nota maksimal 5MB.',
            'items.*.foto_fisik.required' => 'Foto fisik barang saat tiba di sekolah wajib diunggah untuk setiap item.',
            'items.*.foto_fisik.image' => 'Foto fisik barang harus berupa file gambar (JPG, JPEG, PNG).',
            'items.*.foto_fisik.mimes' => 'Foto fisik barang harus berformat JPG, JPEG, atau PNG.',
            'items.*.foto_fisik.max' => 'Ukuran foto fisik barang maksimal 5MB.',
            'bukti_kembali_sisa.mimes' => 'Bukti pengembalian sisa dana kas harus berformat JPG, JPEG, PNG, atau PDF.',
            'bukti_kembali_sisa.max' => 'Ukuran bukti pengembalian sisa kas maksimal 5MB.',
        ];
    }

    public function toDTO(?string $buktiKembaliPath = null, array $processedItems = []): LpjPengadaanData
    {
        return new LpjPengadaanData(
            items: ! empty($processedItems) ? $processedItems : $this->validated()['items'],
            buktiKembaliSisaDanaPath: $buktiKembaliPath,
        );
    }
}
```

Ganti TOTAL isinya jadi:

```php
<?php

namespace App\Http\Requests\Pengadaan;

use App\Domains\Pengadaan\DataTransferObjects\LpjPengadaanData;
use Illuminate\Foundation\Http\FormRequest;

class StoreLpjRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pengadaan.lpj.submit') ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.pengajuan_item_id' => ['required', 'exists:pengajuan_pengadaan_item,id'],
            'items.*.harga_satuan_riil' => ['required', 'numeric', 'min:0'],
            'items.*.total_riil' => ['required', 'numeric', 'min:0'],
            'items.*.foto_nota' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'items.*.foto_fisik' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'bukti_kembali_sisa' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $proposal = $this->route('proposal');
            if (! $proposal) {
                return;
            }

            $existingItems = $proposal->lpj?->items->keyBy('pengajuan_item_id') ?? collect();
            foreach ($this->input('items', []) as $idx => $item) {
                $existing = $existingItems->get($item['pengajuan_item_id'] ?? null);

                if (! $this->hasFile("items.{$idx}.foto_nota") && ! $existing?->foto_nota_path) {
                    $validator->errors()->add("items.{$idx}.foto_nota", 'Scan nota/faktur pembelian untuk setiap item barang wajib diunggah.');
                }
                if (! $this->hasFile("items.{$idx}.foto_fisik") && ! $existing?->foto_fisik_barang_path) {
                    $validator->errors()->add("items.{$idx}.foto_fisik", 'Foto fisik barang saat tiba di sekolah wajib diunggah untuk setiap item.');
                }
            }

            $nominalPencairan = (float) $proposal->nominal_pencairan;
            $items = $this->input('items', []);
            $totalRiil = 0;

            foreach ($items as $item) {
                $totalRiil += (float) ($item['total_riil'] ?? 0);
            }

            $sisaKas = $nominalPencairan - $totalRiil;
            if ($sisaKas > 0 && ! $this->hasFile('bukti_kembali_sisa')) {
                $validator->errors()->add(
                    'bukti_kembali_sisa',
                    'Terdapat sisa dana kas sebesar Rp ' . number_format($sisaKas, 0, ',', '.') . '. Bukti transfer/setoran pengembalian sisa kas ke Yayasan wajib dilampirkan.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Rincian realisasi belanja LPJ wajib diisi.',
            'items.*.harga_satuan_riil.required' => 'Harga satuan riil aktual wajib diisi.',
            'items.*.harga_satuan_riil.min' => 'Harga satuan riil tidak boleh negatif.',
            'items.*.total_riil.required' => 'Total belanja riil wajib diisi.',
            'items.*.foto_nota.mimes' => 'Berkas scan nota fisik harus berformat JPG, JPEG, PNG, atau PDF.',
            'items.*.foto_nota.max' => 'Ukuran berkas scan nota maksimal 5MB.',
            'items.*.foto_fisik.image' => 'Foto fisik barang harus berupa file gambar (JPG, JPEG, PNG).',
            'items.*.foto_fisik.mimes' => 'Foto fisik barang harus berformat JPG, JPEG, atau PNG.',
            'items.*.foto_fisik.max' => 'Ukuran foto fisik barang maksimal 5MB.',
            'bukti_kembali_sisa.mimes' => 'Bukti pengembalian sisa dana kas harus berformat JPG, JPEG, PNG, atau PDF.',
            'bukti_kembali_sisa.max' => 'Ukuran bukti pengembalian sisa kas maksimal 5MB.',
        ];
    }

    public function toDTO(?string $buktiKembaliPath = null, array $processedItems = []): LpjPengadaanData
    {
        return new LpjPengadaanData(
            items: ! empty($processedItems) ? $processedItems : $this->validated()['items'],
            buktiKembaliSisaDanaPath: $buktiKembaliPath,
        );
    }
}
```

(Dihapus: pesan `items.*.foto_nota.required`/`items.*.foto_fisik.required` dari `messages()` karena rule `required` sudah diganti kondisional di `withValidator` dengan pesan custom langsung — pesan generik lama tidak lagi relevan.)

- [ ] **Step 4: Ubah `LpjPengadaanController` — eager-load `lpj.items` di `create()`, tulis ulang badan `store()`**

Method `create()` — baris `$proposal->load(...)` saat ini SUDAH memuat `'lpj.items'` (lihat kode Task 1 Step 3 di atas, sudah benar `'items' => ..., 'lpj.items'`) — TIDAK ADA perubahan di `create()` untuk task ini, itu sudah benar dari Task 1.

Method `store()` — SETELAH Task 1 selesai, method ini adalah:

```php
    public function store(StoreLpjRequest $request, PengajuanPengadaan $proposal): RedirectResponse
    {
        abort_unless($proposal->lembaga_id === $this->tenantContext->activeLembagaId(), 404);

        $items = $request->validated()['items'];
        $processedItems = [];

        foreach ($items as $idx => $item) {
            $fotoNotaPath = null;
            $fotoFisikPath = null;

            if ($request->hasFile("items.{$idx}.foto_nota")) {
                $fotoNotaPath = $request->file("items.{$idx}.foto_nota")->store('pengadaan/nota', 'public');
            }
            if ($request->hasFile("items.{$idx}.foto_fisik")) {
                $fotoFisikPath = $request->file("items.{$idx}.foto_fisik")->store('pengadaan/fisik', 'public');
            }

            $processedItems[] = [
                'pengajuan_item_id' => $item['pengajuan_item_id'],
                'harga_satuan_riil' => $item['harga_satuan_riil'],
                'total_riil' => $item['total_riil'],
                'foto_nota_path' => $fotoNotaPath,
                'foto_fisik_barang_path' => $fotoFisikPath,
            ];
        }

        $buktiKembaliPath = null;
        if ($request->hasFile('bukti_kembali_sisa')) {
            $buktiKembaliPath = $request->file('bukti_kembali_sisa')->store('pengadaan/sisa-kas', 'public');
        }

        $dto = $request->toDTO($buktiKembaliPath, $processedItems);
        $this->submitLpjAction->execute($proposal, $dto);

        return redirect()->route('admin.pengadaan.proposal.show', $proposal)
            ->with('success', 'Laporan Pertanggungjawaban (LPJ) berhasil dikirim untuk diaudit oleh Yayasan.');
    }
```

Ubah jadi (baris `abort_unless` PERTAMA TETAP ADA dan TIDAK BOLEH DIHAPUS — hanya badan setelahnya yang berubah):

```php
    public function store(StoreLpjRequest $request, PengajuanPengadaan $proposal): RedirectResponse
    {
        abort_unless($proposal->lembaga_id === $this->tenantContext->activeLembagaId(), 404);

        $existingItems = $proposal->lpj?->items->keyBy('pengajuan_item_id') ?? collect();
        $items = $request->validated()['items'];
        $processedItems = [];

        foreach ($items as $idx => $item) {
            $existing = $existingItems->get($item['pengajuan_item_id']);
            $fotoNotaPath = $existing?->foto_nota_path;
            $fotoFisikPath = $existing?->foto_fisik_barang_path;

            if ($request->hasFile("items.{$idx}.foto_nota")) {
                $fotoNotaPath = $request->file("items.{$idx}.foto_nota")->store('pengadaan/nota', 'public');
            }
            if ($request->hasFile("items.{$idx}.foto_fisik")) {
                $fotoFisikPath = $request->file("items.{$idx}.foto_fisik")->store('pengadaan/fisik', 'public');
            }

            $processedItems[] = [
                'pengajuan_item_id' => $item['pengajuan_item_id'],
                'harga_satuan_riil' => $item['harga_satuan_riil'],
                'total_riil' => $item['total_riil'],
                'foto_nota_path' => $fotoNotaPath,
                'foto_fisik_barang_path' => $fotoFisikPath,
            ];
        }

        $buktiKembaliPath = null;
        if ($request->hasFile('bukti_kembali_sisa')) {
            $buktiKembaliPath = $request->file('bukti_kembali_sisa')->store('pengadaan/sisa-kas', 'public');
        }

        $dto = $request->toDTO($buktiKembaliPath, $processedItems);
        $this->submitLpjAction->execute($proposal, $dto);

        return redirect()->route('admin.pengadaan.proposal.show', $proposal)
            ->with('success', 'Laporan Pertanggungjawaban (LPJ) berhasil dikirim untuk diaudit oleh Yayasan.');
    }
```

- [ ] **Step 5: Tambah banner LPJ RevisionRequired di `proposal/show.blade.php`**

Baris 53-98 saat ini (ambil dari kode existing hasil audit — tombol "Unggah LPJ Belanja" ada di baris 53-56, banner revisi PROPOSAL-level di baris 76-98):

```blade
                @if ($proposal->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::Disbursed && auth()->user()->can('pengadaan.lpj.submit'))
                    <x-link-button href="{{ route('admin.pengadaan.lpj.create', $proposal) }}">
                        <x-icon name="receipt_long" class="h-4 w-4 mr-1" /> Unggah LPJ Belanja
                    </x-link-button>
                @endif
```

Ubah jadi (label tombol kondisional):

```blade
                @if ($proposal->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::Disbursed && auth()->user()->can('pengadaan.lpj.submit'))
                    <x-link-button href="{{ route('admin.pengadaan.lpj.create', $proposal) }}">
                        <x-icon name="receipt_long" class="h-4 w-4 mr-1" />
                        {{ $proposal->lpj && $proposal->lpj->status_lpj === \App\Domains\Pengadaan\Enums\StatusLpj::RevisionRequired ? 'Perbaiki & Kirim Ulang LPJ' : 'Unggah LPJ Belanja' }}
                    </x-link-button>
                @endif
```

Setelah blok "Revision Callout Banner" existing (baris berakhir dengan `@endif` penutup banner proposal-level, diikuti komentar `{{-- Workflow Step Action Banner --}}`), SISIPKAN blok baru SEBELUM komentar itu:

```blade
        {{-- LPJ Revision Callout Banner --}}
        @if ($proposal->lpj && $proposal->lpj->status_lpj === \App\Domains\Pengadaan\Enums\StatusLpj::RevisionRequired)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 p-5 shadow-card">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white shadow-sm">
                            <x-icon name="assignment_late" class="h-5 w-5" />
                        </span>
                        <div class="space-y-1">
                            <h2 class="font-display text-sm font-bold text-amber-900">Perhatian: LPJ Ini Memerlukan Perbaikan</h2>
                            <p class="text-xs text-amber-800 leading-relaxed">
                                Catatan Auditor Yayasan: <b>{{ $proposal->lpj->catatan_verifikasi ?? 'Silakan periksa kembali nota dan foto fisik barang sesuai instruksi auditor.' }}</b>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Workflow Step Action Banner --}}
```

- [ ] **Step 6: Ubah `lpj/create.blade.php` — prefill dari `$proposal->lpj->items`, hapus `required` HTML, tampilkan indikator file lama**

Bagian `<script>` (nama fungsi `lpjCreateForm`), bagian `items: [...]` saat ini:

```php
                items: [
                    @foreach ($proposal->items as $idx => $item)
                    {
                        id: {{ $item->id }},
                        nama: @js($item->nama_barang),
                        qty: {{ $item->qty }},
                        satuan: @js($item->satuan),
                        harga_satuan_riil: {{ (float) $item->estimasi_harga_satuan }},
                        get total_riil() { return this.qty * (Number(this.harga_satuan_riil) || 0); }
                    },
                    @endforeach
                ],
```

Ubah jadi:

```php
                items: [
                    @foreach ($proposal->items as $idx => $item)
                    @php $lpjItem = $proposal->lpj?->items->firstWhere('pengajuan_item_id', $item->id); @endphp
                    {
                        id: {{ $item->id }},
                        nama: @js($item->nama_barang),
                        qty: {{ $item->qty }},
                        satuan: @js($item->satuan),
                        harga_satuan_riil: {{ (float) ($lpjItem->harga_satuan_riil ?? $item->estimasi_harga_satuan) }},
                        fotoNotaUrl: @js($lpjItem?->foto_nota_path ? \Illuminate\Support\Facades\Storage::url($lpjItem->foto_nota_path) : null),
                        fotoFisikUrl: @js($lpjItem?->foto_fisik_barang_path ? \Illuminate\Support\Facades\Storage::url($lpjItem->foto_fisik_barang_path) : null),
                        get total_riil() { return this.qty * (Number(this.harga_satuan_riil) || 0); }
                    },
                    @endforeach
                ],
```

Bagian Scan Nota / Faktur (blok `<div>` yang berisi `<label>...Scan Nota / Faktur...</label>` dan `<input type="file" :name="`items[${index}][foto_nota]`" ...>`) saat ini:

```blade
                                {{-- Scan Nota / Faktur --}}
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Scan Nota / Faktur <span class="text-error-600">*</span></label>
                                    <input
                                        type="file"
                                        :name="`items[${index}][foto_nota]`"
                                        accept="image/jpeg,image/png,image/jpg,application/pdf"
                                        required
                                        class="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300"
                                    >
                                    <p class="text-[10px] text-gray-400 mt-0.5">JPG, PNG, PDF (Maks 5MB)</p>
                                </div>
```

Ubah jadi:

```blade
                                {{-- Scan Nota / Faktur --}}
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">
                                        Scan Nota / Faktur <span class="text-error-600" x-show="!item.fotoNotaUrl">*</span>
                                    </label>
                                    <template x-if="item.fotoNotaUrl">
                                        <button type="button" @click="$store.imagePreview.buka(item.fotoNotaUrl, 'Nota Tersimpan')" class="mb-1 inline-flex items-center gap-1 text-[10px] font-medium text-brand-600 hover:text-brand-800">
                                            <x-icon name="visibility" class="h-3 w-3" /> Lihat nota tersimpan (upload baru untuk mengganti)
                                        </button>
                                    </template>
                                    <input
                                        type="file"
                                        :name="`items[${index}][foto_nota]`"
                                        accept="image/jpeg,image/png,image/jpg,application/pdf"
                                        class="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300"
                                    >
                                    <p class="text-[10px] text-gray-400 mt-0.5">JPG, PNG, PDF (Maks 5MB)</p>
                                </div>
```

Bagian Foto Fisik Barang Tiba (blok setelahnya) saat ini:

```blade
                                {{-- Foto Fisik Barang Tiba --}}
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Foto Fisik Barang Tiba <span class="text-error-600">*</span></label>
                                    <input
                                        type="file"
                                        :name="`items[${index}][foto_fisik]`"
                                        accept="image/jpeg,image/png,image/jpg"
                                        required
                                        class="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300"
                                    >
                                    <p class="text-[10px] text-gray-400 mt-0.5">Foto Barang JPG, PNG (Maks 5MB)</p>
                                </div>
```

Ubah jadi:

```blade
                                {{-- Foto Fisik Barang Tiba --}}
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">
                                        Foto Fisik Barang Tiba <span class="text-error-600" x-show="!item.fotoFisikUrl">*</span>
                                    </label>
                                    <template x-if="item.fotoFisikUrl">
                                        <button type="button" @click="$store.imagePreview.buka(item.fotoFisikUrl, 'Foto Fisik Tersimpan')" class="mb-1 inline-flex items-center gap-1 text-[10px] font-medium text-brand-600 hover:text-brand-800">
                                            <x-icon name="visibility" class="h-3 w-3" /> Lihat foto tersimpan (upload baru untuk mengganti)
                                        </button>
                                    </template>
                                    <input
                                        type="file"
                                        :name="`items[${index}][foto_fisik]`"
                                        accept="image/jpeg,image/png,image/jpg"
                                        class="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300"
                                    >
                                    <p class="text-[10px] text-gray-400 mt-0.5">Foto Barang JPG, PNG (Maks 5MB)</p>
                                </div>
```

Banner informasi (baris berisi `<p class="text-xs text-indigo-800 max-w-sm text-right leading-relaxed">`) saat ini:

```blade
            <p class="text-xs text-indigo-800 max-w-sm text-right leading-relaxed">
                Input nominal nota riil per barang dan lampirkan <b>scan nota/faktur</b> serta <b>foto fisik barang</b> saat tiba di sekolah.
            </p>
```

Ubah jadi:

```blade
            <p class="text-xs text-indigo-800 max-w-sm text-right leading-relaxed">
                Input nominal nota riil per barang dan lampirkan <b>scan nota/faktur</b> serta <b>foto fisik barang</b> saat tiba di sekolah.
                @if ($proposal->lpj)
                    Item yang tidak diunggah ulang akan tetap memakai berkas sebelumnya.
                @endif
            </p>
```

- [ ] **Step 7: Jalankan test untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Feature/Pengadaan/LpjValidationTest.php --compact`
Expected: PASS — semua test di file ini hijau, TERMASUK `test_lpj_requires_scan_nota_and_foto_fisik` (submit PERTAMA KALI tanpa file APAPUN tetap gagal validasi seperti semula — behavior submit pertama TIDAK BERUBAH karena `$existingItems` selalu kosong untuk proposal yang belum pernah punya LPJ).

- [ ] **Step 8: Jalankan seluruh test Pengadaan untuk cek regresi lebih luas**

Run: `vendor/bin/pest tests/Feature/Pengadaan tests/Unit/Domains/Pengadaan --compact`
Expected: semua PASS.

- [ ] **Step 9: Verifikasi manual dev-server**

`npm run build`, login sebagai pengaju lembaga, buka proposal yang LPJ-nya sudah diminta revisi (kalau tidak ada data seperti ini, buat manual via tinker/seeder sementara TANPA mengubah seeder permanen), konfirmasi: banner amber "Perhatian: LPJ Ini Memerlukan Perbaikan" muncul di `proposal/show`, tombol berlabel "Perbaiki & Kirim Ulang LPJ", form `lpj/create` menampilkan link "Lihat nota tersimpan" untuk item yang sudah py file, dan bisa submit ulang tanpa upload ulang semuanya.

- [ ] **Step 10: Commit**

```bash
git add resources/views/portals/lembaga/pengadaan/proposal/show.blade.php app/Http/Requests/Pengadaan/StoreLpjRequest.php app/Http/Controllers/Lembaga/Pengadaan/LpjPengadaanController.php resources/views/portals/lembaga/pengadaan/lpj/create.blade.php tests/Feature/Pengadaan/LpjValidationTest.php
git commit -m "fix(pengadaan): tampilkan catatan penolakan LPJ ke sekolah, izinkan resubmit parsial tanpa upload ulang semua file

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 8: Catatan Wajib Diisi Saat Menolak/Minta Revisi (Proposal & LPJ)

**Files:**
- Modify: `app/Http/Requests/Pengadaan/ProcessApprovalRequest.php`
- Modify: `app/Http/Controllers/Yayasan/Pengadaan/AuditLpjController.php:71-74`
- Test: `tests/Feature/Pengadaan/SequentialApprovalTest.php` (tambah 1 method — cek dulu isi file untuk pola setup), `tests/Feature/Pengadaan/PengadaanControllerTest.php` (tambah 1 method untuk LPJ)

**Interfaces:**
- Consumes: tidak ada dari task lain.
- Produces: tidak ada interface baru.

- [ ] **Step 1: Tulis test yang gagal untuk kedua kasus**

Di `tests/Feature/Pengadaan/SequentialApprovalTest.php` (baca dulu untuk pola setup existing — kemungkinan sudah punya helper Yayasan/Lembaga/User/proposal siap approve), tambahkan di akhir class:

```php

    public function test_reject_proposal_tanpa_notes_ditolak_validasi(): void
    {
        $response = $this->actingAs($this->approver)
            ->post(route('admin.pengadaan.inbox.decision', $this->proposal), [
                'action' => 'REJECT',
            ]);

        $response->assertSessionHasErrors(['notes']);
    }
```

**Catatan**: sesuaikan `$this->approver`/`$this->proposal` dengan nama variabel yang SUDAH ADA di `setUp()` file target — baca file dulu, JANGAN asumsikan nama variabel.

Di `tests/Feature/Pengadaan/PengadaanControllerTest.php`, tambahkan (pola setup mengikuti test Task 6 yang sudah ditambahkan sebelumnya di file yang sama — reuse Yayasan/Lembaga/User/proposal/lpj kalau memungkinkan, atau buat baru kalau test Task 6 sudah dihapus/tidak reusable):

```php

    public function test_verify_lpj_minta_perbaikan_tanpa_catatan_ditolak_validasi(): void
    {
        $yayasan = Yayasan::create(['nama' => 'Yayasan Catatan Test']);
        $lembaga = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'Sekolah Catatan Test', 'npsn' => '99997777', 'status_aktif' => true]);
        $bendahara = User::factory()->create(['yayasan_id' => $yayasan->id]);
        $role = Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $role->givePermissionTo(['pengadaan.lpj.verify']);
        $bendahara->assignRole($role);

        $proposal = PengajuanPengadaan::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'nomor_pengajuan' => 'PR/2026/09/CATATAN-TEST',
            'judul_pengajuan' => 'Pengadaan Test Catatan',
            'tingkat_urgensi' => 'biasa',
            'total_estimasi' => 1000000,
            'nominal_pencairan' => 1000000,
            'status' => StatusPengajuan::Disbursed,
        ]);

        $lpj = LpjPengadaan::create([
            'pengajuan_pengadaan_id' => $proposal->id,
            'status_lpj' => StatusLpj::Submitted,
        ]);

        $response = $this->actingAs($bendahara)->post(route('admin.pengadaan.audit-lpj.verify', $lpj), [
            'is_approved' => false,
        ]);

        $response->assertSessionHasErrors(['catatan_verifikasi']);
    }
```

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Feature/Pengadaan/SequentialApprovalTest.php --filter="test_reject_proposal_tanpa_notes_ditolak_validasi"`
Expected: FAIL — tidak ada error `notes` karena rule masih `nullable` murni.

Run: `vendor/bin/pest tests/Feature/Pengadaan/PengadaanControllerTest.php --filter="test_verify_lpj_minta_perbaikan_tanpa_catatan_ditolak_validasi"`
Expected: FAIL — sama, `catatan_verifikasi` masih `nullable`.

- [ ] **Step 3: Ubah `ProcessApprovalRequest`**

Baris `'notes' => ['nullable', 'string', 'max:1000'],` di `rules()`, ubah jadi:

```php
            'notes' => ['nullable', 'string', 'max:1000', 'required_if:action,REJECT,REQUEST_REVISION'],
```

Di `messages()`, tambah baris baru:

```php
            'notes.required_if' => 'Catatan wajib diisi saat menolak atau meminta revisi, supaya sekolah tahu apa yang perlu diperbaiki.',
```

File lengkap setelah perubahan:

```php
<?php

namespace App\Http\Requests\Pengadaan;

use App\Domains\Workflow\Enums\ApprovalAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ProcessApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny(['pengadaan.approval.internal', 'pengadaan.approval.yayasan']) ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', new Enum(ApprovalAction::class)],
            'notes' => ['nullable', 'string', 'max:1000', 'required_if:action,REJECT,REQUEST_REVISION'],
            'item_decisions' => ['nullable', 'array'],
            'item_decisions.*.status' => ['required_with:item_decisions', 'in:approved,rejected'],
            'item_decisions.*.catatan' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Keputusan tindakan persetujuan (Setujui, Tolak, atau Revisi) wajib dipilih.',
            'notes.max' => 'Catatan keputusan maksimal 1000 karakter.',
            'notes.required_if' => 'Catatan wajib diisi saat menolak atau meminta revisi, supaya sekolah tahu apa yang perlu diperbaiki.',
            'item_decisions.*.status.in' => 'Keputusan item harus approved atau rejected.',
        ];
    }
}
```

- [ ] **Step 4: Ubah `AuditLpjController::verify()`**

Baris `$request->validate([...])` saat ini:

```php
        $request->validate([
            'is_approved' => ['required', 'boolean'],
            'catatan_verifikasi' => ['nullable', 'string', 'max:1000'],
        ]);
```

Ubah jadi:

```php
        $request->validate([
            'is_approved' => ['required', 'boolean'],
            'catatan_verifikasi' => ['nullable', 'string', 'max:1000', 'required_if:is_approved,0'],
        ], [
            'catatan_verifikasi.required_if' => 'Catatan wajib diisi saat meminta perbaikan LPJ, supaya sekolah tahu apa yang perlu diperbaiki.',
        ]);
```

- [ ] **Step 5: Jalankan test untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Feature/Pengadaan/SequentialApprovalTest.php tests/Feature/Pengadaan/PengadaanControllerTest.php --compact`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/Pengadaan/ProcessApprovalRequest.php app/Http/Controllers/Yayasan/Pengadaan/AuditLpjController.php tests/Feature/Pengadaan/SequentialApprovalTest.php tests/Feature/Pengadaan/PengadaanControllerTest.php
git commit -m "fix(pengadaan): wajibkan catatan saat menolak/minta revisi proposal atau LPJ

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 9: Item Decision Default Mencerminkan Histori Review Sebelumnya

**Files:**
- Modify: `resources/views/portals/yayasan/pengadaan/inbox/review.blade.php:53-63`

**Interfaces:**
- Consumes: `PengajuanPengadaanItem::status_item` (enum `StatusItemPengajuan`, cast di model — DIKONFIRMASI ADA di `app/Domains/Pengadaan/Models/PengajuanPengadaanItem.php`), `PengajuanPengadaanItem::catatan_reviewer` (string nullable, DIKONFIRMASI ADA di model yang sama, diisi `ProcessProposalApprovalAction`).
- Produces: tidak ada interface baru.

**Sebelum mulai**: verifikasi ulang 2 kolom ini benar-benar ada di model dengan menjalankan:
```
grep -n "status_item\|catatan_reviewer" app/Domains/Pengadaan/Models/PengajuanPengadaanItem.php
```
Kalau nama kolom BERBEDA dari yang tertulis di task ini, STOP dan laporkan — jangan lanjut dengan asumsi nama yang salah.

- [ ] **Step 1: Ubah state awal Alpine di `review.blade.php`**

Baris 53-63 saat ini:

```blade
            x-data="{
                action: 'APPROVE',
                decisions: {
                    @foreach ($proposal->items as $item)
                        {{ $item->id }}: {
                            status: 'approved',
                            catatan: ''
                        },
                    @endforeach
                }
            }"
```

Ubah jadi:

```blade
            x-data="{
                action: 'APPROVE',
                decisions: {
                    @foreach ($proposal->items as $item)
                        {{ $item->id }}: {
                            status: '{{ $item->status_item->value === 'rejected' ? 'rejected' : 'approved' }}',
                            catatan: @js($item->catatan_reviewer ?? '')
                        },
                    @endforeach
                }
            }"
```

- [ ] **Step 2: Verifikasi manual — tidak ada automated test untuk state awal Alpine murni**

Buka proposal yang sudah pernah direvisi sekali (minimal 1 item `status_item = rejected` dengan `catatan_reviewer` terisi), akses halaman review approver lagi, konfirmasi dropdown "Keputusan Item" untuk item itu sudah ter-set ke "Ditolak / Dicoret" dan field catatan terisi otomatis dengan catatan sebelumnya.

- [ ] **Step 3: Jalankan test Feature existing yang menyentuh halaman review untuk memastikan tidak ada regresi**

Run: `vendor/bin/pest tests/Feature/Pengadaan/SequentialApprovalTest.php --compact`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/views/portals/yayasan/pengadaan/inbox/review.blade.php
git commit -m "fix(pengadaan): form review item tampilkan histori keputusan sebelumnya, bukan selalu default approved

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 10: Hapus Route `destroy` Proposal yang Dead Code

**Files:**
- Modify: `routes/admin/pengadaan.php:8`

**Interfaces:**
- Consumes: tidak ada.
- Produces: tidak ada.

- [ ] **Step 1: Ubah baris resource route**

Baris 8 saat ini:

```php
    Route::resource('proposal', \App\Http\Controllers\Lembaga\Pengadaan\PengajuanPengadaanController::class);
```

Ubah jadi:

```php
    Route::resource('proposal', \App\Http\Controllers\Lembaga\Pengadaan\PengajuanPengadaanController::class)->except(['destroy']);
```

- [ ] **Step 2: Konfirmasi route `destroy` sudah tidak terdaftar**

Run: `php artisan route:list --name=admin.pengadaan.proposal.destroy`
Expected: output kosong (tidak ada route dengan nama itu lagi).

- [ ] **Step 3: Jalankan seluruh test Pengadaan untuk memastikan tidak ada yang bergantung pada route ini**

Run: `vendor/bin/pest tests/Feature/Pengadaan tests/Unit/Domains/Pengadaan --compact`
Expected: semua PASS (tidak ada test yang memanggil `route('admin.pengadaan.proposal.destroy', ...)`).

- [ ] **Step 4: Commit**

```bash
git add routes/admin/pengadaan.php
git commit -m "fix(pengadaan): hapus route destroy proposal yang dead code (controller tidak punya method-nya)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 11: Polish — Wording, `<x-select>`, A11y Label, Empty-State Konsisten

**Files:**
- Modify: `resources/views/portals/lembaga/pengadaan/proposal/index.blade.php`
- Modify: `resources/views/portals/lembaga/pengadaan/proposal/create.blade.php`
- Modify: `resources/views/portals/lembaga/pengadaan/proposal/edit.blade.php`
- Modify: `resources/views/portals/lembaga/pengadaan/proposal/_daftar.blade.php`
- Modify: `resources/views/portals/yayasan/pengadaan/inbox/index.blade.php`
- Modify: `resources/views/portals/yayasan/pengadaan/disbursement/index.blade.php`
- Modify: `resources/views/portals/yayasan/pengadaan/audit-lpj/index.blade.php`
- Modify: `resources/views/portals/yayasan/pengadaan/disbursement/_daftar.blade.php`
- Modify: `resources/views/portals/yayasan/pengadaan/audit-lpj/_daftar.blade.php`

**Interfaces:**
- Consumes: `<x-select>` component (`resources/views/components/select.blade.php`, `@props(['disabled' => false, 'error' => false])`, menerima atribut lewat `$attributes->merge()` termasuk `x-model`/`name`/`@change` apa adanya).
- Produces: tidak ada interface baru.

**PRASYARAT: Task 3 WAJIB sudah selesai & di-commit sebelum task ini dimulai** (`proposal/index.blade.php` dan `inbox/index.blade.php` sudah diubah Task 3 — task ini menambah perubahan LAIN di file yang sama, BUKAN mengembalikan perubahan Task 3).

- [ ] **Step 1: Wording — samakan istilah "Proposal/Usulan/Pengajuan" jadi "Pengajuan" di teks tampilan**

Di `proposal/index.blade.php`, baris `<p class="text-xs text-gray-500 mt-0.5">Kelola proposal usulan belanja fasilitas, pelacakan alur persetujuan, dan LPJ realisasi.</p>`, ubah jadi:

```blade
                <p class="text-xs text-gray-500 mt-0.5">Kelola pengajuan belanja fasilitas, pelacakan alur persetujuan, dan LPJ realisasi.</p>
```

Baris `<p class="flex items-center gap-2 text-sm font-semibold text-gray-700">` yang berisi teks "Filter Data Usulan" (dalam elemen `<x-icon>` + teks), ubah teks "Filter Data Usulan" jadi "Filter Data Pengajuan":

```blade
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                        Filter Data Pengajuan
                    </p>
```

Di `proposal/create.blade.php`, cari baris judul halaman berisi teks "Buat Usulan Pengadaan Sarpras" — ubah jadi "Buat Pengajuan Pengadaan Sarpras". Cari baris breadcrumb berisi teks "Buat Usulan" (biasanya di elemen `<b>` breadcrumb) — ubah jadi "Buat Pengajuan".

Di `proposal/_daftar.blade.php`, cari baris tombol/link berisi teks "Isi LPJ Belanja" — ubah jadi "Unggah LPJ Belanja" (menyamakan dengan `proposal/show.blade.php` yang sudah pakai istilah ini).

- [ ] **Step 2: `<x-select>` — konversi 4 dropdown statis/filter**

Di `proposal/create.blade.php`, cari `<select name="tingkat_urgensi" ...>` (native, TIDAK di dalam `x-for`), ubah jadi:

```blade
<x-select name="tingkat_urgensi" required>
    <option value="">-- Pilih Tingkat Urgensi --</option>
    <option value="biasa">Biasa / Rutin</option>
    <option value="mendesak">Mendesak</option>
    <option value="kritis">Kritis / Darurat</option>
</x-select>
```

(Kalau ada atribut lain seperti `x-model` di elemen `<select>` original, pertahankan atribut itu di `<x-select>` — jangan hilangkan, cuma ganti tag `select`→`x-select` dan tutupnya.)

Di `proposal/edit.blade.php`, elemen `<select>` untuk `tingkat_urgensi` (hasil Task 5, sudah punya 3 `<option>` dengan value benar) — bungkus dengan `<x-select>` alih-alih `<select>` native, PERTAHANKAN value/label/kondisi `selected` dari Task 5 apa adanya, HANYA ganti tag pembuka `<select name="tingkat_urgensi" ...>` jadi `<x-select name="tingkat_urgensi" ...>` dan tag penutup `</select>` jadi `</x-select>`.

Di `proposal/index.blade.php`, dua `<select x-model="filters.status" @change="muatUlangDaftar()" class="...">` dan `<select x-model="filters.urgensi" @change="muatUlangDaftar()" class="...">` — ganti jadi:

```blade
<x-select x-model="filters.status" @change="muatUlangDaftar()">
    <option value="">Semua Status</option>
    <option value="draft">Draft Usulan</option>
    <option value="submitted">Diajukan</option>
    <option value="in_review">Sedang Direview</option>
    <option value="revision_required">Perlu Revisi</option>
    <option value="approved">Disetujui</option>
    <option value="disbursed">Dana Cair</option>
    <option value="completed">Selesai (LPJ Terverifikasi)</option>
    <option value="rejected">Ditolak</option>
</x-select>
```

```blade
<x-select x-model="filters.urgensi" @change="muatUlangDaftar()">
    <option value="">Semua Tingkat</option>
    <option value="biasa">Biasa / Rutin</option>
    <option value="mendesak">Mendesak</option>
    <option value="kritis">Kritis / Darurat</option>
</x-select>
```

(Hapus `class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500"` — styling ini sudah built-in di komponen `<x-select>`, tidak perlu dipertahankan manual.)

- [ ] **Step 3: A11y — tambah `id="search"` ke 4 input pencarian**

Di `proposal/index.blade.php`, `inbox/index.blade.php`, `disbursement/index.blade.php`, `audit-lpj/index.blade.php` — masing-masing punya `<label for="search">Cari ...</label>` diikuti `<input type="text" x-model="filters.search" ...>` di bawahnya. Tambah `id="search"` ke keempat `<input>` tersebut:

```blade
<input
    id="search"
    type="text" x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()"
    placeholder="..."
    class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
>
```

(Placeholder teks tiap file TIDAK berubah, hanya tambah atribut `id="search"` di baris pembuka `<input`.)

- [ ] **Step 4: Empty-state — samakan `disbursement/_daftar.blade.php` dan `audit-lpj/_daftar.blade.php` ke pola `inbox/_daftar.blade.php`**

Baca `resources/views/portals/yayasan/pengadaan/inbox/_daftar.blade.php` baris 50-59 sebagai referensi struktur (ikon bulat + judul + subjudul). Di `disbursement/_daftar.blade.php` baris 56-59 (empty-state teks polos saat ini), ganti dengan struktur yang SAMA (ikon `<svg>`/`<x-icon>` bulat, judul, subjudul) tapi teks disesuaikan konteks pencairan dana, misalnya:

```blade
<tr>
    <td colspan="{{ $kolomCount ?? 5 }}" class="px-5 py-12 text-center text-gray-400">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-3">
            <x-icon name="payments" class="h-6 w-6" />
        </div>
        <p class="text-sm font-semibold text-gray-700">Belum ada pencairan dana.</p>
        <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">Proposal yang sudah disetujui akan muncul di sini untuk diproses pencairan.</p>
    </td>
</tr>
```

**Catatan**: `colspan` HARUS disesuaikan dengan jumlah kolom tabel aktual di `disbursement/_daftar.blade.php` (baca `<thead>` di `disbursement/index.blade.php` untuk menghitung jumlah `<th>` yang benar, JANGAN asumsikan angka dari contoh di atas) — kalau kode existing sudah punya `colspan` numerik tetap (bukan variabel `$kolomCount`), pertahankan pola numerik yang sama, cukup ganti isi `<td>`-nya.

Terapkan pola yang SAMA (ikon+judul+subjudul, `colspan` sesuai jumlah kolom aktual file itu) di `audit-lpj/_daftar.blade.php`, teks disesuaikan konteks audit LPJ, misalnya "Belum ada LPJ untuk diaudit." / "LPJ yang sudah dikirim sekolah akan muncul di sini untuk diverifikasi."

- [ ] **Step 5: Verifikasi manual dev-server**

`npm run build`. Buka keempat halaman index Pengadaan, konfirmasi: dropdown filter tampil dengan styling `<x-select>` standar (bukan native browser default), label pencarian bisa diklik untuk fokus ke input (test a11y sederhana), empty-state disbursement/audit-lpj sekarang py ikon (kosongkan filter dulu sampai tabel benar-benar kosong untuk melihatnya, atau baca langsung HTML via view-source).

- [ ] **Step 6: Jalankan seluruh test Pengadaan untuk memastikan tidak ada regresi dari perubahan markup**

Run: `vendor/bin/pest tests/Feature/Pengadaan tests/Unit/Domains/Pengadaan --compact`
Expected: semua PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/views/portals/lembaga/pengadaan/proposal/index.blade.php resources/views/portals/lembaga/pengadaan/proposal/create.blade.php resources/views/portals/lembaga/pengadaan/proposal/edit.blade.php resources/views/portals/lembaga/pengadaan/proposal/_daftar.blade.php resources/views/portals/yayasan/pengadaan/inbox/index.blade.php resources/views/portals/yayasan/pengadaan/disbursement/index.blade.php resources/views/portals/yayasan/pengadaan/audit-lpj/index.blade.php resources/views/portals/yayasan/pengadaan/disbursement/_daftar.blade.php resources/views/portals/yayasan/pengadaan/audit-lpj/_daftar.blade.php
git commit -m "style(pengadaan): samakan wording, pakai <x-select> di dropdown statis/filter, perbaiki a11y label, samakan empty-state

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 12: Regresi Penutup

**Files:**
- Tidak ada file yang dimodifikasi — task ini murni verifikasi.

**Interfaces:**
- Consumes: seluruh perubahan dari Task 1-11.
- Produces: tidak ada.

- [ ] **Step 1: Jalankan seluruh test Pengadaan**

Run: `vendor/bin/pest tests/Feature/Pengadaan tests/Unit/Domains/Pengadaan --compact`
Expected: semua PASS, tidak ada yang gagal.

- [ ] **Step 2: Jalankan Pint pada semua file yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` (jalankan ulang sampai `passed` kalau ada auto-fix diterapkan).

- [ ] **Step 3: Jalankan full test suite proyek**

Run: `php artisan test --compact`
Expected: HANYA 3 kegagalan pre-existing yang sudah dikenal (`Tests\Unit\M3DemoDataSeederTest` x2, `Tests\Feature\Akademik\SubjekTenantValidationTest`) yang muncul. KALAU ADA kegagalan lain — STOP, laporkan detail (nama test, pesan error) alih-alih mengasumsikan pre-existing.

- [ ] **Step 4: Konfirmasi ulang dampak operasional dari Task 2**

Kutip ulang angka dari Task 2 Step 6 (`User::whereNull('yayasan_id')->whereHas('roles', ...)->count()`) di laporan akhir — kalau > 0, WAJIB disebutkan eksplisit ke user sebagai catatan tindak lanjut manual (perbaiki data akun-akun itu), BUKAN dianggap selesai begitu saja.

- [ ] **Step 5: Verifikasi manual dev-server — rekap semua perubahan UI dari Task 3,4,7,9,11**

Checklist ulang (boleh screenshot untuk laporan handoff): filter proposal & inbox berfungsi (Task 3), badge status Rejected/InReview/Disbursed tidak abu-abu (Task 4), banner+resubmit LPJ RevisionRequired berfungsi end-to-end (Task 7), form review item menampilkan histori (Task 9), dropdown pakai `<x-select>` + empty-state konsisten (Task 11).

- [ ] **Step 6: Commit penutup (kalau ada sisa perubahan dari Pint)**

```bash
git status
```

Kalau ada perubahan tersisa dari auto-fix Pint yang belum ter-commit:

```bash
git add -u
git commit -m "style(pengadaan): rapikan format Pint hasil perbaikan audit menyeluruh

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

Kalau working tree bersih, tidak perlu commit apa pun di step ini.

---

## Self-Review — Putaran 1 (cakupan spec + placeholder + konsistensi tipe)

**Cakupan spec**: §2.1→Task 1, §2.2→Task 2, §2.3→Task 3, §2.4→Task 4, §2.5→Task 5, §2.6→Task 6, §2.7→Task 7, §2.8→Task 8, §2.9→Task 9, §2.10→Task 10, §2.11+§2.12+§2.13+§2.14→Task 11, §5 (pengujian)→tersebar ke tiap task+Task 12, §6 (struktur task)→diikuti persis. §3 (item di luar scope)→dikonfirmasi TIDAK ADA task yang menyentuh `RecordDisbursementAction` validasi nominal, preview thumbnail, atau refactor inline script `lpj/create.blade.php` ke file JS terdaftar — dicek ulang tidak ada gap.

**Placeholder scan**: tidak ditemukan "TBD"/"TODO"/deskripsi tanpa kode — setiap step code block berisi kode lengkap siap salin.

**Konsistensi tipe**: nama field item baru (`fotoNotaUrl`, `fotoFisikUrl`) yang diperkenalkan Task 7 Step 6 dipakai KONSISTEN di kedua blok file input (Scan Nota & Foto Fisik) pada step yang sama — tidak ada perbedaan penamaan. Method `activeYayasanId()` Task 2 tetap `?int` sebagai return type, tidak berubah di seluruh 4 controller consumer.

## Self-Review — Putaran 2 (verifikasi terhadap kode aktual & test existing)

- Dikonfirmasi ulang `tests/Feature/Pengadaan/CrossTenantIsolationTest.php` MEMANG punya property `$proposalA2`, `$lembagaB1`, `$yayasanA` yang dipakai Task 1 & Task 2 — dibaca langsung dari file existing sebelum plan ditulis, bukan asumsi.
- Dikonfirmasi `tests/Feature/Pengadaan/LpjValidationTest.php` MEMANG punya `$this->proposal`, `$this->item`, `$this->user`, `$this->kategori`, `$this->ruangan` di `setUp()` — Task 7 test barunya konsisten memakai properti yang sama.
- Ditambahkan instruksi eksplisit di Task 5, 6, 8 untuk "baca dulu file test target sebelum menambah method" karena TIDAK semua isi lengkap file test (`PengadaanValidationTest.php`, `SequentialApprovalTest.php`) dibaca penuh saat spec/plan ditulis — mencegah implementer menduplikasi setup atau salah nama variabel.
- Dikonfirmasi `Route::resource('proposal', ...)` di `routes/admin/pengadaan.php:8` MEMANG tanpa `->except()` apa pun saat ini — Task 10 aman menambahkannya.

## Self-Review — Putaran 3 (dependency antar-task & urutan risiko)

- **Task 1 → Task 7**: dikonfirmasi ulang Task 7 Step 4 MENULIS ULANG method `store()` MULAI DARI baris `abort_unless` yang sama persis dengan hasil Task 1 — kalau Task 1 belum jalan, baris itu tidak akan ada di kode "saat ini" yang dikutip Task 7, implementer akan bingung. Sudah ditambahkan "PRASYARAT" eksplisit di kedua task (Task 7 menyebut Task 1, kickoff nanti akan menegaskan urutan global).
- **Task 3 → Task 11**: dikonfirmasi ulang Task 11 Step 2 & 3 menyentuh file yang SAMA (`proposal/index.blade.php`, `inbox/index.blade.php`) dengan perubahan Task 3 (`x-ref="tableContainer"`) — TIDAK overlap baris (Task 3 di baris pembungkus tabel, Task 11 di baris dropdown/input jauh di atasnya), tapi urutan commit tetap harus Task 3 dulu supaya diff Task 11 dibuat di atas kode yang sudah benar.
- **Task 2 4-controller cleanup**: dikonfirmasi tidak ada task lain yang menyentuh `AuditLpjController.php`, `DisbursementPengadaanController.php`, `ApprovalPengadaanController.php` di baris yang sama dengan Task 2 (Task 6 menyentuh `AuditLpjController` juga TAPI di method `verify()` yang beda dari `index()` yang disentuh Task 2 — TIDAK overlap baris, aman independen, TIDAK perlu urutan khusus antara Task 2 dan Task 6/8).
- Task 8 menyentuh `AuditLpjController::verify()` (baris validasi `is_approved`) — dikonfirmasi ulang ini BEDA baris dari Task 2 (yang mengubah `index()`) dan Task 6 (yang mengubah view `show.blade.php`, BUKAN controller `verify()`) — 3 task ini aman independen satu sama lain di file yang sama.

## Self-Review — Putaran 4 (baca ulang dengan mata segar)

- Task 7 Step 3 mengganti TOTAL isi `StoreLpjRequest.php` (bukan diff parsial) karena perubahan menyentuh `rules()` DAN `withValidator()` DAN `messages()` sekaligus — dicek ulang versi "ganti total" yang dicantumkan SAMA PERSIS dengan versi final di spec §2.7(b), tidak ada bagian yang tertinggal (method `toDTO()` di akhir file dikonfirmasi ikut tersalin utuh).
- Dicek ulang pesan commit Task 2 Step 7 punya placeholder `[ISI ANGKA DARI STEP 6 DI SINI]` yang HARUS diisi implementer sebelum commit — ini BUKAN pelanggaran "No Placeholders" rule skill (yang melarang placeholder di INSTRUKSI plan), karena ini instruksi eksplisit ke implementer untuk mengisi data run-time yang tidak bisa diketahui saat plan ditulis (angka dari query database), bukan kode yang seharusnya sudah lengkap.
- Ditemukan saat baca ulang: draft awal Task 9 belum menyebutkan instruksi verifikasi kolom `status_item`/`catatan_reviewer` sebelum mulai — DITAMBAHKAN "Sebelum mulai" block di Task 9 sebelum plan ini final, konsisten dengan catatan di spec §9 (putaran 3) yang mewajibkan ini.
- Dicek ulang urutan Task 1-12 di dokumen ini SAMA PERSIS dengan urutan di spec §6 — tidak ada task yang tertukar posisi.
