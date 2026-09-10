# Perbaikan Audit SDM Izin/Cuti (Crash Pool, HTML Rusak, Pencarian Alasan, Riwayat Admin) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup 4 bug/gap nyata yang ditemukan audit di modul SDM Izin/Cuti — crash 500 untuk pegawai pool yayasan, markup HTML rusak di halaman riwayat pegawai, fitur pencarian "alasan" yang tidak berfungsi, dan tidak adanya riwayat/arsip pengajuan yang sudah diputuskan di sisi admin.

**Architecture:** 4 perbaikan independen murni di domain SDM (`app/Domains/Sdm/*`, `app/Http/Controllers/*IzinCuti*`, view + JS terkait) — TIDAK menyentuh engine `Workflow` bersama sama sekali. Task 1 & 2 adalah perbaikan titik (1 method / 1 blok markup). Task 3 menggabungkan 2 temuan yang saling bergantung di file yang sama (controller query + view + JS Alpine SPA client-side). Task 4 adalah regresi penutup.

**Tech Stack:** Laravel 12 (PHP 8.3), Pest, Blade, Alpine.js (pola SPA client-side sudah established di file ini).

## Global Constraints

- Perbaikan Task 1 HANYA menutup crash (fail gracefully dengan pesan jelas) — BUKAN membangun kapabilitas baru supaya pegawai pool yayasan BENAR-BENAR bisa mengajukan & diproses sampai selesai. Itu butuh step workflow baru berscope yayasan + keputusan produk terpisah, di luar scope plan ini. Jangan mencoba "memperbaiki lebih jauh" dari sekadar mengganti crash 500 menjadi error pesan jelas.
- JANGAN sentuh `app/Domains/Workflow/*` (engine approval bersama) sama sekali di plan ini — ke-4 perbaikan murni domain SDM.
- State `ApprovalStatus::RevisionRequired` TIDAK di-wire ke UI SDM dalam plan ini (butuh keputusan produk terpisah, bukan bug fix — di luar scope).
- TIDAK ADA pagination/infinite-scroll untuk tab Riwayat baru di Task 3. TIDAK ADA filter tanggal/rentang waktu untuk tab Riwayat. YAGNI — jangan ditambahkan walau "kelihatannya berguna".
- Perilaku pill kategori (Semua/Cuti/Sakit/Izin) di halaman admin SENGAJA berubah jadi mengikuti `itemsInView` (ikut `viewMode` aktif — Menunggu vs Riwayat), BUKAN total keseluruhan item seperti sebelumnya. Ini perubahan perilaku yang DISENGAJA, bukan bug untuk "diperbaiki" balik ke perilaku lama.
- Stat card "Menunggu Approval" di bagian atas halaman admin SENGAJA TIDAK ikut berubah oleh `viewMode` — pakai getter `totalPending` yang terpisah dari `itemsInView`, supaya admin tetap tahu ada berapa banyak pengajuan pending walau sedang membuka tab Riwayat.
- Pesan error Task 1 WAJIB memakai teks PERSIS ini (field key `'pegawai'`): `'Pengajuan izin/cuti mandiri belum didukung untuk pegawai pool yayasan (tanpa lembaga tetap). Silakan hubungi admin SDM untuk memprosesnya secara manual.'` — jangan diringkas atau diubah kata-katanya.
- Query `index()` di Task 3 HARUS MENGGANTI TOTAL filter `whereIn('status', [ApprovalStatus::Pending, ApprovalStatus::InReview])` yang lama dengan `whereHas('approvalRequest')` tanpa filter status — BUKAN ditambahkan sebagai filter tambahan di atas yang lama (kalau ditambah bukan diganti, hasilnya identik dengan sebelum diperbaiki, riwayat tetap tidak pernah muncul).

---

## Task 1: Perbaikan Crash Pegawai Pool Yayasan

**Files:**
- Modify: `app/Domains/Sdm/Actions/AjukanIzinCutiAction.php:24-35` (awal method `execute`)
- Test: `tests/Feature/Sdm/AjukanIzinCutiActionTest.php` (tambah 2 test baru di akhir file, sebelum fungsi helper `seedKuotaCutiWorkflowForTest_ajukan()`)

**Interfaces:**
- Consumes: `App\Models\Karyawan` (factory state `pool()` sudah ada di `database/factories/KaryawanFactory.php:61-66`, mengeset `lembaga_id => null`), `App\Domains\Sdm\Models\PengajuanIzinCuti` (untuk assert count).
- Produces: tidak ada interface baru — `AjukanIzinCutiAction::execute()` tetap signature yang sama (`Model $pegawai, KategoriPengajuanIzin $kategori, string $tanggalMulai, string $tanggalSelesai, string $alasan`), sekarang melempar `ValidationException` tambahan untuk 1 kondisi baru.

- [ ] **Step 1: Tulis test yang gagal — pegawai pool (lembaga_id null) ditolak dengan pesan jelas, bukan crash**

Tambahkan di `tests/Feature/Sdm/AjukanIzinCutiActionTest.php`, setelah test terakhir (`it('serializes concurrent Cuti submissions...')`, baris 125) dan SEBELUM fungsi `function seedKuotaCutiWorkflowForTest_ajukan()` (baris 127):

```php
it('rejects a pengajuan from a pool karyawan (lembaga_id null) with a clear validation message, not a crash', function () {
    $karyawan = \App\Models\Karyawan::factory()->pool()->create();

    expect(fn () => app(AjukanIzinCutiAction::class)->execute($karyawan, KategoriPengajuanIzin::Izin, '2026-09-01', '2026-09-01', 'Keperluan pribadi.'))
        ->toThrow(\Illuminate\Validation\ValidationException::class, 'Pengajuan izin/cuti mandiri belum didukung untuk pegawai pool yayasan (tanpa lembaga tetap). Silakan hubungi admin SDM untuk memprosesnya secara manual.');

    expect(\App\Domains\Sdm\Models\PengajuanIzinCuti::count())->toBe(0);
});

it('still allows a non-pool karyawan (lembaga_id set) to submit normally (regression baseline)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $karyawan = \App\Models\Karyawan::factory()->create(['lembaga_id' => $lembaga->id]);

    $pengajuan = app(AjukanIzinCutiAction::class)->execute($karyawan, KategoriPengajuanIzin::Izin, '2026-09-01', '2026-09-01', 'Keperluan pribadi.');

    expect($pengajuan)->not->toBeNull();
    expect($pengajuan->lembaga_id)->toBe($lembaga->id);
});
```

**Catatan**: tidak perlu memanggil `seedKuotaCutiWorkflowForTest_ajukan()` di 2 test baru ini. Test pertama harus gagal SEBELUM mencapai kode apa pun yang butuh workflow/role seeding (guard baru ada di baris paling awal `execute()`). Test kedua memakai kategori `Izin` (bukan `Cuti`), yang tidak melewati cabang kuota resolver sama sekali (lihat `AjukanIzinCutiAction.php:46` — cabang kuota cuma jalan kalau `$kategori === KategoriPengajuanIzin::Cuti`), jadi juga tidak butuh seeding workflow.

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Feature/Sdm/AjukanIzinCutiActionTest.php --filter="rejects a pengajuan from a pool karyawan"`
Expected: FAIL — bukan `ValidationException` yang tertangkap, melainkan `Illuminate\Database\QueryException` (SQLSTATE 23000 constraint violation) yang tidak match ekspektasi `toThrow(ValidationException::class, ...)`.

- [ ] **Step 3: Tambahkan guard di `AjukanIzinCutiAction::execute()`**

Di `app/Domains/Sdm/Actions/AjukanIzinCutiAction.php`, method `execute()` saat ini dimulai:

```php
    public function execute(
        Model $pegawai,
        KategoriPengajuanIzin $kategori,
        string $tanggalMulai,
        string $tanggalSelesai,
        string $alasan,
    ): PengajuanIzinCuti {
        if ($tanggalMulai > $tanggalSelesai) {
```

Ubah jadi (menambah guard SEBELUM validasi tanggal existing):

```php
    public function execute(
        Model $pegawai,
        KategoriPengajuanIzin $kategori,
        string $tanggalMulai,
        string $tanggalSelesai,
        string $alasan,
    ): PengajuanIzinCuti {
        if ($pegawai->lembaga_id === null) {
            throw ValidationException::withMessages([
                'pegawai' => 'Pengajuan izin/cuti mandiri belum didukung untuk pegawai pool yayasan (tanpa lembaga tetap). Silakan hubungi admin SDM untuk memprosesnya secara manual.',
            ]);
        }

        if ($tanggalMulai > $tanggalSelesai) {
```

Sisa method (`buatPengajuan()` dan seterusnya) TIDAK berubah. `ValidationException` sudah di-import di file ini (baris 15, `use Illuminate\Validation\ValidationException;`) — tidak perlu import baru.

- [ ] **Step 4: Jalankan test untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Feature/Sdm/AjukanIzinCutiActionTest.php --compact`
Expected: PASS — semua test di file ini (termasuk 2 yang baru ditambahkan DAN semua test lama yang sudah ada sebelumnya) hijau.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Sdm/Actions/AjukanIzinCutiAction.php tests/Feature/Sdm/AjukanIzinCutiActionTest.php
git commit -m "fix(sdm): cegah crash 500 saat pegawai pool yayasan mengajukan izin/cuti mandiri

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: Perbaikan HTML Rusak di Empty-State Riwayat Self-Service

**Files:**
- Modify: `resources/views/sdm/izin-cuti/index.blade.php:218-228`

**Interfaces:**
- Consumes: tidak ada (murni markup, tidak menyentuh data/logika).
- Produces: tidak ada interface baru.

- [ ] **Step 1: Baca kondisi saat ini untuk konfirmasi baris persis**

File `resources/views/sdm/izin-cuti/index.blade.php`, baris 218-229 saat ini:

```blade
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-gray-400">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-3">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-700">Belum ada pengajuan izin/cuti.</p>
                                    <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">Klik tombol "+ Ajukan Baru" di atas untuk mengajukan permohonan izin atau cuti.</p>
                        @endforelse
                    </tbody>
                </table>
```

`<td>` dan `<tr>` dibuka di baris 219-220 tapi tidak pernah ditutup sebelum `@endforelse` di baris 228.

- [ ] **Step 2: Tutup tag yang hilang**

Ganti blok di atas menjadi:

```blade
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-gray-400">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-3">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-700">Belum ada pengajuan izin/cuti.</p>
                                    <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">Klik tombol "+ Ajukan Baru" di atas untuk mengajukan permohonan izin atau cuti.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
```

- [ ] **Step 3: Verifikasi manual — tidak ada automated test untuk markup murni ini**

Proyek ini tidak punya test Feature yang meng-assert struktur HTML persis untuk halaman ini. Jalankan `php artisan serve` (atau pakai `composer run dev` kalau sudah jalan), login sebagai pegawai (guru/karyawan) yang belum pernah mengajukan izin/cuti, buka `/sdm/izin-cuti`, dan konfirmasi lewat browser DevTools (tab Elements) bahwa `<tr><td>...</td></tr>` sekarang tertutup dengan benar (sebelumnya browser auto-repair membuat ini sulit terlihat dari tampilan visual saja — harus dicek dari DOM inspector, bukan cuma dilihat sekilas).

- [ ] **Step 4: Jalankan test Feature existing yang menyentuh halaman ini (kalau ada) untuk memastikan tidak ada regresi**

Run: `vendor/bin/pest --filter="izin-cuti" --compact`
Expected: semua test yang match nama filter ini tetap PASS (perubahan murni markup, tidak ada test yang seharusnya terpengaruh).

- [ ] **Step 5: Commit**

```bash
git add resources/views/sdm/izin-cuti/index.blade.php
git commit -m "fix(sdm): tutup markup HTML yang rusak di empty-state riwayat izin/cuti pegawai

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 3: Riwayat/Arsip Admin + Pencarian "Alasan" yang Berfungsi

**Files:**
- Modify: `app/Http/Controllers/Admin/ApprovalIzinCutiController.php:21-31` (method `index()`)
- Modify: `resources/views/admin/kehadiran-sdm/izin-cuti/index.blade.php` (mapping item baris 4-21, filter card baris 90-144, stat card baris 51-53, header tabel baris 160-167, baris data baris 184-217, empty-state colspan baris 172)
- Modify: `resources/js/approval-izin-cuti-spa.js` (seluruh isi file)
- Test: `tests/Feature/Admin/ApprovalIzinCutiControllerTest.php` (tambah 1 test baru di akhir file)

**Interfaces:**
- Consumes: `App\Domains\Sdm\Actions\AjukanIzinCutiAction` (untuk setup test — sudah dipakai test lain di file yang sama), `App\Domains\Workflow\Enums\ApprovalStatus` (untuk badge status).
- Produces: `approvalIzinCutiSPA(config)` (fungsi Alpine.js yang di-import `resources/js/app.js` — TIDAK BERUBAH nama/cara pemanggilannya, hanya isi internalnya) sekarang mengekspos state baru `viewMode` (default `'menunggu'`) dan getter baru `itemsInView`, `totalPending`, menggantikan pemakaian langsung `items.length` di beberapa tempat. Setiap item di `config.items` sekarang WAJIB membawa field `alasan` (string), `statusLabel` (string), `statusTone` (string), `isDecided` (boolean) — field baru ini dikonsumsi oleh getter-getter di atas.

- [ ] **Step 1: Tulis test yang gagal — index() sekarang harus mengembalikan pengajuan Pending DAN Approved sekaligus**

Tambahkan di `tests/Feature/Admin/ApprovalIzinCutiControllerTest.php`, setelah test terakhir (`it('rejects an admin without kehadiran-sdm.izin.approve permission'...)`, baris 70) di akhir file:

```php

it('includes both active (Pending) and decided (Approved) pengajuan in the index payload (riwayat support)', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.izin.approve', 'guard_name' => 'web']);
    $kepsekRole = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $kepsekRole->givePermissionTo('kehadiran-sdm.izin.approve');
    $adminSdmRole = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $adminSdmRole->givePermissionTo('kehadiran-sdm.izin.approve');
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsek->assignRole($kepsekRole);
    $adminSdm = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $adminSdm->assignRole($adminSdmRole);

    $guruPending = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Guru Pending Test']);
    $pengajuanPending = app(AjukanIzinCutiAction::class)->execute($guruPending, KategoriPengajuanIzin::Sakit, '2026-09-01', '2026-09-01', 'Sakit demam.');

    $guruApproved = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Guru Approved Test']);
    $pengajuanApproved = app(AjukanIzinCutiAction::class)->execute($guruApproved, KategoriPengajuanIzin::Izin, '2026-09-02', '2026-09-02', 'Keperluan keluarga.');
    $this->actingAs($kepsek)->post(route('admin.kehadiran-sdm.izin-cuti.decision', $pengajuanApproved), ['action' => 'APPROVE']);
    $this->actingAs($adminSdm)->post(route('admin.kehadiran-sdm.izin-cuti.decision', $pengajuanApproved->fresh()), ['action' => 'APPROVE']);
    expect($pengajuanApproved->fresh()->approvalRequest->status)->toBe(ApprovalStatus::Approved);

    $response = $this->actingAs($kepsek)->get(route('admin.kehadiran-sdm.izin-cuti.index'));

    $response->assertOk();
    $response->assertSee('Guru Pending Test');
    $response->assertSee('Guru Approved Test');
});
```

**Catatan**: cek dulu `WorkflowDefinitionSeeder` untuk konfirmasi step 2 workflow `IZIN_CUTI_SDM` memang role `admin_sdm` (dipakai test lain di file yang sama secara implisit — `AjukanIzinCutiActionTest.php:30` mengonfirmasi step 1 adalah `kepala_sekolah`; test `test_submit_partial_approval_and_disbursement_lifecycle`-style 2-step approve di atas mengasumsikan pola 2 langkah kepsek→admin_sdm yang sama seperti test lain di `ProsesApprovalIzinCutiActionTest.php` — kalau ternyata urutan/role berbeda, sesuaikan urutan `actingAs` di step ini mengikuti apa yang benar-benar dikonfirmasi `WorkflowDefinitionSeeder`, jangan asumsikan buta.

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Feature/Admin/ApprovalIzinCutiControllerTest.php --filter="includes both active"`
Expected: FAIL — assertion `assertSee('Guru Approved Test')` gagal karena `index()` saat ini menyaring HANYA status Pending/InReview, pengajuan yang sudah Approved tidak pernah muncul di response.

- [ ] **Step 3: Ubah query `index()` di controller — ganti total filter status lama**

Di `app/Http/Controllers/Admin/ApprovalIzinCutiController.php`, method `index()` saat ini:

```php
    public function index(): View
    {
        $this->authorize('kehadiran-sdm.izin.approve');

        $daftar = PengajuanIzinCuti::with(['pegawai', 'approvalRequest.currentStep'])
            ->whereHas('approvalRequest', fn ($q) => $q->whereIn('status', [ApprovalStatus::Pending, ApprovalStatus::InReview]))
            ->latest('tanggal_mulai')
            ->get();

        return view('admin.kehadiran-sdm.izin-cuti.index', ['daftar' => $daftar]);
    }
```

Ubah jadi (GANTI baris `whereHas` yang lama, jangan ditambah di atasnya):

```php
    public function index(): View
    {
        $this->authorize('kehadiran-sdm.izin.approve');

        $daftar = PengajuanIzinCuti::with(['pegawai', 'approvalRequest.currentStep'])
            ->whereHas('approvalRequest')
            ->latest('tanggal_mulai')
            ->get();

        return view('admin.kehadiran-sdm.izin-cuti.index', ['daftar' => $daftar]);
    }
```

`ApprovalStatus` masih dipakai method `show()` di file yang sama (baris 41, `in_array($approvalRequest->status->value, ['pending', 'in_review'], true)`) — JANGAN hapus `use App\Domains\Workflow\Enums\ApprovalStatus;` di baris import, itu masih dibutuhkan.

- [ ] **Step 4: Ubah mapping item di view admin — tambah field `alasan`, `statusLabel`, `statusTone`, `isDecided`**

Di `resources/views/admin/kehadiran-sdm/izin-cuti/index.blade.php`, baris 1-22 saat ini:

```blade
{{-- resources/views/admin/kehadiran-sdm/izin-cuti/index.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0" x-data="approvalIzinCutiSPA({
        items: @js($daftar->map(function ($item) {
            $k = $item->kategori->value;
            $class = match($k) {
                'cuti' => 'bg-blue-100 text-blue-800',
                'sakit' => 'bg-rose-100 text-rose-800',
                default => 'bg-amber-100 text-amber-800',
            };
            return [
                'id' => $item->id,
                'nama' => $item->pegawai->nama ?? '—',
                'kategori' => $k,
                'kategoriLabel' => $item->kategori->label(),
                'kategoriClass' => $class,
                'periode' => $item->tanggal_mulai->format('d M Y') . ' — ' . $item->tanggal_selesai->format('d M Y'),
                'step' => $item->approvalRequest?->currentStep?->step_name ?? '—',
                'showUrl' => route('admin.kehadiran-sdm.izin-cuti.show', $item),
            ];
        })->values()->all()),
    })">
```

Ubah jadi:

```blade
{{-- resources/views/admin/kehadiran-sdm/izin-cuti/index.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0" x-data="approvalIzinCutiSPA({
        items: @js($daftar->map(function ($item) {
            $k = $item->kategori->value;
            $class = match($k) {
                'cuti' => 'bg-blue-100 text-blue-800',
                'sakit' => 'bg-rose-100 text-rose-800',
                default => 'bg-amber-100 text-amber-800',
            };
            $status = $item->approvalRequest?->status;
            return [
                'id' => $item->id,
                'nama' => $item->pegawai->nama ?? '—',
                'alasan' => $item->alasan,
                'kategori' => $k,
                'kategoriLabel' => $item->kategori->label(),
                'kategoriClass' => $class,
                'periode' => $item->tanggal_mulai->format('d M Y') . ' — ' . $item->tanggal_selesai->format('d M Y'),
                'step' => $item->approvalRequest?->currentStep?->step_name ?? '—',
                'statusLabel' => $status?->label() ?? '—',
                'statusTone' => $status?->badgeTone() ?? 'slate',
                'isDecided' => $status !== null && ! in_array($status, [\App\Domains\Workflow\Enums\ApprovalStatus::Pending, \App\Domains\Workflow\Enums\ApprovalStatus::InReview], true),
                'showUrl' => route('admin.kehadiran-sdm.izin-cuti.show', $item),
            ];
        })->values()->all()),
    })">
```

- [ ] **Step 5: Tambah toggle tab Menunggu/Riwayat, sesuaikan grid kolom filter card**

Di file yang sama, baris 90-144 (Filter Card) saat ini:

```blade
        {{-- Filter Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
                {{-- Search Input --}}
                <div class="lg:col-span-6">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Pegawai / Alasan</label>
                    <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
                        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input x-model="searchQuery" type="text" placeholder="Ketik nama pegawai..." class="w-full border-0 bg-transparent p-0 text-xs text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                </div>

                {{-- Pill Tabs Filters --}}
                <div class="lg:col-span-6 flex items-center justify-start lg:justify-end gap-2 overflow-x-auto scrollbar-none pb-1 sm:pb-0">
                    <button 
                        @click="activeFilter = 'semua'" 
                        type="button" 
                        :class="activeFilter === 'semua' ? 'bg-brand-50 font-semibold text-brand-600 border-brand-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Semua</span>
                        <span :class="activeFilter === 'semua' ? 'bg-brand-100 text-brand-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="items.length"></span>
                    </button>
                    <button 
                        @click="activeFilter = 'cuti'" 
                        type="button" 
                        :class="activeFilter === 'cuti' ? 'bg-blue-50 font-semibold text-blue-700 border-blue-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Cuti</span>
                        <span :class="activeFilter === 'cuti' ? 'bg-blue-100 text-blue-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countCuti"></span>
                    </button>
                    <button 
                        @click="activeFilter = 'sakit'" 
                        type="button" 
                        :class="activeFilter === 'sakit' ? 'bg-rose-50 font-semibold text-rose-700 border-rose-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Sakit</span>
                        <span :class="activeFilter === 'sakit' ? 'bg-rose-100 text-rose-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countSakit"></span>
                    </button>
                    <button 
                        @click="activeFilter = 'izin'" 
                        type="button" 
                        :class="activeFilter === 'izin' ? 'bg-amber-50 font-semibold text-amber-700 border-amber-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Izin</span>
                        <span :class="activeFilter === 'izin' ? 'bg-amber-100 text-amber-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countIzin"></span>
                    </button>
                </div>
            </div>
        </div>
```

Ubah jadi (tambah blok toggle tab sebagai kolom pertama, `lg:col-span-6` search jadi `lg:col-span-5`, `lg:col-span-6` pill jadi `lg:col-span-4`, ditambah `lg:col-span-3` untuk toggle — total tetap 12):

```blade
        {{-- Filter Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
                {{-- View Mode Toggle --}}
                <div class="lg:col-span-3">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Tampilan</label>
                    <div class="flex items-center gap-1 rounded-xl border border-gray-200 bg-gray-50 p-1">
                        <button
                            @click="viewMode = 'menunggu'"
                            type="button"
                            :class="viewMode === 'menunggu' ? 'bg-white shadow-2xs font-semibold text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                            class="flex-1 rounded-lg px-3 py-1.5 text-xs transition-all"
                        >Menunggu</button>
                        <button
                            @click="viewMode = 'riwayat'"
                            type="button"
                            :class="viewMode === 'riwayat' ? 'bg-white shadow-2xs font-semibold text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                            class="flex-1 rounded-lg px-3 py-1.5 text-xs transition-all"
                        >Riwayat</button>
                    </div>
                </div>

                {{-- Search Input --}}
                <div class="lg:col-span-5">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Pegawai / Alasan</label>
                    <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
                        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input x-model="searchQuery" type="text" placeholder="Ketik nama pegawai atau alasan..." class="w-full border-0 bg-transparent p-0 text-xs text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                </div>

                {{-- Pill Tabs Filters --}}
                <div class="lg:col-span-4 flex items-center justify-start lg:justify-end gap-2 overflow-x-auto scrollbar-none pb-1 sm:pb-0">
                    <button 
                        @click="activeFilter = 'semua'" 
                        type="button" 
                        :class="activeFilter === 'semua' ? 'bg-brand-50 font-semibold text-brand-600 border-brand-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Semua</span>
                        <span :class="activeFilter === 'semua' ? 'bg-brand-100 text-brand-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="itemsInView.length"></span>
                    </button>
                    <button 
                        @click="activeFilter = 'cuti'" 
                        type="button" 
                        :class="activeFilter === 'cuti' ? 'bg-blue-50 font-semibold text-blue-700 border-blue-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Cuti</span>
                        <span :class="activeFilter === 'cuti' ? 'bg-blue-100 text-blue-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countCuti"></span>
                    </button>
                    <button 
                        @click="activeFilter = 'sakit'" 
                        type="button" 
                        :class="activeFilter === 'sakit' ? 'bg-rose-50 font-semibold text-rose-700 border-rose-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Sakit</span>
                        <span :class="activeFilter === 'sakit' ? 'bg-rose-100 text-rose-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countSakit"></span>
                    </button>
                    <button 
                        @click="activeFilter = 'izin'" 
                        type="button" 
                        :class="activeFilter === 'izin' ? 'bg-amber-50 font-semibold text-amber-700 border-amber-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" 
                        class="px-3.5 py-1.5 rounded-lg text-xs border transition-all whitespace-nowrap flex items-center gap-2"
                    >
                        <span>Izin</span>
                        <span :class="activeFilter === 'izin' ? 'bg-amber-100 text-amber-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countIzin"></span>
                    </button>
                </div>
            </div>
        </div>
```

- [ ] **Step 6: Ubah stat card "Menunggu Approval" — pakai `totalPending`, bukan `items.length`**

Di file yang sama, baris 51-56 (di dalam kartu statistik pertama) saat ini:

```blade
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Menunggu Approval</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="items.length"></p>
                    </div>
```

Ubah jadi:

```blade
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Menunggu Approval</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight" x-text="totalPending"></p>
                    </div>
```

- [ ] **Step 7: Tambah kolom "Status" di header tabel dan baris data, ubah colspan empty-state**

Di file yang sama, header tabel baris 160-167 saat ini:

```blade
                        <tr class="border-b border-gray-100 bg-gray-50/50 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3 w-28 text-center">Aksi</th>
                            <th class="px-5 py-3">Pegawai</th>
                            <th class="px-5 py-3">Kategori</th>
                            <th class="px-5 py-3">Periode Tanggal</th>
                            <th class="px-5 py-3">Langkah Saat Ini</th>
                        </tr>
```

Ubah jadi:

```blade
                        <tr class="border-b border-gray-100 bg-gray-50/50 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3 w-28 text-center">Aksi</th>
                            <th class="px-5 py-3">Pegawai</th>
                            <th class="px-5 py-3">Kategori</th>
                            <th class="px-5 py-3">Periode Tanggal</th>
                            <th class="px-5 py-3">Langkah Saat Ini</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
```

Empty-state `<template x-if="filteredItems.length === 0">` baris 170-182, ubah `colspan="5"` jadi `colspan="6"`:

```blade
                        <template x-if="filteredItems.length === 0">
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-gray-400">
```

(Isi di dalamnya tidak berubah — cuma atribut `colspan`.)

Baris data `<template x-for="item in filteredItems">`, sel terakhir (`step`, baris 208-215) saat ini:

```blade
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700 font-medium">
                                        <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                        </svg>
                                        <span x-text="item.step"></span>
                                    </span>
                                </td>
                            </tr>
                        </template>
```

Ubah jadi (tambah 1 `<td>` baru untuk Status setelah sel step):

```blade
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700 font-medium">
                                        <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                        </svg>
                                        <span x-text="item.step"></span>
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span :class="'bg-' + item.statusTone + '-100 text-' + item.statusTone + '-800'" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold" x-text="item.statusLabel"></span>
                                </td>
                            </tr>
                        </template>
```

- [ ] **Step 8: Ganti seluruh isi `resources/js/approval-izin-cuti-spa.js`**

Isi file saat ini:

```js
export function approvalIzinCutiSPA(config) {
    return {
        items: config.items ?? [],
        searchQuery: '',
        activeFilter: 'semua',

        get filteredItems() {
            return this.items.filter((item) => {
                const matchSearch = item.nama.toLowerCase().includes(this.searchQuery.toLowerCase());
                if (this.activeFilter === 'semua') return matchSearch;
                return matchSearch && item.kategori === this.activeFilter;
            });
        },

        get countCuti() {
            return this.items.filter((i) => i.kategori === 'cuti').length;
        },

        get countSakit() {
            return this.items.filter((i) => i.kategori === 'sakit').length;
        },

        get countIzin() {
            return this.items.filter((i) => i.kategori === 'izin').length;
        },

        get countDispensasi() {
            return this.items.filter((i) => ['izin', 'sakit'].includes(i.kategori)).length;
        },
    };
}
```

Ganti total isinya jadi:

```js
export function approvalIzinCutiSPA(config) {
    return {
        items: config.items ?? [],
        searchQuery: '',
        activeFilter: 'semua',
        viewMode: 'menunggu',

        get itemsInView() {
            return this.items.filter((item) => this.viewMode === 'menunggu' ? !item.isDecided : item.isDecided);
        },

        get filteredItems() {
            return this.itemsInView.filter((item) => {
                const query = this.searchQuery.toLowerCase();
                const matchSearch = item.nama.toLowerCase().includes(query) || item.alasan.toLowerCase().includes(query);
                const matchFilter = this.activeFilter === 'semua' || item.kategori === this.activeFilter;
                return matchSearch && matchFilter;
            });
        },

        get totalPending() {
            return this.items.filter((i) => !i.isDecided).length;
        },

        get countCuti() {
            return this.itemsInView.filter((i) => i.kategori === 'cuti').length;
        },

        get countSakit() {
            return this.itemsInView.filter((i) => i.kategori === 'sakit').length;
        },

        get countIzin() {
            return this.itemsInView.filter((i) => i.kategori === 'izin').length;
        },

        get countDispensasi() {
            return this.itemsInView.filter((i) => ['izin', 'sakit'].includes(i.kategori)).length;
        },
    };
}
```

**Ini menggabungkan 2 perbaikan sekaligus (pencarian alasan + toggle riwayat)** karena keduanya menyentuh `filteredItems` di file JS yang sama — mengerjakannya terpisah akan membuat 1 patch menimpa yang lain. `countCuti`/`countSakit`/`countIzin`/`countDispensasi` SEKARANG dihitung dari `itemsInView` (ikut `viewMode` aktif), BUKAN dari `items` mentah seperti sebelumnya — ini perubahan perilaku yang disengaja (lihat Global Constraints).

- [ ] **Step 9: Jalankan test untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Feature/Admin/ApprovalIzinCutiControllerTest.php --compact`
Expected: PASS — semua test di file ini (3 test lama + 1 test baru) hijau.

- [ ] **Step 10: Build asset frontend supaya perubahan JS ter-bundle**

Run: `npm run build`
Expected: build sukses tanpa error (memastikan `approval-izin-cuti-spa.js` yang baru ter-compile ke bundle yang benar-benar dipakai browser).

- [ ] **Step 11: Verifikasi manual — toggle tab dan pencarian alasan, tidak ada automated test JS di proyek ini**

Login sebagai admin/kepala sekolah dengan permission `kehadiran-sdm.izin.approve`, buka `/admin/kehadiran-sdm/izin-cuti`. Konfirmasi lewat browser:
1. Tab "Menunggu" (default aktif) hanya menampilkan pengajuan Pending/InReview — sama seperti perilaku sebelumnya.
2. Klik tab "Riwayat" — pengajuan yang sudah Approved/Rejected/Cancelled/RevisionRequired muncul, dengan badge Status yang sesuai warnanya (hijau untuk Disetujui, merah untuk Ditolak, dst).
3. Stat card "Menunggu Approval" di atas TETAP menunjukkan angka yang sama baik di tab Menunggu maupun tab Riwayat (tidak berubah saat pindah tab).
4. Ketik sebagian teks dari `alasan` salah satu pengajuan (bukan nama pegawai) di kolom pencarian — pengajuan itu muncul di hasil filter.
5. Pill kategori (Semua/Cuti/Sakit/Izin) angkanya berubah mengikuti tab yang aktif (mis. pill "Cuti" di tab Riwayat menunjukkan jumlah cuti yang SUDAH diputuskan, beda dengan angka di tab Menunggu).

- [ ] **Step 12: Commit**

```bash
git add app/Http/Controllers/Admin/ApprovalIzinCutiController.php resources/views/admin/kehadiran-sdm/izin-cuti/index.blade.php resources/js/approval-izin-cuti-spa.js tests/Feature/Admin/ApprovalIzinCutiControllerTest.php
git commit -m "feat(sdm): tambah tab riwayat admin izin/cuti + perbaiki pencarian alasan yang tidak berfungsi

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 4: Regresi Penutup

**Files:**
- Tidak ada file yang dimodifikasi — task ini murni verifikasi.

**Interfaces:**
- Consumes: seluruh perubahan dari Task 1-3.
- Produces: tidak ada.

- [ ] **Step 1: Jalankan seluruh test SDM**

Run: `vendor/bin/pest tests/Feature/Sdm tests/Feature/Admin/ApprovalIzinCutiControllerTest.php --compact`
Expected: semua PASS, tidak ada yang gagal.

- [ ] **Step 2: Jalankan Pint pada semua file PHP yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` (atau, kalau ada perbaikan style otomatis diterapkan, jalankan lagi sampai hasilnya `passed`).

- [ ] **Step 3: Jalankan full test suite proyek untuk memastikan tidak ada regresi lintas-domain**

Run: `php artisan test --compact`
Expected: HANYA 3 kegagalan pre-existing yang sudah dikenal sepanjang sesi ini (`Tests\Unit\M3DemoDataSeederTest` x2, `Tests\Feature\Akademik\SubjekTenantValidationTest`) yang muncul. KALAU ADA kegagalan lain di luar 3 itu — STOP, jangan lanjut, laporkan detail kegagalannya (nama test, pesan error, stack trace) alih-alih mengasumsikan itu juga pre-existing.

- [ ] **Step 4: Verifikasi manual dev-server untuk 2 perubahan UI murni (rekap, sudah dilakukan per-task tapi dikonfirmasi ulang di sini sebagai bagian dari checklist penutup)**

Konfirmasi ulang (boleh screenshot untuk laporan handoff kalau relevan):
- Task 2: markup HTML empty-state riwayat pegawai (`/sdm/izin-cuti` saat kosong) sudah valid, tidak ada elemen `<tr>`/`<td>` yang tidak tertutup di DOM inspector.
- Task 3: toggle Menunggu/Riwayat dan pencarian alasan di `/admin/kehadiran-sdm/izin-cuti` berfungsi sesuai 5 poin verifikasi di Task 3 Step 11.

- [ ] **Step 5: Commit penutup (kalau ada file yang berubah dari Pint di Step 2 yang belum ter-commit)**

```bash
git status
```

Kalau ada perubahan tersisa dari auto-fix Pint yang belum di-commit di task-task sebelumnya:

```bash
git add -u
git commit -m "style(sdm): rapikan format Pint hasil perbaikan audit izin/cuti

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

Kalau tidak ada perubahan tersisa (working tree bersih), tidak perlu commit apa pun di step ini.

---

## Self-Review — Putaran 1 (cakupan spec + placeholder + konsistensi tipe)

**Cakupan spec**: §2.1 (crash pool) → Task 1. §2.2 (HTML rusak) → Task 2. §2.3+§2.4 (pencarian alasan + riwayat admin, digabung sesuai instruksi spec) → Task 3. §4 (pengujian wajib) → tersebar ke Step test di tiap task + Task 4 regresi penutup. §5 (struktur task) → diikuti persis, 4 task dengan pengelompokan yang sama. §6 (item sengaja di luar scope) → tidak ada task yang mencoba mewire `RevisionRequired`, menambah pagination, atau redesain workflow pool karyawan — dikonfirmasi tidak ada gap.

**Placeholder scan**: tidak ditemukan "TBD"/"TODO"/"tambahkan validasi yang sesuai" di manapun — setiap step code block berisi kode lengkap yang bisa langsung disalin, bukan deskripsi tanpa isi.

**Konsistensi tipe**: field item (`alasan`, `statusLabel`, `statusTone`, `isDecided`) yang diperkenalkan di Task 3 Step 4 (mapping PHP) dipakai persis dengan nama yang sama di Task 3 Step 8 (JS `filteredItems`, `itemsInView`) dan Task 3 Step 7 (Blade `item.statusTone`, `item.statusLabel`) — tidak ada perbedaan penamaan antar step.

## Self-Review — Putaran 2 (verifikasi terhadap kode aktual proyek)

- Dikonfirmasi ulang `ValidationException` sudah di-import di `AjukanIzinCutiAction.php` (baris 15) — Task 1 Step 3 tidak perlu menambah `use` baru.
- Dikonfirmasi ulang `ApprovalStatus` masih dipakai `show()` method di `ApprovalIzinCutiController.php` (baris 41) setelah `index()` diubah di Task 3 Step 3 — ditambahkan catatan eksplisit di step itu supaya implementer TIDAK menghapus import yang masih dipakai.
- Dikonfirmasi ulang `Karyawan::factory()->pool()` benar-benar ADA di `database/factories/KaryawanFactory.php:61-66` — dipakai di Task 1 test tanpa perlu membuat state factory baru.
- Ditambahkan catatan verifikasi eksplisit di Task 3 Step 1 soal urutan role approval 2-step (`kepala_sekolah` → `admin_sdm`) yang mungkin perlu implementer cek ulang ke `WorkflowDefinitionSeeder` kalau ternyata beda dari asumsi — supaya tidak "buta" mengikuti kode contoh dari test lain tanpa verifikasi.

## Self-Review — Putaran 3 (dependency antar-task & urutan eksekusi)

- Dikonfirmasi Task 1 dan Task 2 benar-benar tidak overlap file sama sekali (Task 1 menyentuh `AjukanIzinCutiAction.php` + test-nya; Task 2 menyentuh `sdm/izin-cuti/index.blade.php` self-service pegawai — BEDA file dari `admin/kehadiran-sdm/izin-cuti/index.blade.php` yang disentuh Task 3). Aman dikerjakan paralel/urutan bebas.
- Task 3 ditambahkan Step 10 (`npm run build`) yang sebelumnya tidak eksplisit di spec — ditambahkan karena perubahan JS Alpine di proyek ini butuh proses build/bundling sebelum berlaku di browser (dikonfirmasi dari `.ai/rules/js.md` soal konvensi file JS terpisah yang di-registrasi via `app.js`) — tanpa step ini, verifikasi manual Task 3 Step 11 akan menguji bundle LAMA, bukan kode yang baru diubah, dan implementer bisa salah menyimpulkan perubahan tidak berfungsi.
- Task 4 dikonfirmasi memang harus terakhir (butuh Task 1-3 selesai untuk full suite yang valid) — tidak ada task lain yang bergantung pada Task 4.
