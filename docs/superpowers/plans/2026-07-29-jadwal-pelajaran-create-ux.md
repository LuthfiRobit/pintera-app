# Jadwal Pelajaran Create Page UX & Multi-Slot Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the "Tambah Jadwal" create page so all dropdowns are searchable (Tom Select), Jam Pelajaran becomes a multi-select grouped by hari (one submit can create several slots at once), batch validation follows a two-tier model (all-or-nothing for security/integrity, skip-and-report for scheduling collisions), Tahun Ajaran + Semester context is visible, and toast/flash blocks match every other admin page.

**Architecture:** One task. Backend (`JadwalPelajaranController::create()`/`store()`) and frontend (`create.blade.php` + a new Tom Select JS module) change together because they share a data contract (`jam_pelajaran_id` becomes an array; the view variable that feeds the dropdown changes shape from a flat list to hari-grouped data) — landing one half without the other leaves the page broken, so they're one reviewable unit.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Tom Select (already a dependency), Pest 4.

**Spec:** `docs/superpowers/specs/2026-07-29-jadwal-pelajaran-create-ux-design.md` — read this for the full rationale (why the two validation tiers, why Tahun Ajaran gets displayed, why toast blocks are added even though success always redirects away).

## Global Constraints

- Only the Create page (`JadwalPelajaranController::create()`/`store()`, `create.blade.php`) changes. Do not touch `index()`, `opsi()`, `index.blade.php`, or `_daftar.blade.php` — those were completed in a prior plan.
- Do not add edit/delete capability for existing jadwal entries — explicitly out of scope (deferred separately).
- Any FK resolution must follow this project's standing rule: resolve via `Model::find($id)` + `abort(404)` for tenant-scoped models, never raw `exists:table,column`. This already holds in the current `store()` and must keep holding for the new multi-slot resolution.
- Two-tier validation (confirmed in the spec): jam-pelajaran-belongs-to-this-pola-jam and guru/semester/mata-pelajaran-belongs-to-this-lembaga are **all-or-nothing** — abort/error the whole request if any one fails. Duplicate-slot and guru-double-booking are **skip-and-report** — create what doesn't collide, list what was skipped, only error if literally everything was skipped.
- New JS follows this codebase's established convention: a factory function in `resources/js/<name>.js`, registered via `Alpine.data('<name>', <factory>)` in `resources/js/app.js`, Tom Select instantiated via `new TomSelect(el, {...})` inside an `initXSelect(el)` method called from Blade via `x-init`. No new npm dependency — Tom Select is already installed and themed (`resources/css/app.css`).
- Time values (`jam_mulai`/`jam_selesai`) are plain `H:i:s` strings on the model (no cast) — display with `substr($value, 0, 5)`, matching `_daftar.blade.php`.

---

### Task 1: Multi-slot create page — backend, view, and JS

**Files:**
- Modify: `app/Http/Controllers/Admin/JadwalPelajaranController.php` (`create()` and `store()` methods)
- Modify: `resources/views/admin/jadwal-pelajaran/create.blade.php`
- Create: `resources/js/jadwal-pelajaran-create.js`
- Modify: `resources/js/app.js` (register the new Alpine component)
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `App\Enums\Hari::aktifDari(array $hariLiburMingguan): array` (existing), `App\Models\Kelas::lembaga(): BelongsTo` and `App\Models\Kelas::tahunAjaran(): BelongsTo` (existing, confirmed present), `App\Models\JamPelajaran::scopeIsPelajaran()` (existing).
- Produces: `create()` now passes `jamPelajaranPerHari` (a `Collection` of `['hari' => Hari, 'items' => Collection<JamPelajaran>]`, one entry per active hari that has at least one `is_pelajaran` slot) and `semester` (`?Semester`) to the view, replacing the old flat `jamPelajaranList`. `store()` now expects `jam_pelajaran_id` as an array in the request body — this is a breaking change to the form's contract, but `create.blade.php` is the only page that submits to `admin.jadwal-pelajaran.store` (verified: no other view references this route).

- [ ] **Step 1: Write the failing backend tests**

In `tests/Feature/Admin/JadwalPelajaranCrudTest.php`, replace this test (uses scalar `jam_pelajaran_id`):

```php
it('creates a jadwal pelajaran entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsJadwalManager($lembaga);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => $jam->id,
        'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ])->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->where('jam_pelajaran_id', $jam->id)->exists())->toBeTrue();
});
```

with:

```php
it('creates a jadwal pelajaran entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsJadwalManager($lembaga);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => [$jam->id],
        'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ])->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->where('jam_pelajaran_id', $jam->id)->exists())->toBeTrue();
});

it('creates jadwal pelajaran entries for multiple selected slots in one submit', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamSatu = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 1, 'is_pelajaran' => true, 'label' => 'Jam ke-1']);
    $jamDua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 2, 'is_pelajaran' => true, 'label' => 'Jam ke-2']);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsJadwalManager($lembaga);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => [$jamSatu->id, $jamDua->id],
        'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ])->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->where('jam_pelajaran_id', $jamSatu->id)->exists())->toBeTrue();
    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->where('jam_pelajaran_id', $jamDua->id)->exists())->toBeTrue();
    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->count())->toBe(2);
});

it('creates the non-colliding slots and reports the skipped one when one of several selected slots already has a jadwal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamSatu = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 1, 'is_pelajaran' => true, 'hari' => 'senin', 'label' => 'Jam ke-1']);
    $jamDua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 2, 'is_pelajaran' => true, 'hari' => 'senin', 'label' => 'Jam ke-2']);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamSatu->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => [$jamSatu->id, $jamDua->id],
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ]);

    $response->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));
    $response->assertSessionHas('status', fn ($status) => str_contains($status, 'Senin Jam ke-2') && str_contains($status, 'Dilewati'));

    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->where('jam_pelajaran_id', $jamDua->id)->exists())->toBeTrue();
    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->count())->toBe(2);
});

it('rejects the whole batch when every selected slot already has a jadwal for this kelas and semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamSatu = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 1, 'is_pelajaran' => true, 'hari' => 'senin']);
    $jamDua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 2, 'is_pelajaran' => true, 'hari' => 'senin']);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamSatu->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamDua->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    $manager = actingAsJadwalManager($lembaga);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => [$jamSatu->id, $jamDua->id],
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ])->assertSessionHasErrors('jam_pelajaran_id');

    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->count())->toBe(2);
});

it('rejects the entire batch when one of several selected jam_pelajaran_id belongs to a different pola jam', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id, 'pola_jam_id' => $polaA->id]);
    $jamValid = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true]);
    $jamLain = JamPelajaran::factory()->create(['pola_jam_id' => $polaLain->id, 'is_pelajaran' => true]);
    $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    $manager = actingAsJadwalManager($lembagaA);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => [$jamValid->id, $jamLain->id],
        'guru_id' => $guruA->id,
        'semester_id' => $semesterA->id,
    ])->assertNotFound();

    expect(JadwalPelajaran::where('kelas_id', $kelasA->id)->exists())->toBeFalse();
});
```

Replace this test (assumes flat `jamPelajaranList` view data):

```php
it('only offers is_pelajaran slots when creating a jadwal entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamBelajar = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 1, 'is_pelajaran' => true, 'label' => 'Jam ke-1']);
    $jamIstirahat = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 2, 'is_pelajaran' => false, 'label' => 'Istirahat']);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertViewHas('jamPelajaranList', function ($list) use ($jamBelajar, $jamIstirahat) {
        return $list->contains('id', $jamBelajar->id) && ! $list->contains('id', $jamIstirahat->id);
    });
});
```

with:

```php
it('only offers is_pelajaran slots when creating a jadwal entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamBelajar = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 1, 'is_pelajaran' => true, 'label' => 'Jam ke-1']);
    $jamIstirahat = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 2, 'is_pelajaran' => false, 'label' => 'Istirahat']);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertViewHas('jamPelajaranPerHari', function ($groups) use ($jamBelajar, $jamIstirahat) {
        $ids = $groups->flatMap(fn ($grup) => $grup['items']->pluck('id'));

        return $ids->contains($jamBelajar->id) && ! $ids->contains($jamIstirahat->id);
    });
});

it('groups the jam pelajaran options by hari in hari-aktif order', function () {
    $yayasan = Yayasan::factory()->create(['hari_libur_mingguan' => [0, 6]]);
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'rabu', 'urutan' => 1, 'is_pelajaran' => true]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'senin', 'urutan' => 1, 'is_pelajaran' => true]);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertViewHas('jamPelajaranPerHari', function ($groups) {
        return $groups->pluck('hari')->map(fn ($h) => $h->value)->all() === ['senin', 'rabu'];
    });
});
```

> Note: `Lembaga::factory()->create(['yayasan_id' => $yayasan->id])` already defaults `hari_libur_mingguan` to `[]` unless overridden — passing `hari_libur_mingguan` on the `Yayasan` factory above is a mistake to avoid; set it on the `Lembaga` factory instead, matching every other test in this file (e.g. `Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0, 6]])`). Use that corrected form when writing this test.

Replace this test (scalar payload):

```php
it('rejects a kelas_id belonging to another lembaga', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $jamA = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true]);
    $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    $manager = actingAsJadwalManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $tahunAjaranB->id]);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasB->id,
        'jam_pelajaran_id' => $jamA->id,
        'guru_id' => $guruA->id,
        'semester_id' => $semesterA->id,
    ])->assertNotFound();

    expect(JadwalPelajaran::where('kelas_id', $kelasB->id)->exists())->toBeFalse();
});
```

with:

```php
it('rejects a kelas_id belonging to another lembaga', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $jamA = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true]);
    $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    $manager = actingAsJadwalManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $tahunAjaranB->id]);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasB->id,
        'jam_pelajaran_id' => [$jamA->id],
        'guru_id' => $guruA->id,
        'semester_id' => $semesterA->id,
    ])->assertNotFound();

    expect(JadwalPelajaran::where('kelas_id', $kelasB->id)->exists())->toBeFalse();
});
```

Replace this test (scalar payload):

```php
it('rejects a guru_id belonging to another lembaga even when kelas_id is own', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id, 'pola_jam_id' => $polaA->id]);
    $jamA = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true]);
    $manager = actingAsJadwalManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $guruB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => $jamA->id,
        'guru_id' => $guruB->id,
        'semester_id' => $semesterA->id,
    ])->assertNotFound();

    expect(JadwalPelajaran::where('kelas_id', $kelasA->id)->exists())->toBeFalse();
});
```

with:

```php
it('rejects a guru_id belonging to another lembaga even when kelas_id is own', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id, 'pola_jam_id' => $polaA->id]);
    $jamA = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true]);
    $manager = actingAsJadwalManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $guruB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => [$jamA->id],
        'guru_id' => $guruB->id,
        'semester_id' => $semesterA->id,
    ])->assertNotFound();

    expect(JadwalPelajaran::where('kelas_id', $kelasA->id)->exists())->toBeFalse();
});
```

Replace this test (scalar payload):

```php
it('rejects a jadwal that mixes entities from different lembaga even for a yayasan-scoped user', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $jamA = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true]);
    $kelasA->update(['pola_jam_id' => $polaA->id]);
    $guruB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]); // different lembaga than everything else

    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_jadwal_mix_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['jadwal-pelajaran.kelola']);
    $manager = User::factory()->create(['lembaga_id' => null]);
    $manager->assignRole($role);
    // No active_lembaga_id in session — this yayasan-scoped user can see both lembaga A and B.

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => $jamA->id,
        'guru_id' => $guruB->id, // mismatched lembaga
        'semester_id' => $semesterA->id,
    ])->assertSessionHasErrors();

    expect(JadwalPelajaran::where('kelas_id', $kelasA->id)->exists())->toBeFalse();
});
```

with:

```php
it('rejects a jadwal that mixes entities from different lembaga even for a yayasan-scoped user', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $jamA = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true]);
    $kelasA->update(['pola_jam_id' => $polaA->id]);
    $guruB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]); // different lembaga than everything else

    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_jadwal_mix_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['jadwal-pelajaran.kelola']);
    $manager = User::factory()->create(['lembaga_id' => null]);
    $manager->assignRole($role);
    // No active_lembaga_id in session — this yayasan-scoped user can see both lembaga A and B.

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => [$jamA->id],
        'guru_id' => $guruB->id, // mismatched lembaga
        'semester_id' => $semesterA->id,
    ])->assertSessionHasErrors();

    expect(JadwalPelajaran::where('kelas_id', $kelasA->id)->exists())->toBeFalse();
});
```

Replace this test (scalar payload):

```php
it('rejects a jam_pelajaran_id belonging to a different pola jam than the kelas uses', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id, 'pola_jam_id' => $polaA->id]);
    $jamLain = JamPelajaran::factory()->create(['pola_jam_id' => $polaLain->id, 'is_pelajaran' => true]);
    $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    $manager = actingAsJadwalManager($lembagaA);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => $jamLain->id,
        'guru_id' => $guruA->id,
        'semester_id' => $semesterA->id,
    ])->assertNotFound();

    expect(JadwalPelajaran::where('kelas_id', $kelasA->id)->exists())->toBeFalse();
});
```

with:

```php
it('rejects a jam_pelajaran_id belonging to a different pola jam than the kelas uses', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id, 'pola_jam_id' => $polaA->id]);
    $jamLain = JamPelajaran::factory()->create(['pola_jam_id' => $polaLain->id, 'is_pelajaran' => true]);
    $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    $manager = actingAsJadwalManager($lembagaA);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => [$jamLain->id],
        'guru_id' => $guruA->id,
        'semester_id' => $semesterA->id,
    ])->assertNotFound();

    expect(JadwalPelajaran::where('kelas_id', $kelasA->id)->exists())->toBeFalse();
});
```

Replace this test (scalar payload):

```php
it('shows a friendly error instead of a raw SQL error on a duplicate kelas/jam/semester entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ])->assertSessionHasErrors()->assertStatus(302); // never a 500

    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->count())->toBe(1);
});
```

with:

```php
it('shows a friendly error instead of a raw SQL error on a duplicate kelas/jam/semester entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => [$jam->id], 'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ])->assertSessionHasErrors()->assertStatus(302); // never a 500

    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->count())->toBe(1);
});
```

Replace this test (scalar payload, and the error now lands on `jam_pelajaran_id` since the whole single-slot batch was skipped, not on `guru_id`):

```php
it('rejects double-booking a guru into two classes at the same jam and semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $kelasSatu = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $kelasDua = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelasSatu->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasDua->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ])->assertSessionHasErrors('guru_id');

    expect(JadwalPelajaran::where('kelas_id', $kelasDua->id)->exists())->toBeFalse();
});
```

with:

```php
it('rejects double-booking a guru into two classes at the same jam and semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $kelasSatu = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $kelasDua = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelasSatu->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasDua->id, 'jam_pelajaran_id' => [$jam->id], 'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ])->assertSessionHasErrors('jam_pelajaran_id');

    expect(JadwalPelajaran::where('kelas_id', $kelasDua->id)->exists())->toBeFalse();
});
```

Finally, append these new frontend-markup tests at the very end of the file:

```php
it('renders the jam pelajaran select as a multi-select grouped by hari', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'senin', 'is_pelajaran' => true, 'label' => 'Jam ke-1']);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertSee('name="jam_pelajaran_id[]"', false);
    $response->assertSee('multiple', false);
    $response->assertSee('<optgroup label="Senin"', false);
});

it('displays tahun ajaran and semester context on the create page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027']);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil']);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertSee('2026/2027');
    $response->assertSee('Ganjil');
});

it('shows a warning banner instead of the form when the kelas has no pola jam', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => null]);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertSee('Kelas ini belum punya Pola Jam');
    $response->assertDontSee('name="jam_pelajaran_id[]"', false);
});

it('renders a toast block for validation errors on the create page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->from(route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]))
        ->post(route('admin.jadwal-pelajaran.store'), [
            'kelas_id' => $kelas->id, 'jam_pelajaran_id' => [$jam->id], 'guru_id' => $guru->id, 'semester_id' => $semester->id,
        ])
        ->assertRedirect(route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $followUp = $this->actingAs($manager)->get($response->headers->get('Location'));
    $followUp->assertSee('$store.toast.push', false);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php`
Expected: FAIL — array-payload tests fail validation against the old scalar rule, `assertViewHas('jamPelajaranPerHari', ...)` fails because that key doesn't exist yet, and the markup tests fail because the old blade still renders a single-select.

- [ ] **Step 3: Implement the controller**

Replace `create()` and `store()` in `app/Http/Controllers/Admin/JadwalPelajaranController.php`:

```php
    public function create(Request $request): View
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $kelas = Kelas::with(['lembaga', 'tahunAjaran'])->findOrFail($request->query('kelas_id'));
        $semesterId = $request->query('semester_id');
        $semester = $semesterId ? Semester::find($semesterId) : null;

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

        return view('admin.jadwal-pelajaran.create', [
            'kelas' => $kelas,
            'semesterId' => $semesterId,
            'semester' => $semester,
            'jamPelajaranPerHari' => $jamPelajaranPerHari,
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
            'guruList' => Guru::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('jadwal-pelajaran.kelola');

        $data = $request->validate([
            'kelas_id' => ['required', 'integer'],
            'jam_pelajaran_id' => ['required', 'array', 'min:1'],
            'jam_pelajaran_id.*' => ['integer'],
            'mata_pelajaran_id' => ['nullable', 'integer'],
            'guru_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
        ]);

        $kelas = Kelas::find($data['kelas_id']);
        if (! $kelas) {
            abort(404);
        }

        $guru = Guru::find($data['guru_id']);
        if (! $guru) {
            abort(404);
        }

        $semester = Semester::find($data['semester_id']);
        if (! $semester) {
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

        if ($semester->lembaga_id !== $kelas->lembaga_id) {
            return back()->withErrors(['semester_id' => 'Semester harus berasal dari lembaga yang sama dengan kelas ini.'])->withInput();
        }

        if (isset($mataPelajaran) && $mataPelajaran->lembaga_id !== $kelas->lembaga_id) {
            return back()->withErrors(['mata_pelajaran_id' => 'Mata pelajaran harus berasal dari lembaga yang sama dengan kelas ini.'])->withInput();
        }

        $jamPelajaranIds = array_unique($data['jam_pelajaran_id']);
        $jamPelajaranList = JamPelajaran::whereIn('id', $jamPelajaranIds)
            ->where('pola_jam_id', $kelas->pola_jam_id)
            ->get();
        if ($jamPelajaranList->count() !== count($jamPelajaranIds)) {
            abort(404);
        }

        $berhasil = [];
        $dilewati = [];

        foreach ($jamPelajaranList as $jamPelajaran) {
            $duplikat = JadwalPelajaran::where('kelas_id', $kelas->id)
                ->where('jam_pelajaran_id', $jamPelajaran->id)
                ->where('semester_id', $semester->id)
                ->exists();
            if ($duplikat) {
                $dilewati[] = $this->formatSlot($jamPelajaran) . ' (kelas ini sudah punya jadwal di slot ini)';
                continue;
            }

            $guruBentrok = JadwalPelajaran::where('guru_id', $guru->id)
                ->where('jam_pelajaran_id', $jamPelajaran->id)
                ->where('semester_id', $semester->id)
                ->exists();
            if ($guruBentrok) {
                $dilewati[] = $this->formatSlot($jamPelajaran) . ' (guru sudah mengajar kelas lain di slot ini)';
                continue;
            }

            JadwalPelajaran::create([
                'kelas_id' => $kelas->id,
                'jam_pelajaran_id' => $jamPelajaran->id,
                'mata_pelajaran_id' => $data['mata_pelajaran_id'] ?? null,
                'guru_id' => $guru->id,
                'semester_id' => $semester->id,
            ]);
            $berhasil[] = $this->formatSlot($jamPelajaran);
        }

        if (empty($berhasil)) {
            return back()->withErrors([
                'jam_pelajaran_id' => 'Semua slot yang dipilih dilewati: ' . implode('; ', $dilewati) . '.',
            ])->withInput();
        }

        $status = 'Jadwal pelajaran berhasil ditambahkan untuk ' . implode(', ', $berhasil) . '.';
        if (! empty($dilewati)) {
            $status .= ' Dilewati: ' . implode('; ', $dilewati) . '.';
        }

        return redirect()->route('admin.jadwal-pelajaran.index', [
            'kelas_id' => $kelas->id,
            'semester_id' => $semester->id,
        ])->with('status', $status);
    }

    private function formatSlot(JamPelajaran $jamPelajaran): string
    {
        return $jamPelajaran->hari->label() . ' ' . $jamPelajaran->label;
    }
```

- [ ] **Step 4: Run backend tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --filter="creates a jadwal pelajaran entry|creates jadwal pelajaran entries for multiple|creates the non-colliding|rejects the whole batch when every selected slot|rejects the entire batch when one of several|only offers is_pelajaran slots|groups the jam pelajaran options|rejects a kelas_id|rejects a guru_id|rejects a jadwal that mixes|rejects a jam_pelajaran_id belonging|shows a friendly error|rejects double-booking"`
Expected: PASS (the 4 markup-only tests from Step 1 — multi-select rendering, tahun ajaran/semester context, warning banner, toast block — still FAIL, since the view hasn't changed yet).

- [ ] **Step 5: Rewrite the create view**

Replace the full contents of `resources/views/admin/jadwal-pelajaran/create.blade.php`:

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
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Jadwal — {{ $kelas->nama }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semesterId]) }}" class="font-semibold text-gray-700 hover:text-brand-600">Jadwal Pelajaran</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tambah</b>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-xl bg-gray-50 px-4 py-3 text-sm">
            <p><span class="text-gray-400">Tahun Ajaran</span> <span class="ml-1.5 font-semibold text-gray-800">{{ $kelas->tahunAjaran->nama }}</span></p>
            <p><span class="text-gray-400">Semester</span> <span class="ml-1.5 font-semibold text-gray-800">{{ $semester->nama ?? '—' }}</span></p>
            <p><span class="text-gray-400">Kelas</span> <span class="ml-1.5 font-semibold text-gray-800">{{ $kelas->nama }}</span></p>
        </div>

        @if ($jamPelajaranPerHari->isEmpty())
            <div class="flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-50 p-5 text-sm text-warning-700">
                <x-icon name="warning" class="mt-0.5 h-5 w-5 shrink-0 text-warning-500" />
                <div>
                    <p class="font-semibold">Kelas ini belum punya Pola Jam</p>
                    <p class="mt-1">Atur Pola Jam terlebih dahulu sebelum menambahkan jadwal pelajaran untuk kelas ini.</p>
                    <a href="{{ route('admin.pola-jam.index') }}" class="mt-2 inline-block font-semibold text-warning-800 underline">Buka halaman Pola Jam &rarr;</a>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('admin.jadwal-pelajaran.store') }}" x-data="jadwalPelajaranCreateForm()">
                @csrf
                <input type="hidden" name="kelas_id" value="{{ $kelas->id }}">
                <input type="hidden" name="semester_id" value="{{ $semesterId }}">

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700 border-b border-gray-100 pb-3">
                        <x-icon name="schedule" class="h-[15px] w-[15px] text-gray-400" />
                        Penempatan Slot &amp; Pengajar
                    </p>

                    <div>
                        <x-input-label value="Jam Pelajaran" />
                        <p class="mt-0.5 text-xs text-gray-400">Bisa pilih lebih dari satu slot sekaligus, mis. untuk mata pelajaran yang menempati 2 jam berturut-turut.</p>
                        <select
                            name="jam_pelajaran_id[]"
                            multiple
                            x-ref="jamPelajaranSelect"
                            x-init="initJamPelajaranSelect($refs.jamPelajaranSelect)"
                            class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        >
                            @foreach ($jamPelajaranPerHari as $grup)
                                <optgroup label="{{ $grup['hari']->label() }}">
                                    @foreach ($grup['items'] as $jam)
                                        <option value="{{ $jam->id }}" @selected(collect(old('jam_pelajaran_id', []))->contains($jam->id))>{{ substr($jam->jam_mulai, 0, 5) }}–{{ substr($jam->jam_selesai, 0, 5) }} ({{ $jam->label }})</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('jam_pelajaran_id')" class="mt-1.5" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Mata Pelajaran" />
                            <p class="mt-0.5 text-xs text-gray-400">Opsional untuk kelas PAUD.</p>
                            <select
                                name="mata_pelajaran_id"
                                x-ref="mataPelajaranSelect"
                                x-init="initMataPelajaranSelect($refs.mataPelajaranSelect)"
                                class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            >
                                <option value="">— Tidak ada —</option>
                                @foreach ($mataPelajaranList as $mapel)
                                    <option value="{{ $mapel->id }}" @selected(old('mata_pelajaran_id') == $mapel->id)>{{ $mapel->nama }}</option>
                                @endforeach
                            </select>
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
                                <option value="" disabled selected>— Pilih Guru —</option>
                                @foreach ($guruList as $guru)
                                    <option value="{{ $guru->id }}" @selected(old('guru_id') == $guru->id)>{{ $guru->nama }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('guru_id')" class="mt-1.5" />
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <x-primary-button type="submit">Simpan Jadwal</x-primary-button>
                    <a href="{{ route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semesterId]) }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Batal</a>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 6: Create the JS module**

Create `resources/js/jadwal-pelajaran-create.js`:

```js
import TomSelect from 'tom-select';

export function jadwalPelajaranCreateForm() {
    return {
        initJamPelajaranSelect(el) {
            new TomSelect(el, {
                create: false,
                placeholder: 'Pilih satu atau beberapa slot jam pelajaran...',
            });
        },

        initMataPelajaranSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari mata pelajaran...',
            });
        },

        initGuruSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari guru...',
            });
        },
    };
}
```

- [ ] **Step 7: Register the component in app.js**

In `resources/js/app.js`, add the import alongside the other Alpine component imports (right after `import { jadwalPelajaranFilter } from './jadwal-pelajaran-filter';`):

```js
import { jadwalPelajaranCreateForm } from './jadwal-pelajaran-create';
```

And add the registration alongside the other `Alpine.data(...)` calls (right after `Alpine.data('jadwalPelajaranFilter', jadwalPelajaranFilter);`):

```js
Alpine.data('jadwalPelajaranCreateForm', jadwalPelajaranCreateForm);
```

- [ ] **Step 8: Run the full backend+frontend test file**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php`
Expected: PASS — all tests in the file, including the 4 markup tests from Step 1.

- [ ] **Step 9: Build assets**

Run: `npm run build`
Expected: builds successfully with no errors (confirms the new JS module has no syntax errors and the Tom Select import resolves).

- [ ] **Step 10: Run the full test suite**

Run: `php artisan test`
Expected: all tests pass — confirms nothing else in the codebase depends on the old scalar `jam_pelajaran_id` shape or the old `jamPelajaranList` view variable.

- [ ] **Step 11: Commit**

```bash
git add app/Http/Controllers/Admin/JadwalPelajaranController.php resources/views/admin/jadwal-pelajaran/create.blade.php resources/js/jadwal-pelajaran-create.js resources/js/app.js tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat: allow selecting multiple jam pelajaran slots at once when creating a jadwal, add tom select and context display"
```

---

## Plan Self-Review Notes

- **Spec coverage**: all 10 confirmed points from the spec map to concrete steps — Tom Select on all 3 dropdowns (Step 5/6), Jam Pelajaran multi-select with optgroups (Step 3/5), two-tier batch validation (Step 3), Tahun Ajaran + Semester display (Step 3/5), toast blocks (Step 5), time format without seconds (Step 5, `substr(...,0,5)`), "(opsional utk PAUD)" moved to helper text (Step 5), warning banner for missing Pola Jam (Step 5).
- **Type consistency**: `formatSlot(JamPelajaran $jamPelajaran): string` is used identically in both the `$dilewati` and `$berhasil` branches of `store()`. The view's `jamPelajaranPerHari` shape (`Collection<{hari: Hari, items: Collection<JamPelajaran>}>`) is produced once in `create()` and consumed once in the Blade `@foreach` — no other file reads this variable.
- **No placeholders**: every code block is complete and literal; the test file edits show full before/after content rather than "update similarly."
- **Bug fix carried along**: the old `->orderBy('hari')` (alphabetical string sort) is replaced by `Hari::aktifDari()`-driven grouping, which orders by the lembaga's actual weekday order — this was flagged during the design discussion and is fixed here rather than as a separate task, since it's part of the same restructuring.
