# Jadwal Pelajaran Edit & Delete Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add edit and delete capability for existing Jadwal Pelajaran entries — currently there is no route, controller method, or UI to change or remove an entry once created.

**Architecture:** One task. New routes (`edit`/`update`/`destroy`) and controller methods, a new `edit.blade.php` view reusing the existing `jadwalPelajaranCreateForm()` JS module (no new JS needed — its `initXSelect` methods are already generic), and an Edit/Hapus action pair added to each row of the existing `_daftar.blade.php` partial using the codebase's established `confirmDialog` pattern.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Tom Select, Pest 4.

**Spec:** `docs/superpowers/specs/2026-07-29-jadwal-pelajaran-edit-delete-design.md` — read this for full rationale.

## Global Constraints

- `JadwalPelajaran` is NOT tenant-scoped directly (no `lembaga_id` column, no `BelongsToTenant` trait) — same situation as `JamPelajaran`. Tenant isolation on `edit()`/`update()`/`destroy()` must be enforced explicitly by resolving `Kelas::find($jadwalPelajaran->kelas_id)` and `abort(404)` if `null` (Kelas IS tenant-scoped, so this transitively blocks cross-lembaga access) — this is the exact pattern `JamPelajaranController::edit()`/`update()`/`destroy()` already uses for `PolaJam::find($jamPelajaran->pola_jam_id)`.
- Kelas and Semester are NOT editable on the edit form — they stay fixed as context, read from `$jadwalPelajaran` itself, never taken from request input.
- Security/integrity validation on update (jam pelajaran must be `is_pelajaran` and belong to the kelas's `pola_jam_id`; guru/mata_pelajaran must belong to the kelas's `lembaga_id`) mirrors `store()` exactly. Collision validation (duplicate slot, guru double-booking) also mirrors `store()` but every collision query excludes the record being edited (`where('id', '!=', $jadwalPelajaran->id)`).
- Delete confirmation must reuse the existing global `confirmDialog` helper (`window.confirmDialog(title, message, options)`, returns a Promise) exactly as used in `resources/views/admin/pola-jam/index.blade.php` — no new confirmation component.
- Both new actions are gated by the same single permission already used by the rest of this controller: `jadwal-pelajaran.kelola` (no new permission).
- No new JS file — `edit.blade.php` reuses `x-data="jadwalPelajaranCreateForm()"` from `resources/js/jadwal-pelajaran-create.js` (already registered in `app.js`); Tom Select auto-detects the absence of the `multiple` attribute and renders `initJamPelajaranSelect` as a single-select for this page.

---

### Task 1: Edit and delete routes, controller methods, views, and actions

**Files:**
- Modify: `routes/admin.php` (add 3 routes after the existing `jadwal-pelajaran` store route, currently line 133)
- Modify: `app/Http/Controllers/Admin/JadwalPelajaranController.php` (add `edit()`, `update()`, `destroy()` methods)
- Modify: `resources/views/admin/jadwal-pelajaran/_daftar.blade.php` (add Edit/Hapus actions to each row)
- Create: `resources/views/admin/jadwal-pelajaran/edit.blade.php`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\JadwalPelajaran` (existing, implicit route-model-binding), `App\Models\Kelas`/`Semester`/`Guru`/`MataPelajaran`/`JamPelajaran` (existing, already imported in the controller), `App\Enums\Hari::aktifDari()` (existing), `resources/js/jadwal-pelajaran-create.js`'s `jadwalPelajaranCreateForm()` factory (existing, unchanged).
- Produces: routes `admin.jadwal-pelajaran.edit` (GET), `admin.jadwal-pelajaran.update` (PUT), `admin.jadwal-pelajaran.destroy` (DELETE) — all three take a `JadwalPelajaran` route parameter.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/JadwalPelajaranCrudTest.php` (at the end of the file):

```php
it('shows edit and hapus actions for each jadwal entry in the daftar', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]));

    $response->assertSee(route('admin.jadwal-pelajaran.edit', $jadwal), false);
    $response->assertSee('Hapus');
    $response->assertSee('confirmDialog', false);
    $response->assertSee(route('admin.jadwal-pelajaran.destroy', $jadwal), false);
});

it('updates a jadwal pelajaran entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamLama = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 1, 'is_pelajaran' => true]);
    $jamBaru = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 2, 'is_pelajaran' => true]);
    $guruLama = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruBaru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelBaru = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamLama->id, 'guru_id' => $guruLama->id, 'semester_id' => $semester->id, 'mata_pelajaran_id' => null]);

    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'jam_pelajaran_id' => $jamBaru->id,
        'mata_pelajaran_id' => $mapelBaru->id,
        'guru_id' => $guruBaru->id,
    ])->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $jadwal->refresh();
    expect($jadwal->jam_pelajaran_id)->toBe($jamBaru->id);
    expect($jadwal->guru_id)->toBe($guruBaru->id);
    expect($jadwal->mata_pelajaran_id)->toBe($mapelBaru->id);
});

it('does not allow changing kelas or semester through the update payload', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'kelas_id' => $kelasLain->id,
        'semester_id' => $semesterLain->id,
        'jam_pelajaran_id' => $jam->id,
        'guru_id' => $guru->id,
    ])->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $jadwal->refresh();
    expect($jadwal->kelas_id)->toBe($kelas->id);
    expect($jadwal->semester_id)->toBe($semester->id);
});

it('rejects updating guru_id to a guru from another lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembagaA);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    $guruLain = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'jam_pelajaran_id' => $jam->id,
        'guru_id' => $guruLain->id,
    ])->assertSessionHasErrors('guru_id');

    expect($jadwal->fresh()->guru_id)->toBe($guru->id);
});

it('rejects updating jam_pelajaran_id to a slot from a different pola jam', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $jamLain = JamPelajaran::factory()->create(['pola_jam_id' => $polaLain->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'jam_pelajaran_id' => $jamLain->id,
        'guru_id' => $guru->id,
    ])->assertNotFound();

    expect($jadwal->fresh()->jam_pelajaran_id)->toBe($jam->id);
});

it('rejects updating jam_pelajaran_id to a slot that is not is_pelajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $jamIstirahat = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => false]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'jam_pelajaran_id' => $jamIstirahat->id,
        'guru_id' => $guru->id,
    ])->assertNotFound();

    expect($jadwal->fresh()->jam_pelajaran_id)->toBe($jam->id);
});

it('allows saving an update that keeps the same jam_pelajaran_id without a false duplicate conflict', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guruLama = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruBaru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guruLama->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'jam_pelajaran_id' => $jam->id,
        'guru_id' => $guruBaru->id,
    ])->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    expect($jadwal->fresh()->guru_id)->toBe($guruBaru->id);
});

it('rejects updating to a jam_pelajaran_id that collides with another existing jadwal in the same kelas and semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamSatu = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 1, 'is_pelajaran' => true]);
    $jamDua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 2, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwalSatu = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamSatu->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    $jadwalDua = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamDua->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwalDua), [
        'jam_pelajaran_id' => $jamSatu->id,
        'guru_id' => $guru->id,
    ])->assertSessionHasErrors('jam_pelajaran_id');

    expect($jadwalDua->fresh()->jam_pelajaran_id)->toBe($jamDua->id);
});

it('rejects updating guru_id to create a double booking with another existing jadwal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $kelasSatu = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $kelasDua = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $guruSatu = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruDua = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelasSatu->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guruSatu->id, 'semester_id' => $semester->id]);
    $jadwalDua = JadwalPelajaran::factory()->create(['kelas_id' => $kelasDua->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guruDua->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwalDua), [
        'jam_pelajaran_id' => $jam->id,
        'guru_id' => $guruSatu->id,
    ])->assertSessionHasErrors('guru_id');

    expect($jadwalDua->fresh()->guru_id)->toBe($guruDua->id);
});

it('rejects editing or updating a jadwal entry belonging to another lembaga', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsJadwalManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $polaB = PolaJam::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $tahunAjaranB->id, 'pola_jam_id' => $polaB->id]);
    $jamB = JamPelajaran::factory()->create(['pola_jam_id' => $polaB->id, 'is_pelajaran' => true]);
    $guruB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);
    $jadwalB = JadwalPelajaran::factory()->create(['kelas_id' => $kelasB->id, 'jam_pelajaran_id' => $jamB->id, 'guru_id' => $guruB->id, 'semester_id' => $semesterB->id]);

    $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.edit', $jadwalB))->assertNotFound();
    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwalB), [
        'jam_pelajaran_id' => $jamB->id,
        'guru_id' => $guruB->id,
    ])->assertNotFound();
});

it('deletes a jadwal pelajaran entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->delete(route('admin.jadwal-pelajaran.destroy', $jadwal))
        ->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]))
        ->assertSessionHas('status');

    expect(JadwalPelajaran::find($jadwal->id))->toBeNull();
});

it('rejects deleting a jadwal entry belonging to another lembaga', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsJadwalManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $polaB = PolaJam::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $tahunAjaranB->id, 'pola_jam_id' => $polaB->id]);
    $jamB = JamPelajaran::factory()->create(['pola_jam_id' => $polaB->id, 'is_pelajaran' => true]);
    $guruB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);
    $jadwalB = JadwalPelajaran::factory()->create(['kelas_id' => $kelasB->id, 'jam_pelajaran_id' => $jamB->id, 'guru_id' => $guruB->id, 'semester_id' => $semesterB->id]);

    $this->actingAs($manager)->delete(route('admin.jadwal-pelajaran.destroy', $jadwalB))->assertNotFound();

    expect(JadwalPelajaran::find($jadwalB->id))->not->toBeNull();
});

it('denies access to edit, update, and destroy without jadwal-pelajaran.kelola permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    $outsider = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($outsider)->get(route('admin.jadwal-pelajaran.edit', $jadwal))->assertForbidden();
    $this->actingAs($outsider)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id,
    ])->assertForbidden();
    $this->actingAs($outsider)->delete(route('admin.jadwal-pelajaran.destroy', $jadwal))->assertForbidden();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php`
Expected: FAIL — the routes don't exist yet, so every new test errors with a `RouteNotFoundException` (thrown by the `route(...)` helper itself, before any HTTP call happens).

- [ ] **Step 3: Add the routes**

In `routes/admin.php`, immediately after the existing line `Route::post('jadwal-pelajaran', [JadwalPelajaranController::class, 'store'])->name('jadwal-pelajaran.store');` (currently line 133), add:

```php
    Route::get('jadwal-pelajaran/{jadwalPelajaran}/edit', [JadwalPelajaranController::class, 'edit'])->name('jadwal-pelajaran.edit');
    Route::put('jadwal-pelajaran/{jadwalPelajaran}', [JadwalPelajaranController::class, 'update'])->name('jadwal-pelajaran.update');
    Route::delete('jadwal-pelajaran/{jadwalPelajaran}', [JadwalPelajaranController::class, 'destroy'])->name('jadwal-pelajaran.destroy');
```

- [ ] **Step 4: Add the controller methods**

In `app/Http/Controllers/Admin/JadwalPelajaranController.php`, add these three public methods right after `store()` (before the private `formatSlot()` helper at the end of the class):

```php
    public function edit(JadwalPelajaran $jadwalPelajaran): View
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $kelas = Kelas::with(['lembaga', 'tahunAjaran'])->find($jadwalPelajaran->kelas_id);
        if (! $kelas) {
            abort(404);
        }

        $semester = Semester::find($jadwalPelajaran->semester_id);

        $hariAktif = Hari::aktifDari($kelas->lembaga->hari_libur_mingguan ?? []);

        $jamPelajaranPerHari = collect();
        if ($kelas->pola_jam_id) {
            $mentah = JamPelajaran::where('pola_jam_id', $kelas->pola_jam_id)
                ->isPelajaran()
                ->orderBy('urutan')
                ->get()
                ->groupBy(fn ($jam) => $jam->hari->value);

            foreach ($hariAktif as $hari) {
                if ($mentah->has($hari->value)) {
                    $jamPelajaranPerHari->push(['hari' => $hari, 'items' => $mentah->get($hari->value)]);
                }
            }
        }

        return view('admin.jadwal-pelajaran.edit', [
            'jadwalPelajaran' => $jadwalPelajaran,
            'kelas' => $kelas,
            'semester' => $semester,
            'jamPelajaranPerHari' => $jamPelajaranPerHari,
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
            'guruList' => Guru::orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, JadwalPelajaran $jadwalPelajaran): RedirectResponse
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $kelas = Kelas::find($jadwalPelajaran->kelas_id);
        if (! $kelas) {
            abort(404);
        }

        $data = $request->validate([
            'jam_pelajaran_id' => ['required', 'integer'],
            'mata_pelajaran_id' => ['nullable', 'integer'],
            'guru_id' => ['required', 'integer'],
        ]);

        $guru = Guru::find($data['guru_id']);
        if (! $guru) {
            abort(404);
        }

        if (! empty($data['mata_pelajaran_id'])) {
            $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);
            if (! $mataPelajaran) {
                abort(404);
            }
        }

        if ($guru->lembaga_id !== $kelas->lembaga_id) {
            return back()->withErrors(['guru_id' => 'Guru harus berasal dari lembaga yang sama dengan kelas ini.'])->withInput();
        }

        if (isset($mataPelajaran) && $mataPelajaran->lembaga_id !== $kelas->lembaga_id) {
            return back()->withErrors(['mata_pelajaran_id' => 'Mata pelajaran harus berasal dari lembaga yang sama dengan kelas ini.'])->withInput();
        }

        $jamPelajaran = JamPelajaran::where('id', $data['jam_pelajaran_id'])
            ->where('pola_jam_id', $kelas->pola_jam_id)
            ->isPelajaran()
            ->first();
        if (! $jamPelajaran) {
            abort(404);
        }

        $duplikat = JadwalPelajaran::where('kelas_id', $jadwalPelajaran->kelas_id)
            ->where('jam_pelajaran_id', $data['jam_pelajaran_id'])
            ->where('semester_id', $jadwalPelajaran->semester_id)
            ->where('id', '!=', $jadwalPelajaran->id)
            ->exists();
        if ($duplikat) {
            return back()->withErrors(['jam_pelajaran_id' => 'Kelas ini sudah punya jadwal pada slot ini di semester yang sama.'])->withInput();
        }

        $guruBentrok = JadwalPelajaran::where('guru_id', $data['guru_id'])
            ->where('jam_pelajaran_id', $data['jam_pelajaran_id'])
            ->where('semester_id', $jadwalPelajaran->semester_id)
            ->where('id', '!=', $jadwalPelajaran->id)
            ->exists();
        if ($guruBentrok) {
            return back()->withErrors(['guru_id' => 'Guru ini sudah mengajar kelas lain pada jam dan semester yang sama.'])->withInput();
        }

        $jadwalPelajaran->update([
            'jam_pelajaran_id' => $data['jam_pelajaran_id'],
            'mata_pelajaran_id' => $data['mata_pelajaran_id'] ?? null,
            'guru_id' => $data['guru_id'],
        ]);

        return redirect()->route('admin.jadwal-pelajaran.index', [
            'kelas_id' => $jadwalPelajaran->kelas_id,
            'semester_id' => $jadwalPelajaran->semester_id,
        ])->with('status', 'Jadwal pelajaran berhasil diperbarui.');
    }

    public function destroy(JadwalPelajaran $jadwalPelajaran): RedirectResponse
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $kelas = Kelas::find($jadwalPelajaran->kelas_id);
        if (! $kelas) {
            abort(404);
        }

        $kelasId = $jadwalPelajaran->kelas_id;
        $semesterId = $jadwalPelajaran->semester_id;
        $jadwalPelajaran->delete();

        return redirect()->route('admin.jadwal-pelajaran.index', [
            'kelas_id' => $kelasId,
            'semester_id' => $semesterId,
        ])->with('status', 'Jadwal pelajaran berhasil dihapus.');
    }
```

- [ ] **Step 5: Create the edit view**

Create `resources/views/admin/jadwal-pelajaran/edit.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Jadwal — {{ $kelas->nama }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $jadwalPelajaran->semester_id]) }}" class="font-semibold text-gray-700 hover:text-brand-600">Jadwal Pelajaran</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-xl bg-brand-50 px-4 py-3 text-sm">
            <p><span class="text-brand-400">Tahun Ajaran</span> <span class="ml-1.5 font-semibold text-brand-700">{{ $kelas->tahunAjaran->nama }}</span></p>
            <p><span class="text-brand-400">Semester</span> <span class="ml-1.5 font-semibold text-brand-700">{{ $semester->nama ?? '—' }}</span></p>
            <p><span class="text-brand-400">Kelas</span> <span class="ml-1.5 font-semibold text-brand-700">{{ $kelas->nama }}</span></p>
        </div>

        @if ($jamPelajaranPerHari->isEmpty())
            <div class="flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-50 p-5 text-sm text-warning-700">
                <x-icon name="warning" class="mt-0.5 h-5 w-5 shrink-0 text-warning-500" />
                <div>
                    <p class="font-semibold">Kelas ini belum punya Pola Jam</p>
                    <p class="mt-1">Atur Pola Jam terlebih dahulu sebelum mengedit jadwal pelajaran untuk kelas ini.</p>
                    <a href="{{ route('admin.pola-jam.index') }}" class="mt-2 inline-block font-semibold text-warning-800 underline">Buka halaman Pola Jam &rarr;</a>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('admin.jadwal-pelajaran.update', $jadwalPelajaran) }}" x-data="jadwalPelajaranCreateForm()">
                @csrf
                @method('PUT')

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700 border-b border-gray-100 pb-3">
                        <x-icon name="schedule" class="h-[15px] w-[15px] text-gray-400" />
                        Penempatan Slot &amp; Pengajar
                    </p>

                    <div>
                        <x-input-label value="Jam Pelajaran" />
                        <select
                            name="jam_pelajaran_id"
                            x-ref="jamPelajaranSelect"
                            x-init="initJamPelajaranSelect($refs.jamPelajaranSelect)"
                            class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        >
                            @foreach ($jamPelajaranPerHari as $grup)
                                <optgroup label="{{ $grup['hari']->label() }}">
                                    @foreach ($grup['items'] as $jam)
                                        <option value="{{ $jam->id }}" @selected($jam->id == old('jam_pelajaran_id', $jadwalPelajaran->jam_pelajaran_id))>{{ substr($jam->jam_mulai, 0, 5) }}–{{ substr($jam->jam_selesai, 0, 5) }} ({{ $jam->label }})</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('jam_pelajaran_id')" class="mt-1.5" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Mata Pelajaran" />
                            <select
                                name="mata_pelajaran_id"
                                x-ref="mataPelajaranSelect"
                                x-init="initMataPelajaranSelect($refs.mataPelajaranSelect)"
                                class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            >
                                <option value="">— Tidak ada —</option>
                                @foreach ($mataPelajaranList as $mapel)
                                    <option value="{{ $mapel->id }}" @selected($mapel->id == old('mata_pelajaran_id', $jadwalPelajaran->mata_pelajaran_id))>{{ $mapel->nama }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1.5 text-xs text-gray-400">Opsional untuk kelas PAUD.</p>
                            <x-input-error :messages="$errors->get('mata_pelajaran_id')" class="mt-1.5" />
                        </div>

                        <div>
                            <x-input-label value="Guru Pengampu" />
                            <select
                                name="guru_id"
                                x-ref="guruSelect"
                                x-init="initGuruSelect($refs.guruSelect)"
                                class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            >
                                <option value="" disabled>— Pilih Guru —</option>
                                @foreach ($guruList as $guru)
                                    <option value="{{ $guru->id }}" @selected($guru->id == old('guru_id', $jadwalPelajaran->guru_id))>{{ $guru->nama }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('guru_id')" class="mt-1.5" />
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                    <a href="{{ route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $jadwalPelajaran->semester_id]) }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Batal</a>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 6: Add Edit/Hapus actions to the daftar partial**

In `resources/views/admin/jadwal-pelajaran/_daftar.blade.php`, replace this block (currently lines 36-70, the whole `@foreach ($jadwalHariIni as $jadwal)` loop body):

```blade
                                @foreach ($jadwalHariIni as $jadwal)
                                    <li class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 px-6 py-4 transition-colors duration-150 hover:bg-gray-50/60">
                                        <div class="flex flex-wrap items-center gap-3 md:gap-4">
                                            {{-- Badge Waktu (Format tanpa detik: H:i) --}}
                                            <div class="flex items-center gap-2 font-mono text-xs">
                                                <span class="rounded bg-brand-50 px-2.5 py-1 font-bold text-brand-600 ring-1 ring-inset ring-brand-500/20">
                                                    {{ substr($jadwal->jamPelajaran->jam_mulai, 0, 5) }}
                                                </span>
                                                <span class="text-gray-400 font-medium">&rarr;</span>
                                                <span class="rounded bg-gray-100 px-2.5 py-1 font-semibold text-gray-700 ring-1 ring-inset ring-gray-300/60">
                                                    {{ substr($jadwal->jamPelajaran->jam_selesai, 0, 5) }}
                                                </span>
                                            </div>

                                            <span class="hidden md:inline text-gray-300">&bull;</span>

                                            {{-- Badge Label Slot (Jam ke-1, Istirahat, dll) --}}
                                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 ring-1 ring-inset ring-gray-200/60">
                                                {{ $jadwal->jamPelajaran->label }}
                                            </span>

                                            <span class="hidden md:inline text-gray-300">&bull;</span>

                                            {{-- Mata Pelajaran & Guru --}}
                                            <div class="flex flex-wrap items-center gap-2 md:gap-3">
                                                <span class="text-sm font-bold text-gray-900">
                                                    {{ $jadwal->mataPelajaran?->nama ?? '(tanpa mapel)' }}
                                                </span>
                                                <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 sm:border-l sm:border-gray-200 sm:pl-3">
                                                    <x-icon name="person" class="h-3.5 w-3.5 text-gray-400" />
                                                    <span>Guru: <strong class="font-semibold text-gray-800">{{ $jadwal->guru->nama }}</strong></span>
                                                </span>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
```

with:

```blade
                                @foreach ($jadwalHariIni as $jadwal)
                                    <li class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 px-6 py-4 transition-colors duration-150 hover:bg-gray-50/60">
                                        <div class="flex flex-wrap items-center gap-3 md:gap-4">
                                            {{-- Badge Waktu (Format tanpa detik: H:i) --}}
                                            <div class="flex items-center gap-2 font-mono text-xs">
                                                <span class="rounded bg-brand-50 px-2.5 py-1 font-bold text-brand-600 ring-1 ring-inset ring-brand-500/20">
                                                    {{ substr($jadwal->jamPelajaran->jam_mulai, 0, 5) }}
                                                </span>
                                                <span class="text-gray-400 font-medium">&rarr;</span>
                                                <span class="rounded bg-gray-100 px-2.5 py-1 font-semibold text-gray-700 ring-1 ring-inset ring-gray-300/60">
                                                    {{ substr($jadwal->jamPelajaran->jam_selesai, 0, 5) }}
                                                </span>
                                            </div>

                                            <span class="hidden md:inline text-gray-300">&bull;</span>

                                            {{-- Badge Label Slot (Jam ke-1, Istirahat, dll) --}}
                                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 ring-1 ring-inset ring-gray-200/60">
                                                {{ $jadwal->jamPelajaran->label }}
                                            </span>

                                            <span class="hidden md:inline text-gray-300">&bull;</span>

                                            {{-- Mata Pelajaran & Guru --}}
                                            <div class="flex flex-wrap items-center gap-2 md:gap-3">
                                                <span class="text-sm font-bold text-gray-900">
                                                    {{ $jadwal->mataPelajaran?->nama ?? '(tanpa mapel)' }}
                                                </span>
                                                <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 sm:border-l sm:border-gray-200 sm:pl-3">
                                                    <x-icon name="person" class="h-3.5 w-3.5 text-gray-400" />
                                                    <span>Guru: <strong class="font-semibold text-gray-800">{{ $jadwal->guru->nama }}</strong></span>
                                                </span>
                                            </div>
                                        </div>

                                        @can('jadwal-pelajaran.kelola')
                                            <div class="flex items-center gap-4">
                                                <a href="{{ route('admin.jadwal-pelajaran.edit', $jadwal) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-900 transition-colors">Edit</a>
                                                <form method="POST" action="{{ route('admin.jadwal-pelajaran.destroy', $jadwal) }}" x-data @submit.prevent="confirmDialog('Hapus Jadwal?', @js('Apakah Anda yakin ingin menghapus jadwal ' . ($jadwal->mataPelajaran?->nama ?? 'ini') . ' oleh ' . $jadwal->guru->nama . '?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-xs font-semibold text-error-500 hover:text-error-700 transition-colors">Hapus</button>
                                                </form>
                                            </div>
                                        @endcan
                                    </li>
                                @endforeach
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php`
Expected: PASS (all tests, including all 13 new ones).

- [ ] **Step 8: Run the full test suite**

Run: `php artisan test`
Expected: all tests pass.

- [ ] **Step 9: Build assets**

Run: `npm run build`
Expected: builds successfully (no JS changed, but this confirms the new Blade view has no syntax issues that break the build pipeline).

- [ ] **Step 10: Commit**

```bash
git add routes/admin.php app/Http/Controllers/Admin/JadwalPelajaranController.php resources/views/admin/jadwal-pelajaran/_daftar.blade.php resources/views/admin/jadwal-pelajaran/edit.blade.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat: add edit and delete for existing jadwal pelajaran entries"
```

---

## Plan Self-Review Notes

- **Spec coverage**: all 7 requirements from the spec map to concrete steps — routes (Step 3), edit/update/destroy controller methods with the fixed kelas/semester + two-tier validation (Step 4), edit view matching create's design language (Step 5), Edit/Hapus actions with `confirmDialog` (Step 6).
- **Tenant isolation**: `edit()`/`update()`/`destroy()` all resolve `Kelas::find($jadwalPelajaran->kelas_id)` + `abort(404)` before doing anything else, exactly matching the `JamPelajaranController` precedent named in the Global Constraints — covered by the dedicated cross-lembaga tests in Step 1.
- **Type consistency**: the validation/collision-check logic in `update()` is structurally identical to `store()`'s per-slot checks (same field names, same error messages, same `isPelajaran()` scope usage) — this was verified against the current `store()` implementation before writing this plan, not copied blind from the original spec draft.
- **No placeholders**: every step contains complete, literal code — no "similar to store()" shortcuts.
