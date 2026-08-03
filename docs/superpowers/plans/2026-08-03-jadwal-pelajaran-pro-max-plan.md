# Jadwal Pelajaran Pro-Max Refactor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform the academic lesson scheduling module (`JadwalPelajaran`) into an executive Pro-Max user experience featuring 1-click schedule duplication with teacher collision avoidance, SPA pop-up modals for fast inline management without page redirects, and an interactive weekly timetable roster grid view.

**Architecture:** Extend `JadwalPelajaranController` with a transactional `duplicate` endpoint that correlates source class schedule slots with target class patterns by `(hari, urutan)` while actively screening for teacher double-booking. Upgrade `index.blade.php` and `_daftar.blade.php` by injecting three Alpine.js modal partials (`_modal-create`, `_modal-edit`, `_modal-duplicate`) and a reactive toggle switcher displaying either daily sequential lists or an intuitive interactive weekly roster grid table.

**Tech Stack:** Laravel 11, PHP 8.3, Eloquent ORM, Blade, Alpine.js, Tailwind CSS, Pest/PHPUnit.

## Global Constraints

- **Multi-Tenant Isolation:** All operations (listing, creating, editing, and duplicating) MUST strictly adhere to tenant boundaries (`$kelas->lembaga_id`). Never expose or alter records from another institution.
- **TDD Requirement:** All code modifications must proceed through failing tests first (RED), followed by implementation (GREEN), and verified with zero regressions across the existing 44 passing tests in `JadwalPelajaranCrudTest`.
- **Database & Testing Resilience:** Before invoking `php artisan test`, always check and start the local Laragon MySQL demon process (`mysqld.exe`) if offline.
- **Blade & Alpine Compatibility:** Do NOT put Blade `@js()` calls inside Alpine attributes starting with `@` (e.g., `@click`, `@submit`) to prevent syntax compilation errors; use `->toJson()` or clean variable binding instead.

---

### Task 1: Backend 1-Click Schedule Duplication (Endpoint, Tenant Scope, & Collision Protection)

**Files:**
- Modify: `routes/admin.php:175-190`
- Modify: `app/Http/Controllers/Admin/JadwalPelajaranController.php:315-350`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php:1250-1350`

**Interfaces:**
- Consumes: Existing `JadwalPelajaran`, `Kelas`, `Semester`, and `JamPelajaran` Eloquent models and tenant access rules in `JadwalPelajaranController`.
- Produces: `POST /admin/jadwal-pelajaran/duplicate` (named `admin.jadwal-pelajaran.duplicate`) returning JSON/Redirect with clear diagnostic feedback on total replicated sessions and skipped collisions.

- [ ] **Step 1: Write the failing test for schedule duplication and collision avoidance**

Add the following automated test cases to `tests/Feature/Admin/JadwalPelajaranCrudTest.php`:
```php
it('duplicates jadwal pelajaran from source kelas and semester to target kelas and semester while skipping teacher collisions', function () {
    $admin = createUserWithRole('admin_sekolah', ['jadwal-pelajaran.kelola'], $this->lembaga->id);

    $pola = \App\Models\PolaJam::create([
        'lembaga_id' => $this->lembaga->id,
        'nama' => 'Pola Standar',
    ]);
    $slot1 = \App\Models\JamPelajaran::create([
        'pola_jam_id' => $pola->id,
        'hari' => \App\Enums\Hari::Senin->value,
        'urutan' => 1,
        'label' => 'Jam 1',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:45',
        'is_pelajaran' => true,
    ]);
    $slot2 = \App\Models\JamPelajaran::create([
        'pola_jam_id' => $pola->id,
        'hari' => \App\Enums\Hari::Senin->value,
        'urutan' => 2,
        'label' => 'Jam 2',
        'jam_mulai' => '07:45',
        'jam_selesai' => '08:30',
        'is_pelajaran' => true,
    ]);
    
    // Bind pola to classes
    $sourceKelas = $this->kelas;
    $targetKelas = \App\Models\Kelas::create([
        'lembaga_id' => $this->lembaga->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'nama' => 'Kelas Target Copy',
    ]);
    $sourceKelas->polaJam()->attach($pola->id);
    $targetKelas->polaJam()->attach($pola->id);

    $mapel1 = \App\Models\MataPelajaran::create(['lembaga_id' => $this->lembaga->id, 'nama' => 'Matematika', 'kode' => 'MTK']);
    $mapel2 = \App\Models\MataPelajaran::create(['lembaga_id' => $this->lembaga->id, 'nama' => 'Fisika', 'kode' => 'FIS']);

    // Create source schedules
    \App\Models\JadwalPelajaran::create([
        'kelas_id' => $sourceKelas->id,
        'semester_id' => $this->semester->id,
        'jam_pelajaran_id' => $slot1->id,
        'mata_pelajaran_id' => $mapel1->id,
        'guru_id' => $this->guru->id,
    ]);
    \App\Models\JadwalPelajaran::create([
        'kelas_id' => $sourceKelas->id,
        'semester_id' => $this->semester->id,
        'jam_pelajaran_id' => $slot2->id,
        'mata_pelajaran_id' => $mapel2->id,
        'guru_id' => $this->guru->id,
    ]);

    // Create a pre-existing schedule for another class in target semester that uses the exact same guru at slot 1
    $otherKelas = \App\Models\Kelas::create([
        'lembaga_id' => $this->lembaga->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'nama' => 'Kelas Lain Bentrok',
    ]);
    \App\Models\JadwalPelajaran::create([
        'kelas_id' => $otherKelas->id,
        'semester_id' => $this->semester->id,
        'jam_pelajaran_id' => $slot1->id,
        'mata_pelajaran_id' => $mapel1->id,
        'guru_id' => $this->guru->id,
    ]);

    $response = $this->actingAs($admin)->postJson(route('admin.jadwal-pelajaran.duplicate'), [
        'source_kelas_id' => $sourceKelas->id,
        'source_semester_id' => $this->semester->id,
        'target_kelas_id' => $targetKelas->id,
        'target_semester_id' => $this->semester->id,
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'copied_count' => 1,
            'skipped_count' => 1,
        ]);

    // Check database state
    $this->assertDatabaseHas('jadwal_pelajaran', [
        'kelas_id' => $targetKelas->id,
        'jam_pelajaran_id' => $slot2->id,
        'mata_pelajaran_id' => $mapel2->id,
    ]);
    $this->assertDatabaseMissing('jadwal_pelajaran', [
        'kelas_id' => $targetKelas->id,
        'jam_pelajaran_id' => $slot1->id,
    ]);
});

it('rejects schedule duplication when target class belongs to a different tenant', function () {
    $admin = createUserWithRole('admin_sekolah', ['jadwal-pelajaran.kelola'], $this->lembaga->id);

    $otherLembaga = \App\Models\Lembaga::create(['nama' => 'Lembaga Asing']);
    $otherTahun = \App\Models\TahunAjaran::create(['lembaga_id' => $otherLembaga->id, 'nama' => '2025/2026 Asing', 'status_aktif' => true]);
    $alienKelas = \App\Models\Kelas::create(['lembaga_id' => $otherLembaga->id, 'tahun_ajaran_id' => $otherTahun->id, 'nama' => 'Kelas Asing']);

    $response = $this->actingAs($admin)->postJson(route('admin.jadwal-pelajaran.duplicate'), [
        'source_kelas_id' => $this->kelas->id,
        'source_semester_id' => $this->semester->id,
        'target_kelas_id' => $alienKelas->id,
        'target_semester_id' => $this->semester->id,
    ]);

    $response->assertStatus(404);
});
```

- [ ] **Step 2: Run test to verify it fails (TDD RED)**

Run:
```bash
If (!(Get-Process mysqld -ErrorAction SilentlyContinue)) { Start-Process -FilePath "D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe" -ArgumentList "--defaults-file=D:\laragon\bin\mysql\mysql-8.0.30-winx64\my.ini" -WindowStyle Hidden ; Start-Sleep -Seconds 2 } ; php artisan test --filter="duplicates jadwal pelajaran from source kelas"
```
Expected: FAIL due to missing route `admin.jadwal-pelajaran.duplicate` or 404/500 error.

- [ ] **Step 3: Implement minimal duplication endpoint with collision avoidance**

Modify `routes/admin.php` inside the `jadwal-pelajaran` prefix/controller block:
```php
Route::post('jadwal-pelajaran/duplicate', [JadwalPelajaranController::class, 'duplicate'])->name('jadwal-pelajaran.duplicate');
```

Modify `app/Http/Controllers/Admin/JadwalPelajaranController.php` to add the `duplicate` method:
```php
    public function duplicate(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $data = $request->validate([
            'source_kelas_id' => ['required', 'integer'],
            'source_semester_id' => ['required', 'integer'],
            'target_kelas_id' => ['required', 'integer', 'different:source_kelas_id'],
            'target_semester_id' => ['required', 'integer'],
        ]);

        $sourceKelas = Kelas::with('polaJam')->find($data['source_kelas_id']);
        $targetKelas = Kelas::with('polaJam')->find($data['target_kelas_id']);
        $sourceSemester = Semester::find($data['source_semester_id']);
        $targetSemester = Semester::find($data['target_semester_id']);

        abort_if(! $sourceKelas || ! $targetKelas || ! $sourceSemester || ! $targetSemester, 404);
        
        $user = $request->user();
        $lembagaId = $user->active_lembaga_id ?: ($user->lembaga_id ?: null);
        
        if ($lembagaId) {
            abort_if($sourceKelas->lembaga_id !== $lembagaId || $targetKelas->lembaga_id !== $lembagaId, 404);
            abort_if($sourceSemester->tahunAjaran?->lembaga_id !== $lembagaId || $targetSemester->tahunAjaran?->lembaga_id !== $lembagaId, 404);
        } else {
            abort_if($sourceKelas->lembaga_id !== $targetKelas->lembaga_id, 404);
        }

        $targetPola = $targetKelas->polaJam->first();
        if (! $targetPola) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kelas tujuan belum memiliki ikatan Pola Jam.',
            ], 422);
        }

        $targetSlots = JamPelajaran::where('pola_jam_id', $targetPola->id)->get()->keyBy(function ($slot) {
            return ($slot->hari instanceof \UnitEnum ? $slot->hari->value : $slot->hari) . '-' . $slot->urutan;
        });

        $sourceJadwals = JadwalPelajaran::with('jamPelajaran')
            ->where('kelas_id', $sourceKelas->id)
            ->where('semester_id', $sourceSemester->id)
            ->get();

        $copiedCount = 0;
        $skippedCount = 0;

        DB::transaction(function () use ($sourceJadwals, $targetSlots, $targetKelas, $targetSemester, &$copiedCount, &$skippedCount) {
            foreach ($sourceJadwals as $sj) {
                if (! $sj->jamPelajaran) {
                    $skippedCount++;
                    continue;
                }
                $key = ($sj->jamPelajaran->hari instanceof \UnitEnum ? $sj->jamPelajaran->hari->value : $sj->jamPelajaran->hari) . '-' . $sj->jamPelajaran->urutan;
                $targetSlot = $targetSlots->get($key);
                
                if (! $targetSlot) {
                    $skippedCount++;
                    continue;
                }

                // Check if target class already has a schedule for this slot
                $classCollision = JadwalPelajaran::where('kelas_id', $targetKelas->id)
                    ->where('semester_id', $targetSemester->id)
                    ->where('jam_pelajaran_id', $targetSlot->id)
                    ->exists();

                if ($classCollision) {
                    $skippedCount++;
                    continue;
                }

                // Check if teacher double booked in another class at same time slot
                $teacherCollision = JadwalPelajaran::where('guru_id', $sj->guru_id)
                    ->where('semester_id', $targetSemester->id)
                    ->where('jam_pelajaran_id', $targetSlot->id)
                    ->exists();

                if ($teacherCollision) {
                    $skippedCount++;
                    continue;
                }

                JadwalPelajaran::create([
                    'kelas_id' => $targetKelas->id,
                    'semester_id' => $targetSemester->id,
                    'jam_pelajaran_id' => $targetSlot->id,
                    'mata_pelajaran_id' => $sj->mata_pelajaran_id,
                    'guru_id' => $sj->guru_id,
                ]);
                
                $copiedCount++;
            }
        });

        $message = "Berhasil menyalin {$copiedCount} sesi jadwal.";
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} sesi dilewati karena bentrok waktu atau tidak sesuai pola jam.";
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'copied_count' => $copiedCount,
                'skipped_count' => $skippedCount,
            ]);
        }

        return redirect()->back()->with('status', $message);
    }
```

- [ ] **Step 4: Run test to verify it passes (TDD GREEN)**

Run:
```bash
If (!(Get-Process mysqld -ErrorAction SilentlyContinue)) { Start-Process -FilePath "D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe" -ArgumentList "--defaults-file=D:\laragon\bin\mysql\mysql-8.0.30-winx64\my.ini" -WindowStyle Hidden ; Start-Sleep -Seconds 2 } ; php artisan test --filter="JadwalPelajaranCrudTest"
```
Expected: PASS all 46 tests.

- [ ] **Step 5: Commit changes**

Run:
```bash
git add routes/admin.php app/Http/Controllers/Admin/JadwalPelajaranController.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat(akademik): implement 1-click schedule duplication with active teacher collision avoidance"
```

---

### Task 2: SPA Modal Transformation (Create, Edit, and Duplicate Modals Without Redirection)

**Files:**
- Create: `resources/views/admin/jadwal-pelajaran/_modal-create.blade.php`
- Create: `resources/views/admin/jadwal-pelajaran/_modal-edit.blade.php`
- Create: `resources/views/admin/jadwal-pelajaran/_modal-duplicate.blade.php`
- Modify: `resources/views/admin/jadwal-pelajaran/index.blade.php:20-80`
- Modify: `resources/views/admin/jadwal-pelajaran/_daftar.blade.php:70-80`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php:1351-1420`

**Interfaces:**
- Consumes: Existing store routes (`admin.jadwal-pelajaran.store` & `update`) and newly created `admin.jadwal-pelajaran.duplicate`.
- Produces: Pop-up modal forms for adding, editing, and duplicating schedules without navigating away from the active filter selection.

- [ ] **Step 1: Write the failing test for SPA modal integration in schedule index and list**

Add the following automated assertion to `tests/Feature/Admin/JadwalPelajaranCrudTest.php`:
```php
it('renders SPA modal triggers and inclusion containers on schedule index and ajax fragment', function () {
    $admin = createUserWithRole('admin_sekolah', ['jadwal-pelajaran.kelola'], $this->lembaga->id);

    $response = $this->actingAs($admin)->get(route('admin.jadwal-pelajaran.index'));
    
    $response->assertOk();
    $response->assertSee('showModalCreate');
    $response->assertSee('showModalEdit');
    $response->assertSee('showModalDuplicate');
    $response->assertSee('Salin Jadwal');

    // Test ajax fragment
    $pola = \App\Models\PolaJam::create(['lembaga_id' => $this->lembaga->id, 'nama' => 'Pola Test AJAX']);
    $slot = \App\Models\JamPelajaran::create([
        'pola_jam_id' => $pola->id,
        'hari' => \App\Enums\Hari::Senin->value,
        'urutan' => 1,
        'label' => 'Jam 1',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:45',
        'is_pelajaran' => true,
    ]);
    $this->kelas->polaJam()->attach($pola->id);
    $mapel = \App\Models\MataPelajaran::create(['lembaga_id' => $this->lembaga->id, 'nama' => 'Kimia']);
    
    $jadwal = \App\Models\JadwalPelajaran::create([
        'kelas_id' => $this->kelas->id,
        'semester_id' => $this->semester->id,
        'jam_pelajaran_id' => $slot->id,
        'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $this->guru->id,
    ]);

    $ajaxResponse = $this->actingAs($admin)->get(route('admin.jadwal-pelajaran.index', [
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'semester_id' => $this->semester->id,
        'kelas_id' => $this->kelas->id,
    ]), ['X-Requested-With' => 'XMLHttpRequest']);

    $ajaxResponse->assertOk();
    $ajaxResponse->assertSee('openEditJadwal');
    $ajaxResponse->assertDontSee('href="' . route('admin.jadwal-pelajaran.edit', $jadwal) . '"', false);
});
```

- [ ] **Step 2: Run test to verify it fails (TDD RED)**

Run:
```bash
If (!(Get-Process mysqld -ErrorAction SilentlyContinue)) { Start-Process -FilePath "D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe" -ArgumentList "--defaults-file=D:\laragon\bin\mysql\mysql-8.0.30-winx64\my.ini" -WindowStyle Hidden ; Start-Sleep -Seconds 2 } ; php artisan test --filter="renders SPA modal triggers and inclusion containers on schedule index"
```
Expected: FAIL due to missing `showModalCreate` or remaining old link hrefs.

- [ ] **Step 3: Implement SPA Modals and controller state functions in Blade**

Create `resources/views/admin/jadwal-pelajaran/_modal-create.blade.php`:
```html
<div x-show="showModalCreate" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" x-cloak style="display: none;">
    <div x-show="showModalCreate" class="fixed inset-0 transform transition-all" @click="showModalCreate = false">
        <div class="absolute inset-0 bg-gray-900/60"></div>
    </div>
    <div x-show="showModalCreate" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-lg sm:w-full z-10 p-6 text-left relative">
        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200">
            <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                <x-icon name="add_circle" class="h-5 w-5 text-brand-500" />
                <span>Tambah Sesi Jadwal</span>
            </h3>
            <button @click="showModalCreate = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="close" class="h-5 w-5" />
            </button>
        </div>
        <form action="{{ route('admin.jadwal-pelajaran.store') }}" method="POST" class="mt-4 space-y-4">
            @csrf
            <input type="hidden" name="kelas_id" :value="kelasId">
            <input type="hidden" name="semester_id" :value="semesterId">
            
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Slot Jam Pelajaran <span class="text-error-500">*</span></label>
                <select name="jam_pelajaran_id[]" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    <template x-for="slot in availableSlots" :key="slot.id">
                        <option :value="slot.id" :selected="slot.id == formCreate.jam_pelajaran_id" x-text="slot.label + ' (' + slot.jam_mulai + '-' + slot.jam_selesai + ')'"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Mata Pelajaran <span class="text-error-500">*</span></label>
                <select name="mata_pelajaran_id" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    <template x-for="mp in mataPelajaranList" :key="mp.id">
                        <option :value="mp.id" x-text="mp.nama"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Guru Pengampu <span class="text-error-500">*</span></label>
                <select name="guru_id" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    <template x-for="guru in guruList" :key="guru.id">
                        <option :value="guru.id" x-text="guru.nama"></option>
                    </template>
                </select>
            </div>
            <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100">
                <button type="button" @click="showModalCreate = false" class="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">Simpan Sesi</button>
            </div>
        </form>
    </div>
</div>
```

Create `resources/views/admin/jadwal-pelajaran/_modal-edit.blade.php`:
```html
<div x-show="showModalEdit" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" x-cloak style="display: none;">
    <div x-show="showModalEdit" class="fixed inset-0 transform transition-all" @click="showModalEdit = false">
        <div class="absolute inset-0 bg-gray-900/60"></div>
    </div>
    <div x-show="showModalEdit" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-lg sm:w-full z-10 p-6 text-left relative">
        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200">
            <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                <x-icon name="edit_calendar" class="h-5 w-5 text-brand-500" />
                <span>Edit Sesi Jadwal</span>
            </h3>
            <button @click="showModalEdit = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="close" class="h-5 w-5" />
            </button>
        </div>
        <form :action="formEdit.actionUrl" method="POST" class="mt-4 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Mata Pelajaran <span class="text-error-500">*</span></label>
                <select x-model="formEdit.mata_pelajaran_id" name="mata_pelajaran_id" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    <template x-for="mp in mataPelajaranList" :key="mp.id">
                        <option :value="mp.id" :selected="mp.id == formEdit.mata_pelajaran_id" x-text="mp.nama"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Guru Pengampu <span class="text-error-500">*</span></label>
                <select x-model="formEdit.guru_id" name="guru_id" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    <template x-for="guru in guruList" :key="guru.id">
                        <option :value="guru.id" :selected="guru.id == formEdit.guru_id" x-text="guru.nama"></option>
                    </template>
                </select>
            </div>
            <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100">
                <button type="button" @click="showModalEdit = false" class="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
```

Create `resources/views/admin/jadwal-pelajaran/_modal-duplicate.blade.php`:
```html
<div x-show="showModalDuplicate" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" x-cloak style="display: none;">
    <div x-show="showModalDuplicate" class="fixed inset-0 transform transition-all" @click="showModalDuplicate = false">
        <div class="absolute inset-0 bg-gray-900/60"></div>
    </div>
    <div x-show="showModalDuplicate" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-lg sm:w-full z-10 p-6 text-left relative">
        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200">
            <div>
                <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                    <x-icon name="content_copy" class="h-5 w-5 text-brand-500" />
                    <span>Salin Jadwal dari Kelas Lain</span>
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Salin susunan mata pelajaran & pengampu secara instan tanpa bentrok.</p>
            </div>
            <button @click="showModalDuplicate = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="close" class="h-5 w-5" />
            </button>
        </div>
        <form action="{{ route('admin.jadwal-pelajaran.duplicate') }}" method="POST" class="mt-4 space-y-4">
            @csrf
            <input type="hidden" name="target_kelas_id" :value="kelasId">
            <input type="hidden" name="target_semester_id" :value="semesterId">
            
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Semester Sumber <span class="text-error-500">*</span></label>
                <select x-model="formDuplicate.source_semester_id" name="source_semester_id" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm text-gray-900">
                    <option value="">— Pilih Semester Sumber —</option>
                    @foreach ($semesterList as $sem)
                        <option value="{{ $sem->id }}">{{ $sem->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kelas Sumber <span class="text-error-500">*</span></label>
                <select x-model="formDuplicate.source_kelas_id" name="source_kelas_id" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm text-gray-900">
                    <option value="">— Pilih Kelas Sumber —</option>
                    @foreach ($kelasList as $kls)
                        <option value="{{ $kls->id }}">{{ $kls->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100">
                <button type="button" @click="showModalDuplicate = false" class="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">Salin Sekarang</button>
            </div>
        </form>
    </div>
</div>
```

Modify `resources/views/admin/jadwal-pelajaran/index.blade.php` to add modal state attributes to `jadwalPelajaranFilter({ ... })`:
```blade
            x-data="jadwalPelajaranFilter({
                tahunAjaranId: @js($tahunAjaranId),
                kelasId: @js($kelasId),
                semesterId: @js($semesterId),
                opsiUrl: @js(route('admin.jadwal-pelajaran.opsi')),
                indexUrlBase: @js(route('admin.jadwal-pelajaran.index')),
                createUrlBase: @js(route('admin.jadwal-pelajaran.create')),
                showModalCreate: false,
                showModalEdit: false,
                showModalDuplicate: false,
                formCreate: { jam_pelajaran_id: null },
                formEdit: { actionUrl: '', mata_pelajaran_id: null, guru_id: null },
                formDuplicate: { source_kelas_id: '', source_semester_id: @js($semesterId) },
                availableSlots: [],
                mataPelajaranList: [],
                guruList: [],
                openEditJadwal(actionUrl, mpId, guruId) {
                    this.formEdit.actionUrl = actionUrl;
                    this.formEdit.mata_pelajaran_id = mpId;
                    this.formEdit.guru_id = guruId;
                    this.showModalEdit = true;
                },
                openCreateJadwal(slotId = null) {
                    this.formCreate.jam_pelajaran_id = slotId;
                    this.showModalCreate = true;
                },
                openDuplicateJadwal() {
                    this.showModalDuplicate = true;
                }
            })"
```
Also update button header in `index.blade.php`:
```blade
                    <template x-if="kelasId && semesterId">
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" @click="openDuplicateJadwal()" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-[0.98]">
                                <x-icon name="content_copy" class="h-4 w-4 text-brand-500" />
                                <span>Salin Jadwal</span>
                            </button>
                            <button type="button" @click="openCreateJadwal()" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-600 active:scale-[0.98]">
                                <span class="text-base leading-none mr-0.5">+</span> Tambah Slot Jadwal
                            </button>
                        </div>
                    </template>
```
Include modal partials at the bottom of `index.blade.php`:
```blade
        {{-- Include SPA Modal Partials --}}
        @include('admin.jadwal-pelajaran._modal-create')
        @include('admin.jadwal-pelajaran._modal-edit')
        @include('admin.jadwal-pelajaran._modal-duplicate')
```

In `resources/views/admin/jadwal-pelajaran/_daftar.blade.php`, replace the edit anchor tag link with `@click`:
```blade
<button type="button" @click="openEditJadwal('{{ route('admin.jadwal-pelajaran.update', $jadwal) }}', '{{ $jadwal->mata_pelajaran_id }}', '{{ $jadwal->guru_id }}')" class="text-xs font-semibold text-gray-500 hover:text-gray-900 transition-colors">Edit</button>
```

- [ ] **Step 4: Run test to verify it passes (TDD GREEN)**

Run:
```bash
If (!(Get-Process mysqld -ErrorAction SilentlyContinue)) { Start-Process -FilePath "D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe" -ArgumentList "--defaults-file=D:\laragon\bin\mysql\mysql-8.0.30-winx64\my.ini" -WindowStyle Hidden ; Start-Sleep -Seconds 2 } ; php artisan test --filter="JadwalPelajaranCrudTest"
```
Expected: PASS all tests.

- [ ] **Step 5: Commit changes**

Run:
```bash
git add resources/views/admin/jadwal-pelajaran/ tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat(akademik): implement smooth SPA modals for creating, editing, and duplicating jadwal pelajaran"
```

---

### Task 3: Interactive Weekly Timetable Roster Matrix (Dual-Mode Switcher & Table Grid)

**Files:**
- Modify: `resources/views/admin/jadwal-pelajaran/_daftar.blade.php:15-85`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php:1421-1480`

**Interfaces:**
- Consumes: `$jadwalList`, `$hariAktif`, `$kelasId`, and `$semesterId` in `_daftar.blade.php`.
- Produces: Dual-view toggle header (*Daftar Harian* vs *Matriks Roster Mingguan*) allowing interactive clicks on slots to edit or add lessons directly from the weekly grid table.

- [ ] **Step 1: Write the failing test for Weekly Timetable Roster Matrix rendering**

Add automated test case to `tests/Feature/Admin/JadwalPelajaranCrudTest.php`:
```php
it('renders weekly roster matrix headers, slots, and interactive edit triggers in jadwal daftar view', function () {
    $admin = createUserWithRole('admin_sekolah', ['jadwal-pelajaran.kelola'], $this->lembaga->id);

    $pola = \App\Models\PolaJam::create(['lembaga_id' => $this->lembaga->id, 'nama' => 'Pola Roster']);
    $slot1 = \App\Models\JamPelajaran::create([
        'pola_jam_id' => $pola->id,
        'hari' => \App\Enums\Hari::Senin->value,
        'urutan' => 1,
        'label' => 'Jam Ke-1',
        'jam_mulai' => '07:15',
        'jam_selesai' => '08:00',
        'is_pelajaran' => true,
    ]);
    $this->kelas->polaJam()->attach($pola->id);
    
    $mapel = \App\Models\MataPelajaran::create(['lembaga_id' => $this->lembaga->id, 'nama' => 'Biologi']);
    
    $jadwal = \App\Models\JadwalPelajaran::create([
        'kelas_id' => $this->kelas->id,
        'semester_id' => $this->semester->id,
        'jam_pelajaran_id' => $slot1->id,
        'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $this->guru->id,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.jadwal-pelajaran.index', [
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'semester_id' => $this->semester->id,
        'kelas_id' => $this->kelas->id,
    ]), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk();
    $response->assertSee('Matriks Roster Mingguan');
    $response->assertSee('Jam Ke- / Waktu');
    $response->assertSee('Biologi');
    $response->assertSee('07:15 - 08:00');
});
```

- [ ] **Step 2: Run test to verify it fails (TDD RED)**

Run:
```bash
If (!(Get-Process mysqld -ErrorAction SilentlyContinue)) { Start-Process -FilePath "D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe" -ArgumentList "--defaults-file=D:\laragon\bin\mysql\mysql-8.0.30-winx64\my.ini" -WindowStyle Hidden ; Start-Sleep -Seconds 2 } ; php artisan test --filter="renders weekly roster matrix headers"
```
Expected: FAIL due to missing text `"Matriks Roster Mingguan"` and table headers in `_daftar.blade.php`.

- [ ] **Step 3: Implement View Mode Switcher and Roster Table Grid in `_daftar.blade.php`**

Wrap the schedule list in `_daftar.blade.php` inside `x-data="{ viewMode: 'list' }"`:
```blade
        <div x-data="{ viewMode: 'list' }">
            {{-- Toggle Bar --}}
            <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50/40 px-6 py-3">
                <span class="text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-1.5">
                    <x-icon name="date_range" class="h-4 w-4 text-brand-500" />
                    <span>Jadwal Mingguan Kelas</span>
                </span>
                <div class="inline-flex rounded-lg border border-gray-200 bg-gray-100 p-0.5">
                    <button type="button" @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-white text-gray-900 shadow-2xs font-bold' : 'text-gray-600 hover:text-gray-900 font-medium'" class="flex items-center gap-1 px-3 py-1 rounded-md text-xs transition">
                        <x-icon name="list" class="h-3.5 w-3.5" />
                        <span>Daftar Harian</span>
                    </button>
                    <button type="button" @click="viewMode = 'matrix'" :class="viewMode === 'matrix' ? 'bg-white text-gray-900 shadow-2xs font-bold' : 'text-gray-600 hover:text-gray-900 font-medium'" class="flex items-center gap-1 px-3 py-1 rounded-md text-xs transition">
                        <x-icon name="grid_view" class="h-3.5 w-3.5 text-brand-500" />
                        <span>Matriks Roster Mingguan</span>
                    </button>
                </div>
            </div>

            {{-- Mode 1: Daftar Harian --}}
            <div x-show="viewMode === 'list'" class="divide-y divide-gray-100 bg-white">
                {{-- Existing foreach over hariAktif --}}
            </div>

            {{-- Mode 2: Matriks Roster Mingguan --}}
            <div x-show="viewMode === 'matrix'" x-cloak style="display: none;" class="overflow-x-auto p-6 bg-gray-50/20 border-t border-gray-100">
                @php
                    $allUrutans = $jadwalList->pluck('jamPelajaran.urutan')->unique()->sort()->values();
                @endphp
                <table class="w-full border-collapse rounded-xl overflow-hidden shadow-2xs border border-gray-200 bg-white text-left text-xs">
                    <thead>
                        <tr class="bg-gray-100 border-b border-gray-200 text-gray-700 font-bold">
                            <th class="py-2.5 px-4 w-32 border-r border-gray-200 text-center uppercase tracking-wider">Jam Ke- / Waktu</th>
                            @foreach ($hariAktif as $hariCol)
                                <th class="py-2.5 px-3 border-r border-gray-200 text-center uppercase tracking-wider last:border-r-0">{{ $hariCol->label() }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($allUrutans as $urutan)
                            <tr class="hover:bg-gray-50/50 transition">
                                @php
                                    $sampleJadwal = $jadwalList->firstWhere('jamPelajaran.urutan', $urutan);
                                @endphp
                                <td class="py-3 px-3 border-r border-gray-100 text-center bg-gray-50/40 font-mono shrink-0">
                                    <div class="font-bold text-gray-900 text-sm">Ke-{{ $urutan }}</div>
                                    @if ($sampleJadwal?->jamPelajaran)
                                        <div class="text-[11px] text-gray-500 font-semibold mt-0.5">{{ \Carbon\Carbon::parse($sampleJadwal->jamPelajaran->jam_mulai)->format('H:i') }} - {{ \Carbon\Carbon::parse($sampleJadwal->jamPelajaran->jam_selesai)->format('H:i') }}</div>
                                    @endif
                                </td>
                                @foreach ($hariAktif as $hariCol)
                                    @php
                                        $cellJadwal = $jadwalList->where('jamPelajaran.hari', $hariCol)->where('jamPelajaran.urutan', $urutan)->first();
                                    @endphp
                                    <td class="py-2 px-2 border-r border-gray-100 align-top last:border-r-0 w-[14%]">
                                        @if ($cellJadwal)
                                            <div @can('jadwal-pelajaran.kelola') @click="openEditJadwal('{{ route('admin.jadwal-pelajaran.update', $cellJadwal) }}', '{{ $cellJadwal->mata_pelajaran_id }}', '{{ $cellJadwal->guru_id }}')" @endcan
                                                 class="group rounded-lg p-2.5 border transition relative cursor-pointer bg-brand-50/40 border-brand-200 hover:border-brand-400 hover:bg-brand-50/80 text-brand-950">
                                                <div class="font-bold text-xs leading-tight mb-1.5 flex items-center justify-between gap-1">
                                                    <span>{{ $cellJadwal->mataPelajaran?->nama ?? '(Tanpa Mapel)' }}</span>
                                                    @can('jadwal-pelajaran.kelola')
                                                        <x-icon name="edit" class="h-3 w-3 opacity-0 group-hover:opacity-100 transition text-brand-600 shrink-0" />
                                                    @endcan
                                                </div>
                                                <div class="text-[11px] text-gray-600 font-medium flex items-center gap-1 border-t border-brand-100 pt-1 mt-1">
                                                    <x-icon name="person" class="h-3 w-3 text-gray-400 shrink-0" />
                                                    <span class="truncate">{{ $cellJadwal->guru->nama }}</span>
                                                </div>
                                            </div>
                                        @else
                                            <div @can('jadwal-pelajaran.kelola') @click="openCreateJadwal()" @endcan class="h-full w-full min-h-[58px] rounded-lg border border-dashed border-gray-200 hover:border-brand-300 hover:bg-brand-50/20 transition flex items-center justify-center text-[10px] text-gray-400 italic cursor-pointer select-none">
                                                + Tambah Sesi
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($hariAktif) + 1 }}" class="py-8 text-center text-xs text-gray-400 italic">Belum ada slot waktu pada pola jam kelas ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
```

- [ ] **Step 4: Run test to verify it passes (TDD GREEN)**

Run:
```bash
If (!(Get-Process mysqld -ErrorAction SilentlyContinue)) { Start-Process -FilePath "D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe" -ArgumentList "--defaults-file=D:\laragon\bin\mysql\mysql-8.0.30-winx64\my.ini" -WindowStyle Hidden ; Start-Sleep -Seconds 2 } ; php artisan test --filter="JadwalPelajaranCrudTest"
```
Expected: PASS all test cases cleanly without regression.

- [ ] **Step 5: Commit changes and verify full suite**

Run:
```bash
git add resources/views/admin/jadwal-pelajaran/ tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat(akademik): integrate interactive weekly roster matrix view with click-to-edit capabilities"
```

---

## Verification Plan

### Automated Tests
Run full suite for academic timetabling modules:
```bash
If (!(Get-Process mysqld -ErrorAction SilentlyContinue)) { Start-Process -FilePath "D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe" -ArgumentList "--defaults-file=D:\laragon\bin\mysql\mysql-8.0.30-winx64\my.ini" -WindowStyle Hidden ; Start-Sleep -Seconds 2 } ; php artisan test --filter="JadwalPelajaranCrudTest|PolaJamCrudTest|JamPelajaranCrudTest"
```
Expected: 100% green across all three core scheduling test files (~76+ total tests passing).

### Manual Verification
1. Navigate to `/admin/jadwal-pelajaran`, pick Tahun Ajaran, Semester, and Kelas.
2. Verify both "Daftar Harian" and "Matriks Roster Mingguan" render cleanly.
3. Try clicking "Salin Jadwal" and copying from previous semester or parallel class. Verify amber/warning message if a teacher collision occurs.
4. Try clicking on any matrix cell to test instant opening of SPA Edit Modal without full page navigation.
