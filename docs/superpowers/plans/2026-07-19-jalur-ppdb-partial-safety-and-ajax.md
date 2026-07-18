# Keamanan Hapus & Interaksi Tanpa Reload — Formulir Field, Dokumen Syarat, Seleksi & Tes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop deletes on Formulir Field / Dokumen Syarat / Seleksi & Tes from either crashing with a raw SQL error or silently wiping registrant data, and convert all three CRUD sections on the Jalur PPDB edit page from full-page-reload forms to Alpine.js + fetch interactions that update only their own card.

**Architecture:** Each of the three controllers gains a guard in `destroy()` that checks a new `hasMany` relation to the item's registrant-data table (`jawabanFormulir`, `dokumenPendaftaran`, `hasilSeleksi`) before deleting, returning a blocked response naming the count if any exist — this makes the previously-crashing/-silent scenarios impossible the same way the earlier Jalur PPDB deactivation guard did. `hasil_seleksi`'s FK changes from `cascadeOnDelete()` to the default restrict, matching the other two tables, as defense-in-depth under the new guard. Both `store()` and `destroy()` on all three controllers add a `$request->wantsJson()` branch (mirroring the existing pattern in `RoleController`), and the three Blade partials become Alpine components (`resources/js/*-list.js`, registered in `app.js` exactly like `roles-table.js`/`role-form.js`) that `fetch()` those endpoints and update local state — no navigation on add or delete.

**Tech Stack:** Laravel 12, Pest 4, Alpine.js 3.4.2 (already a dependency), the existing `Alpine.store('toast')` (already wired into the shared layout via `<x-toast />`).

## Global Constraints

- Only `destroy()` gains the block-if-in-use guard; `store()` is unaffected except for the new JSON response branch.
- `hasil_seleksi.seleksi_ppdb_id` FK changes from `cascadeOnDelete()` to restrict (default) — this is a schema change via a new migration, not an edit to the original `create_hasil_seleksi_table` migration.
- No changes to `Pendaftaran`, `DokumenPendaftaran`, `JawabanFormulirPendaftaran`, `HasilSeleksi` models/controllers, or the public SPMB flow.
- `<x-badge>` and `<x-input-error>` are server-rendered Blade components — they cannot reactively bind to client-side Alpine state. Anywhere the new Alpine templates need to show a badge or field error that depends on runtime JS state, use plain HTML (`<span>`/`<p>`) with Alpine `:class`/`x-text`/`x-if` bindings instead, matching the exact visual classes those components already produce.
- Every task ends with a separate commit.

---

## Task 1: Model relations + `hasil_seleksi` FK migration

**Files:**
- Modify: `app/Models/FormulirField.php`
- Modify: `app/Models/DokumenSyaratPpdb.php`
- Modify: `app/Models/SeleksiPpdb.php`
- Create: `database/migrations/2026_07_19_100000_restrict_seleksi_ppdb_delete_on_hasil_seleksi.php`
- Test: `tests/Feature/Admin/FormulirFieldTest.php`, `tests/Feature/Admin/DokumenSyaratTest.php`, `tests/Feature/Admin/SeleksiTest.php` (append to each)

**Interfaces:**
- Produces: `FormulirField::jawabanFormulir(): HasMany`, `DokumenSyaratPpdb::dokumenPendaftaran(): HasMany`, `SeleksiPpdb::hasilSeleksi(): HasMany`. Tasks 2-4 call `->exists()`/`->count()` on these inside `destroy()`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/DokumenSyaratTest.php` (add `use App\Models\DokumenPendaftaran;` to the top imports alongside the existing ones):

```php
it('exposes the dokumenPendaftaran relation with real registrant document data', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();
    $dokumen = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);

    DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id,
        'dokumen_syarat_ppdb_id' => $dokumen->id,
        'file_path' => 'dokumen/akta.pdf',
        'nama_file_asli' => 'akta.pdf',
        'mime_type' => 'application/pdf',
    ]);

    expect($dokumen->dokumenPendaftaran()->count())->toBe(1);
});
```

Append to `tests/Feature/Admin/FormulirFieldTest.php` (add `use App\Models\JawabanFormulirPendaftaran;` to the top imports):

```php
it('exposes the jawabanFormulir relation with real registrant answer data', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Field A', 'field_type' => 'text']);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);

    JawabanFormulirPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id,
        'formulir_field_id' => $field->id,
        'nilai' => 'jawaban contoh',
    ]);

    expect($field->jawabanFormulir()->count())->toBe(1);
});
```

Append to `tests/Feature/Admin/SeleksiTest.php` (add `use App\Models\HasilSeleksi;` to the top imports):

```php
it('exposes the hasilSeleksi relation with real registrant result data', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id, 'jadwal' => '2026-08-15 09:00:00',
    ]);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);

    HasilSeleksi::create(['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $seleksi->id, 'nilai' => 80]);

    expect($seleksi->hasilSeleksi()->count())->toBe(1);
});

it('restricts deleting a seleksi_ppdb row at the database level when hasil_seleksi references it', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id, 'jadwal' => '2026-08-15 09:00:00',
    ]);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);
    HasilSeleksi::create(['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $seleksi->id, 'nilai' => 80]);

    expect(fn () => $seleksi->delete())->toThrow(\Illuminate\Database\QueryException::class);
    expect(SeleksiPpdb::find($seleksi->id))->not->toBeNull();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=FormulirFieldTest` — expect the new relation test to FAIL (method doesn't exist).
Run: `php artisan test --filter=DokumenSyaratTest` — expect the new relation test to FAIL.
Run: `php artisan test --filter=SeleksiTest` — expect the new relation test to FAIL, and the DB-restrict test to FAIL differently (it expects a `QueryException` to be thrown, but today's schema cascades instead, so nothing throws and the assertion fails).

- [ ] **Step 3: Add the relation to `FormulirField`**

In `app/Models/FormulirField.php`, add the import:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

and this method after `jalurPpdb()`:

```php
    public function jawabanFormulir(): HasMany
    {
        return $this->hasMany(JawabanFormulirPendaftaran::class);
    }
```

- [ ] **Step 4: Add the relation to `DokumenSyaratPpdb`**

In `app/Models/DokumenSyaratPpdb.php`, add the same `HasMany` import, and this method after `jalurPpdb()`:

```php
    public function dokumenPendaftaran(): HasMany
    {
        return $this->hasMany(DokumenPendaftaran::class);
    }
```

- [ ] **Step 5: Add the relation to `SeleksiPpdb`**

In `app/Models/SeleksiPpdb.php`, add the same `HasMany` import, and this method after `jenisTesMaster()`:

```php
    public function hasilSeleksi(): HasMany
    {
        return $this->hasMany(HasilSeleksi::class);
    }
```

- [ ] **Step 6: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_seleksi', function (Blueprint $table) {
            $table->dropForeign(['seleksi_ppdb_id']);
            $table->foreign('seleksi_ppdb_id')->references('id')->on('seleksi_ppdb')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hasil_seleksi', function (Blueprint $table) {
            $table->dropForeign(['seleksi_ppdb_id']);
            $table->foreign('seleksi_ppdb_id')->references('id')->on('seleksi_ppdb')->cascadeOnDelete();
        });
    }
};
```

Save this to `database/migrations/2026_07_19_100000_restrict_seleksi_ppdb_delete_on_hasil_seleksi.php`.

- [ ] **Step 7: Run the migration**

Run: `php artisan migrate`
Expected: `2026_07_19_100000_restrict_seleksi_ppdb_delete_on_hasil_seleksi ... DONE` with no errors.

- [ ] **Step 8: Run the tests to verify they pass**

Run: `php artisan test --filter=FormulirFieldTest` — expect all pass.
Run: `php artisan test --filter=DokumenSyaratTest` — expect all pass.
Run: `php artisan test --filter=SeleksiTest` — expect all pass, including the new DB-restrict test.

- [ ] **Step 9: Commit**

```bash
git add app/Models/FormulirField.php app/Models/DokumenSyaratPpdb.php app/Models/SeleksiPpdb.php database/migrations/2026_07_19_100000_restrict_seleksi_ppdb_delete_on_hasil_seleksi.php tests/Feature/Admin/FormulirFieldTest.php tests/Feature/Admin/DokumenSyaratTest.php tests/Feature/Admin/SeleksiTest.php
git commit -m "feat: add registrant-data relations to Jalur PPDB partials, restrict hasil_seleksi FK"
```

---

## Task 2: `DokumenSyaratController` — block-if-in-use guard + JSON responses

**Files:**
- Modify: `app/Http/Controllers/Admin/DokumenSyaratController.php`
- Test: `tests/Feature/Admin/DokumenSyaratTest.php` (append)

**Interfaces:**
- Consumes: `DokumenSyaratPpdb::dokumenPendaftaran(): HasMany` from Task 1.
- Produces: `store()`/`destroy()` respond with JSON (`{data: ...}` / `{message: ...}` / `{message, errors}`) when the request has `Accept: application/json`. Task 5 (frontend) is the consumer.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/DokumenSyaratTest.php`:

```php
it('rejects deleting a dokumen syarat that already has a registrant document', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();
    $dokumen = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);
    DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $dokumen->id,
        'file_path' => 'dokumen/akta.pdf', 'nama_file_asli' => 'akta.pdf', 'mime_type' => 'application/pdf',
    ]);

    $this->actingAs($user)->delete(route('admin.dokumen-syarat.destroy', $dokumen))
        ->assertSessionHasErrors('dokumen_syarat');

    expect(DokumenSyaratPpdb::find($dokumen->id))->not->toBeNull();
});

it('names the related document count in the deletion error message', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();
    $dokumen = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);
    DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $dokumen->id,
        'file_path' => 'dokumen/akta.pdf', 'nama_file_asli' => 'akta.pdf', 'mime_type' => 'application/pdf',
    ]);

    $this->actingAs($user)->delete(route('admin.dokumen-syarat.destroy', $dokumen));

    expect(session('errors')->get('dokumen_syarat')[0])->toContain('1 dokumen');
});

it('responds with JSON on store when requested', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();

    $response = $this->actingAs($user)->postJson(route('admin.dokumen-syarat.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'nama_dokumen' => 'Kartu Keluarga',
        'wajib' => '1',
    ]);

    $response->assertCreated();
    expect($response->json('data.nama_dokumen'))->toBe('Kartu Keluarga');
});

it('responds with a JSON 422 and the correct message when a blocked deletion is requested via AJAX', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();
    $dokumen = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);
    DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $dokumen->id,
        'file_path' => 'dokumen/akta.pdf', 'nama_file_asli' => 'akta.pdf', 'mime_type' => 'application/pdf',
    ]);

    $blocked = $this->actingAs($user)->deleteJson(route('admin.dokumen-syarat.destroy', $dokumen));
    $blocked->assertStatus(422);
    expect($blocked->json('message'))->toContain('1 dokumen');
    expect(DokumenSyaratPpdb::find($dokumen->id))->not->toBeNull();
});

it('responds with a JSON success message when an unblocked deletion is requested via AJAX', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();
    $dokumen = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Kartu Keluarga']);

    $response = $this->actingAs($user)->deleteJson(route('admin.dokumen-syarat.destroy', $dokumen));

    $response->assertOk();
    expect($response->json('message'))->toBe('Dokumen syarat berhasil dihapus.');
    expect(DokumenSyaratPpdb::find($dokumen->id))->toBeNull();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=DokumenSyaratTest`
Expected: the 5 new tests FAIL — the controller doesn't guard `destroy()` or respond with JSON yet.

- [ ] **Step 3: Replace the controller**

Replace the full content of `app/Http/Controllers/Admin/DokumenSyaratController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\DokumenSyaratPpdb;
use App\Models\JalurPpdb;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;

class DokumenSyaratController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('dokumen-syarat.create');

        $data = $request->validate([
            'jalur_ppdb_id' => ['required', Rule::exists('jalur_ppdb', 'id')->where(fn ($query) => $query->whereIn('id', JalurPpdb::pluck('id')))],
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'wajib' => ['nullable', 'boolean'],
        ]);

        $jalur = JalurPpdb::findOrFail($data['jalur_ppdb_id']);

        $dokumen = DokumenSyaratPpdb::create([
            'jalur_ppdb_id' => $jalur->id,
            'nama_dokumen' => $data['nama_dokumen'],
            'wajib' => $request->boolean('wajib', true),
            'urutan' => $jalur->dokumenSyarat()->count(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['data' => $dokumen], 201);
        }

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Dokumen syarat berhasil ditambahkan.');
    }

    public function destroy(Request $request, DokumenSyaratPpdb $dokumenSyarat): RedirectResponse|JsonResponse
    {
        $this->authorize('dokumen-syarat.delete');

        $jumlahDokumen = $dokumenSyarat->dokumenPendaftaran()->count();
        if ($jumlahDokumen > 0) {
            return $this->errorResponse(
                $request,
                "Tidak bisa dihapus, sudah ada {$jumlahDokumen} dokumen terkait dari calon murid."
            );
        }

        $jalur = $dokumenSyarat->jalurPpdb;
        $dokumenSyarat->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Dokumen syarat berhasil dihapus.']);
        }

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Dokumen syarat berhasil dihapus.');
    }

    private function errorResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['dokumen_syarat' => $message]);
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=DokumenSyaratTest`
Expected: `12 passed` (7 pre-existing + 1 from Task 1 + 5 new — note: `'deletes a dokumen syarat'` from before Task 1 still passes unchanged, since a dokumen with zero related documents is never blocked).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/DokumenSyaratController.php tests/Feature/Admin/DokumenSyaratTest.php
git commit -m "feat: block deleting a dokumen syarat still referenced by a registrant document"
```

---

## Task 3: `FormulirFieldController` — block-if-in-use guard + JSON responses

**Files:**
- Modify: `app/Http/Controllers/Admin/FormulirFieldController.php`
- Test: `tests/Feature/Admin/FormulirFieldTest.php` (append)

**Interfaces:**
- Consumes: `FormulirField::jawabanFormulir(): HasMany` from Task 1.
- Produces: same JSON contract shape as Task 2, consumed by Task 6.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/FormulirFieldTest.php`:

```php
it('rejects deleting a formulir field that already has a registrant answer', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Field A', 'field_type' => 'text']);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);
    JawabanFormulirPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'formulir_field_id' => $field->id, 'nilai' => 'jawaban',
    ]);

    $this->actingAs($user)->delete(route('admin.formulir-field.destroy', $field))
        ->assertSessionHasErrors('formulir_field');

    expect(FormulirField::find($field->id))->not->toBeNull();
});

it('names the related answer count in the deletion error message', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Field A', 'field_type' => 'text']);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);
    JawabanFormulirPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'formulir_field_id' => $field->id, 'nilai' => 'jawaban',
    ]);

    $this->actingAs($user)->delete(route('admin.formulir-field.destroy', $field));

    expect(session('errors')->get('formulir_field')[0])->toContain('1 jawaban');
});

it('responds with JSON on store when requested', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();

    $response = $this->actingAs($user)->postJson(route('admin.formulir-field.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'label' => 'Field Baru',
        'field_type' => 'text',
    ]);

    $response->assertCreated();
    expect($response->json('data.label'))->toBe('Field Baru');
});

it('responds with a JSON 422 including field errors for the select-options rule when requested via AJAX', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();

    $response = $this->actingAs($user)->postJson(route('admin.formulir-field.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'label' => 'Pilihan',
        'field_type' => 'select',
        'options' => 'Satu',
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.options.0'))->toContain('minimal 2 opsi');
});

it('responds with a JSON 422 and the correct message when a blocked deletion is requested via AJAX', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Field A', 'field_type' => 'text']);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);
    JawabanFormulirPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'formulir_field_id' => $field->id, 'nilai' => 'jawaban',
    ]);

    $response = $this->actingAs($user)->deleteJson(route('admin.formulir-field.destroy', $field));

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('1 jawaban');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=FormulirFieldTest`
Expected: the 5 new tests FAIL.

- [ ] **Step 3: Replace the controller**

Replace the full content of `app/Http/Controllers/Admin/FormulirFieldController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\FormulirField;
use App\Models\JalurPpdb;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;

class FormulirFieldController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('formulir-field.create');

        $data = $request->validate([
            'jalur_ppdb_id' => ['required', Rule::exists('jalur_ppdb', 'id')->where(fn ($query) => $query->whereIn('id', JalurPpdb::pluck('id')))],
            'label' => ['required', 'string', 'max:255'],
            'field_type' => ['required', 'in:text,textarea,number,date,select,file'],
            'is_required' => ['nullable', 'boolean'],
            'options' => ['required_if:field_type,select', 'nullable', 'string'],
        ]);

        $jalur = JalurPpdb::findOrFail($data['jalur_ppdb_id']);

        $options = null;
        if ($data['field_type'] === 'select') {
            $options = array_values(array_filter(array_map('trim', explode("\n", $data['options'] ?? ''))));

            if (count($options) < 2) {
                $message = 'Field bertipe pilihan butuh minimal 2 opsi (satu opsi per baris).';

                if ($request->wantsJson()) {
                    return response()->json(['message' => $message, 'errors' => ['options' => [$message]]], 422);
                }

                return back()->withErrors(['options' => $message])->withInput();
            }
        }

        $field = FormulirField::create([
            'jalur_ppdb_id' => $jalur->id,
            'label' => $data['label'],
            'field_type' => $data['field_type'],
            'options' => $options,
            'is_required' => $request->boolean('is_required'),
            'urutan' => $jalur->formulirField()->count(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['data' => $field], 201);
        }

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Field formulir berhasil ditambahkan.');
    }

    public function destroy(Request $request, FormulirField $formulirField): RedirectResponse|JsonResponse
    {
        $this->authorize('formulir-field.delete');

        $jumlahJawaban = $formulirField->jawabanFormulir()->count();
        if ($jumlahJawaban > 0) {
            return $this->errorResponse(
                $request,
                "Tidak bisa dihapus, sudah ada {$jumlahJawaban} jawaban terkait dari calon murid."
            );
        }

        $jalur = $formulirField->jalurPpdb;
        $formulirField->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Field formulir berhasil dihapus.']);
        }

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Field formulir berhasil dihapus.');
    }

    private function errorResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['formulir_field' => $message]);
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=FormulirFieldTest`
Expected: `13 passed` (7 pre-existing + 1 from Task 1 + 5 new).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/FormulirFieldController.php tests/Feature/Admin/FormulirFieldTest.php
git commit -m "feat: block deleting a formulir field still referenced by a registrant answer"
```

---

## Task 4: `SeleksiController` — block-if-in-use guard + JSON responses

**Files:**
- Modify: `app/Http/Controllers/Admin/SeleksiController.php`
- Test: `tests/Feature/Admin/SeleksiTest.php` (append)

**Interfaces:**
- Consumes: `SeleksiPpdb::hasilSeleksi(): HasMany` from Task 1.
- Produces: `store()`'s JSON success response includes `data` eager-loaded with `gelombangPpdb`/`jenisTesMaster` (serialized as `gelombang_ppdb`/`jenis_tes_master`) — Task 7's frontend needs these names to render a newly-added row without a follow-up request.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/SeleksiTest.php`:

```php
it('rejects deleting a seleksi row that already has a registrant result', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id, 'jadwal' => '2026-08-15 09:00:00',
    ]);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);
    HasilSeleksi::create(['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $seleksi->id, 'nilai' => 80]);

    $this->actingAs($user)->delete(route('admin.seleksi.destroy', $seleksi))
        ->assertSessionHasErrors('seleksi');

    expect(SeleksiPpdb::find($seleksi->id))->not->toBeNull();
});

it('names the related result count in the deletion error message', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id, 'jadwal' => '2026-08-15 09:00:00',
    ]);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);
    HasilSeleksi::create(['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $seleksi->id, 'nilai' => 80]);

    $this->actingAs($user)->delete(route('admin.seleksi.destroy', $seleksi));

    expect(session('errors')->get('seleksi')[0])->toContain('1 hasil penilaian');
});

it('responds with JSON on store when requested, including the loaded gelombang and jenis tes names', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();

    $response = $this->actingAs($user)->postJson(route('admin.seleksi.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => '2026-08-15 09:00',
    ]);

    $response->assertCreated();
    expect($response->json('data.gelombang_ppdb.nama'))->toBe('Gelombang 1');
    expect($response->json('data.jenis_tes_master.nama'))->toBe('Tes Tulis');
});

it('responds with a JSON 422 including field errors for the tahun-ajaran-mismatch rule when requested via AJAX', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();
    $tahunLain = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => false,
    ]);
    $gelombangTahunLain = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLain->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => '2025-08-01', 'tanggal_tutup' => '2025-09-01', 'kuota' => 40,
    ]);

    $response = $this->actingAs($user)->postJson(route('admin.seleksi.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombangTahunLain->id,
        'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => '2026-08-15 09:00',
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.gelombang_ppdb_id.0'))->toContain('tahun ajaran yang sama');
});

it('responds with a JSON 422 and the correct message when a blocked deletion is requested via AJAX', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id, 'jadwal' => '2026-08-15 09:00:00',
    ]);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);
    HasilSeleksi::create(['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $seleksi->id, 'nilai' => 80]);

    $response = $this->actingAs($user)->deleteJson(route('admin.seleksi.destroy', $seleksi));

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('1 hasil penilaian');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=SeleksiTest`
Expected: the 5 new tests FAIL.

- [ ] **Step 3: Replace the controller**

Replace the full content of `app/Http/Controllers/Admin/SeleksiController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\SeleksiPpdb;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;

class SeleksiController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('seleksi.create');

        $data = $request->validate([
            'jalur_ppdb_id' => ['required', Rule::exists('jalur_ppdb', 'id')->where(fn ($query) => $query->whereIn('id', JalurPpdb::pluck('id')))],
            'gelombang_ppdb_id' => ['required', Rule::exists('gelombang_ppdb', 'id')->where(fn ($query) => $query->whereIn('id', GelombangPpdb::pluck('id')))],
            'jenis_tes_master_id' => ['required', Rule::exists('jenis_tes_master', 'id')->where(fn ($query) => $query->whereIn('id', JenisTesMaster::pluck('id')))],
            'jadwal' => ['required', 'date'],
            'kriteria_kelulusan' => ['nullable', 'string', 'max:2000'],
            'bobot' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $jalur = JalurPpdb::findOrFail($data['jalur_ppdb_id']);
        $gelombang = GelombangPpdb::findOrFail($data['gelombang_ppdb_id']);

        if ($gelombang->tahun_ajaran_id !== $jalur->tahun_ajaran_id) {
            $message = 'Gelombang yang dipilih bukan dari tahun ajaran yang sama dengan jalur ini.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $message, 'errors' => ['gelombang_ppdb_id' => [$message]]], 422);
            }

            return back()->withErrors(['gelombang_ppdb_id' => $message])->withInput();
        }

        $seleksi = SeleksiPpdb::create($data);

        if ($request->wantsJson()) {
            return response()->json(['data' => $seleksi->load(['gelombangPpdb', 'jenisTesMaster'])], 201);
        }

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Jadwal seleksi berhasil ditambahkan.');
    }

    public function destroy(Request $request, SeleksiPpdb $seleksi): RedirectResponse|JsonResponse
    {
        $this->authorize('seleksi.delete');

        $jumlahHasil = $seleksi->hasilSeleksi()->count();
        if ($jumlahHasil > 0) {
            return $this->errorResponse(
                $request,
                "Tidak bisa dihapus, sudah ada {$jumlahHasil} hasil penilaian terkait dari calon murid."
            );
        }

        $jalur = $seleksi->jalurPpdb;
        $seleksi->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jadwal seleksi berhasil dihapus.']);
        }

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Jadwal seleksi berhasil dihapus.');
    }

    private function errorResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['seleksi' => $message]);
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=SeleksiTest`
Expected: `14 passed` (7 pre-existing + 2 from Task 1 + 5 new).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/SeleksiController.php tests/Feature/Admin/SeleksiTest.php
git commit -m "feat: block deleting a seleksi schedule still referenced by a registrant result"
```

---

## Task 5: Frontend — Dokumen Syarat (Alpine, no reload)

**Files:**
- Create: `resources/js/dokumen-syarat-list.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/admin/jalur-ppdb/partials/dokumen-syarat.blade.php`

**Interfaces:**
- Consumes: `admin.dokumen-syarat.store`/`admin.dokumen-syarat.destroy` JSON contract from Task 2.

- [ ] **Step 1: Create the Alpine component**

```js
export function dokumenSyaratList(config) {
    return {
        items: config.initialItems,
        jalurId: config.jalurId,
        storeUrl: config.storeUrl,
        deleteUrlTemplate: config.deleteUrlTemplate,
        form: { nama_dokumen: '', wajib: true },
        errors: {},
        submitting: false,

        async addItem() {
            this.submitting = true;
            this.errors = {};

            try {
                const response = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ jalur_ppdb_id: this.jalurId, ...this.form }),
                });

                const json = await response.json();

                if (response.status === 422) {
                    this.errors = json.errors ?? {};
                    Alpine.store('toast').push('error', json.message ?? 'Periksa kembali form.');
                    return;
                }

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menambah dokumen.');
                    return;
                }

                this.items.push(json.data);
                this.form = { nama_dokumen: '', wajib: true };
                Alpine.store('toast').push('success', 'Dokumen syarat berhasil ditambahkan.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menambah dokumen.');
            } finally {
                this.submitting = false;
            }
        },

        async deleteItem(item) {
            if (!confirm(`Hapus dokumen "${item.nama_dokumen}"?`)) {
                return;
            }

            try {
                const response = await fetch(this.deleteUrlTemplate.replace('__ID__', item.id), {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menghapus dokumen.');
                    return;
                }

                this.items = this.items.filter((existing) => existing.id !== item.id);
                Alpine.store('toast').push('success', json.message ?? 'Dokumen syarat berhasil dihapus.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menghapus dokumen.');
            }
        },
    };
}
```

Save this to `resources/js/dokumen-syarat-list.js`.

- [ ] **Step 2: Register the component in `app.js`**

In `resources/js/app.js`, add the import alongside the existing ones:

```js
import { dokumenSyaratList } from './dokumen-syarat-list';
```

and the registration alongside the existing `Alpine.data(...)` calls:

```js
Alpine.data('dokumenSyaratList', dokumenSyaratList);
```

- [ ] **Step 3: Replace the Blade partial**

Replace the full content of `resources/views/admin/jalur-ppdb/partials/dokumen-syarat.blade.php`:

```blade
<div
    class="rounded-2xl border border-gray-200 bg-white shadow-card"
    x-data="dokumenSyaratList({
        initialItems: @js($jalur->dokumenSyarat),
        jalurId: {{ $jalur->id }},
        storeUrl: @js(route('admin.dokumen-syarat.store')),
        deleteUrlTemplate: @js(route('admin.dokumen-syarat.destroy', ['dokumenSyarat' => '__ID__'])),
    })"
>
    <div class="border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Dokumen Syarat</p>
        <p class="mt-0.5 text-sm text-gray-500">Daftar dokumen yang harus diunggah calon murid pada jalur ini.</p>
    </div>

    <ul class="divide-y divide-gray-100 px-5">
        <template x-if="items.length === 0">
            <li class="py-6 text-center text-sm text-gray-500">Belum ada dokumen syarat.</li>
        </template>
        <template x-for="item in items" :key="item.id">
            <li class="flex items-center justify-between py-3">
                <span class="flex items-center gap-2 text-sm text-gray-900">
                    <span x-text="item.nama_dokumen"></span>
                    <span
                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="item.wajib ? 'bg-brand-50 text-brand-600' : 'bg-gray-100 text-gray-600'"
                        x-text="item.wajib ? 'Wajib' : 'Opsional'"
                    ></span>
                </span>
                <button type="button" @click="deleteItem(item)" class="text-sm font-semibold text-error-600 hover:text-error-700">Hapus</button>
            </li>
        </template>
    </ul>

    <form @submit.prevent="addItem()" class="flex flex-wrap items-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-4">
        <div class="flex-1">
            <x-input-label value="Nama Dokumen" />
            <x-text-input type="text" x-model="form.nama_dokumen" placeholder="mis. Akta Kelahiran" class="mt-1.5" />
            <template x-if="errors.nama_dokumen">
                <p class="mt-1.5 text-sm text-error-600" x-text="errors.nama_dokumen[0]"></p>
            </template>
        </div>
        <label class="flex items-center gap-2 pb-2.5 text-sm text-gray-700">
            <input type="checkbox" x-model="form.wajib" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
            Wajib
        </label>
        <x-secondary-button type="submit" x-bind:disabled="submitting">Tambah Dokumen</x-secondary-button>
    </form>
</div>
```

- [ ] **Step 4: Build frontend assets**

Run: `npm run build`
Expected: clean build, no errors — confirms `dokumen-syarat-list.js` has no syntax errors and `app.js` still bundles correctly.

- [ ] **Step 5: Verify Blade compiles**

Run: `php artisan view:cache && php artisan view:clear`
Expected: no syntax errors.

- [ ] **Step 6: Run the regression tests**

Run: `php artisan test --filter=DokumenSyaratTest`
Expected: `12 passed` — the controller-level tests from Task 2 don't touch the view at all (they call the routes directly), so they must stay green untouched by this purely-frontend task.

- [ ] **Step 7: Commit**

```bash
git add resources/js/dokumen-syarat-list.js resources/js/app.js resources/views/admin/jalur-ppdb/partials/dokumen-syarat.blade.php
git commit -m "feat: convert Dokumen Syarat CRUD to Alpine, no page reload on add/delete"
```

---

## Task 6: Frontend — Formulir Field (Alpine, no reload)

**Files:**
- Create: `resources/js/formulir-field-list.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/admin/jalur-ppdb/partials/formulir-field.blade.php`

**Interfaces:**
- Consumes: `admin.formulir-field.store`/`admin.formulir-field.destroy` JSON contract from Task 3.

- [ ] **Step 1: Create the Alpine component**

```js
export function formulirFieldList(config) {
    return {
        items: config.initialItems,
        jalurId: config.jalurId,
        storeUrl: config.storeUrl,
        deleteUrlTemplate: config.deleteUrlTemplate,
        form: { label: '', field_type: 'text', is_required: false, options: '' },
        errors: {},
        submitting: false,

        async addItem() {
            this.submitting = true;
            this.errors = {};

            try {
                const response = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ jalur_ppdb_id: this.jalurId, ...this.form }),
                });

                const json = await response.json();

                if (response.status === 422) {
                    this.errors = json.errors ?? {};
                    Alpine.store('toast').push('error', json.message ?? 'Periksa kembali form.');
                    return;
                }

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menambah field.');
                    return;
                }

                this.items.push(json.data);
                this.form = { label: '', field_type: 'text', is_required: false, options: '' };
                Alpine.store('toast').push('success', 'Field formulir berhasil ditambahkan.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menambah field.');
            } finally {
                this.submitting = false;
            }
        },

        async deleteItem(item) {
            if (!confirm(`Hapus field "${item.label}"?`)) {
                return;
            }

            try {
                const response = await fetch(this.deleteUrlTemplate.replace('__ID__', item.id), {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menghapus field.');
                    return;
                }

                this.items = this.items.filter((existing) => existing.id !== item.id);
                Alpine.store('toast').push('success', json.message ?? 'Field formulir berhasil dihapus.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menghapus field.');
            }
        },
    };
}
```

Save this to `resources/js/formulir-field-list.js`.

- [ ] **Step 2: Register the component in `app.js`**

In `resources/js/app.js`, add the import alongside the existing ones:

```js
import { formulirFieldList } from './formulir-field-list';
```

and the registration alongside the existing `Alpine.data(...)` calls:

```js
Alpine.data('formulirFieldList', formulirFieldList);
```

- [ ] **Step 3: Replace the Blade partial**

Replace the full content of `resources/views/admin/jalur-ppdb/partials/formulir-field.blade.php`:

```blade
<div
    class="rounded-2xl border border-gray-200 bg-white shadow-card"
    x-data="formulirFieldList({
        initialItems: @js($jalur->formulirField),
        jalurId: {{ $jalur->id }},
        storeUrl: @js(route('admin.formulir-field.store')),
        deleteUrlTemplate: @js(route('admin.formulir-field.destroy', ['formulirField' => '__ID__'])),
    })"
>
    <div class="border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Formulir Field</p>
        <p class="mt-0.5 text-sm text-gray-500">Field tambahan di luar data wajib Dapodik, khusus untuk jalur ini.</p>
    </div>

    <ul class="divide-y divide-gray-100 px-5">
        <template x-if="items.length === 0">
            <li class="py-6 text-center text-sm text-gray-500">Belum ada field tambahan.</li>
        </template>
        <template x-for="item in items" :key="item.id">
            <li class="flex items-center justify-between py-3">
                <div>
                    <span class="text-sm font-semibold text-gray-900" x-text="item.label"></span>
                    <span class="ml-2 text-xs uppercase text-gray-500" x-text="item.field_type"></span>
                    <span
                        x-show="item.is_required"
                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold bg-brand-50 text-brand-600"
                    >Wajib</span>
                    <template x-if="item.field_type === 'select' && item.options && item.options.length">
                        <p class="mt-0.5 text-xs text-gray-500">Opsi: <span x-text="(item.options ?? []).join(', ')"></span></p>
                    </template>
                </div>
                <button type="button" @click="deleteItem(item)" class="text-sm font-semibold text-error-600 hover:text-error-700">Hapus</button>
            </li>
        </template>
    </ul>

    <form @submit.prevent="addItem()" class="space-y-3 border-t border-gray-200 bg-gray-50 px-5 py-4">
        <div class="flex flex-wrap items-end gap-2">
            <div class="flex-1">
                <x-input-label value="Label Field" />
                <x-text-input type="text" x-model="form.label" placeholder="Contoh: Nomor WhatsApp Orang Tua" class="mt-1.5" />
                <template x-if="errors.label">
                    <p class="mt-1.5 text-sm text-error-600" x-text="errors.label[0]"></p>
                </template>
            </div>
            <div>
                <x-input-label value="Tipe" />
                <select x-model="form.field_type" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="text">Teks</option>
                    <option value="textarea">Teks Panjang</option>
                    <option value="number">Angka</option>
                    <option value="date">Tanggal</option>
                    <option value="select">Pilihan</option>
                    <option value="file">Berkas</option>
                </select>
            </div>
            <label class="flex items-center gap-2 pb-2.5 text-sm text-gray-700">
                <input type="checkbox" x-model="form.is_required" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                Wajib
            </label>
        </div>
        <div>
            <x-input-label value="Opsi (khusus tipe Pilihan, satu per baris)" />
            <textarea x-model="form.options" rows="2" placeholder="Opsi 1&#10;Opsi 2" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
            <template x-if="errors.options">
                <p class="mt-1.5 text-sm text-error-600" x-text="errors.options[0]"></p>
            </template>
        </div>
        <x-secondary-button type="submit" x-bind:disabled="submitting">Tambah Field</x-secondary-button>
    </form>
</div>
```

- [ ] **Step 4: Build frontend assets**

Run: `npm run build`
Expected: clean build, no errors.

- [ ] **Step 5: Verify Blade compiles**

Run: `php artisan view:cache && php artisan view:clear`
Expected: no syntax errors.

- [ ] **Step 6: Run the regression tests**

Run: `php artisan test --filter=FormulirFieldTest`
Expected: `13 passed`.

- [ ] **Step 7: Commit**

```bash
git add resources/js/formulir-field-list.js resources/js/app.js resources/views/admin/jalur-ppdb/partials/formulir-field.blade.php
git commit -m "feat: convert Formulir Field CRUD to Alpine, no page reload on add/delete"
```

---

## Task 7: Frontend — Seleksi & Tes (Alpine, no reload)

**Files:**
- Create: `resources/js/seleksi-list.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/admin/jalur-ppdb/partials/seleksi.blade.php`

**Interfaces:**
- Consumes: `admin.seleksi.store`/`admin.seleksi.destroy` JSON contract from Task 4, including the `gelombang_ppdb`/`jenis_tes_master` nested objects on newly-created items.

- [ ] **Step 1: Create the Alpine component**

```js
export function seleksiList(config) {
    return {
        items: config.initialItems,
        jalurId: config.jalurId,
        storeUrl: config.storeUrl,
        deleteUrlTemplate: config.deleteUrlTemplate,
        form: {
            gelombang_ppdb_id: config.defaultGelombangId ?? '',
            jenis_tes_master_id: config.defaultJenisTesId ?? '',
            jadwal: '',
            bobot: '',
            kriteria_kelulusan: '',
        },
        errors: {},
        submitting: false,

        formatJadwal(iso) {
            const bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            const tanggalObj = new Date(iso);
            const tanggal = String(tanggalObj.getDate()).padStart(2, '0');
            const jam = String(tanggalObj.getHours()).padStart(2, '0');
            const menit = String(tanggalObj.getMinutes()).padStart(2, '0');
            return `${tanggal} ${bulan[tanggalObj.getMonth()]} ${tanggalObj.getFullYear()} ${jam}:${menit}`;
        },

        async addItem() {
            this.submitting = true;
            this.errors = {};

            try {
                const response = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ jalur_ppdb_id: this.jalurId, ...this.form }),
                });

                const json = await response.json();

                if (response.status === 422) {
                    this.errors = json.errors ?? {};
                    Alpine.store('toast').push('error', json.message ?? 'Periksa kembali form.');
                    return;
                }

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menambah jadwal seleksi.');
                    return;
                }

                this.items.push(json.data);
                this.form = {
                    gelombang_ppdb_id: config.defaultGelombangId ?? '',
                    jenis_tes_master_id: config.defaultJenisTesId ?? '',
                    jadwal: '',
                    bobot: '',
                    kriteria_kelulusan: '',
                };
                Alpine.store('toast').push('success', 'Jadwal seleksi berhasil ditambahkan.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menambah jadwal seleksi.');
            } finally {
                this.submitting = false;
            }
        },

        async deleteItem(item) {
            if (!confirm('Hapus jadwal seleksi ini?')) {
                return;
            }

            try {
                const response = await fetch(this.deleteUrlTemplate.replace('__ID__', item.id), {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menghapus jadwal seleksi.');
                    return;
                }

                this.items = this.items.filter((existing) => existing.id !== item.id);
                Alpine.store('toast').push('success', json.message ?? 'Jadwal seleksi berhasil dihapus.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menghapus jadwal seleksi.');
            }
        },
    };
}
```

Save this to `resources/js/seleksi-list.js`.

- [ ] **Step 2: Register the component in `app.js`**

In `resources/js/app.js`, add the import alongside the existing ones:

```js
import { seleksiList } from './seleksi-list';
```

and the registration alongside the existing `Alpine.data(...)` calls:

```js
Alpine.data('seleksiList', seleksiList);
```

- [ ] **Step 3: Replace the Blade partial**

Replace the full content of `resources/views/admin/jalur-ppdb/partials/seleksi.blade.php`:

```blade
<div
    class="rounded-2xl border border-gray-200 bg-white shadow-card"
    x-data="seleksiList({
        initialItems: @js($jalur->seleksi),
        jalurId: {{ $jalur->id }},
        storeUrl: @js(route('admin.seleksi.store')),
        deleteUrlTemplate: @js(route('admin.seleksi.destroy', ['seleksi' => '__ID__'])),
        defaultGelombangId: {{ $gelombangList->first()?->id ?? 'null' }},
        defaultJenisTesId: {{ $jenisTesList->first()?->id ?? 'null' }},
    })"
>
    <div class="border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Seleksi &amp; Tes</p>
        <p class="mt-0.5 text-sm text-gray-500">Jadwal tes untuk jalur ini, per gelombang. Boleh dikosongkan jika jalur tidak memakai tes.</p>
    </div>

    <ul class="divide-y divide-gray-100 px-5">
        <template x-if="items.length === 0">
            <li class="py-6 text-center text-sm text-gray-500">Belum ada jadwal seleksi.</li>
        </template>
        <template x-for="item in items" :key="item.id">
            <li class="flex items-center justify-between py-3">
                <div>
                    <span class="text-sm font-semibold text-gray-900" x-text="item.jenis_tes_master.nama"></span>
                    <span class="ml-2 text-xs text-gray-500" x-text="item.gelombang_ppdb.nama + ' · ' + formatJadwal(item.jadwal)"></span>
                    <template x-if="item.kriteria_kelulusan">
                        <p class="mt-0.5 text-xs text-gray-500" x-text="item.kriteria_kelulusan"></p>
                    </template>
                </div>
                <button type="button" @click="deleteItem(item)" class="text-sm font-semibold text-error-600 hover:text-error-700">Hapus</button>
            </li>
        </template>
    </ul>

    <form @submit.prevent="addItem()" class="space-y-3 border-t border-gray-200 bg-gray-50 px-5 py-4">
        <div class="flex flex-wrap items-end gap-2">
            <div>
                <x-input-label value="Gelombang" />
                <select x-model="form.gelombang_ppdb_id" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($gelombangList as $gelombang)
                        <option value="{{ $gelombang->id }}">{{ $gelombang->nama }}</option>
                    @endforeach
                </select>
                <template x-if="errors.gelombang_ppdb_id">
                    <p class="mt-1.5 text-sm text-error-600" x-text="errors.gelombang_ppdb_id[0]"></p>
                </template>
            </div>
            <div>
                <x-input-label value="Jenis Tes" />
                <select x-model="form.jenis_tes_master_id" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($jenisTesList as $jenisTes)
                        <option value="{{ $jenisTes->id }}">{{ $jenisTes->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label value="Jadwal" />
                <x-text-input type="datetime-local" x-model="form.jadwal" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Bobot (%)" />
                <x-text-input type="number" x-model="form.bobot" class="mt-1.5 w-24" />
            </div>
        </div>
        <div>
            <x-input-label value="Kriteria Kelulusan (opsional)" />
            <x-text-input type="text" x-model="form.kriteria_kelulusan" class="mt-1.5" />
        </div>
        <x-secondary-button type="submit" x-bind:disabled="submitting">Tambah Jadwal Seleksi</x-secondary-button>
    </form>
</div>
```

- [ ] **Step 4: Build frontend assets**

Run: `npm run build`
Expected: clean build, no errors.

- [ ] **Step 5: Verify Blade compiles**

Run: `php artisan view:cache && php artisan view:clear`
Expected: no syntax errors.

- [ ] **Step 6: Run the regression tests**

Run: `php artisan test --filter=SeleksiTest`
Expected: `14 passed`.

- [ ] **Step 7: Commit**

```bash
git add resources/js/seleksi-list.js resources/js/app.js resources/views/admin/jalur-ppdb/partials/seleksi.blade.php
git commit -m "feat: convert Seleksi & Tes CRUD to Alpine, no page reload on add/delete"
```

---

## Task 8: Final verification

**Files:** (no new files — pure verification)

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: every test passes, 0 failures — including `FormulirFieldTest` (13), `DokumenSyaratTest` (12), `SeleksiTest` (14), `JalurPpdbTest`, `GelombangPpdbTest`, `GelombangJalurRestrictionTest`, alongside the full pre-existing suite.

- [ ] **Step 2: Rebuild frontend assets**

Run: `npm run build`
Expected: clean build.

- [ ] **Step 3: Manual verification of the full flow**

With `composer dev` running: log in as `superadmin@sistem.test` / `password`, switch to a lembaga, open a jalur's edit page. For each of the three sections (Formulir Field, Dokumen Syarat, Seleksi & Tes):
- Add an item using the inline form — confirm it appears in the list immediately with **no page navigation** (URL bar doesn't change, scroll position stays put, the other two sections' content doesn't flicker), and a success toast appears top-right.
- Delete an item that has no registrant data attached — confirm a browser `confirm()` prompt appears, then the item disappears from the list immediately with no reload, and a success toast appears.
- Reproduce the originally-reported bug: manually create a `dokumen_pendaftaran` row referencing an existing `dokumen_syarat_ppdb` id (via `php artisan tinker`), then try deleting that dokumen syarat from the UI — confirm it's rejected with an error toast naming the count, the item stays in the list, and there's still no page reload. Repeat the same check for a Formulir Field with a `jawaban_formulir_pendaftaran` row and a Seleksi row with a `hasil_seleksi` row.
- Confirm the main "Detail Jalur" card at the top (nama/deskripsi/status_aktif) still works exactly as before (full page submit, unaffected by this plan).

- [ ] **Step 4: Commit any final cleanup**

If Step 3 surfaces no issues, there's nothing to commit here — this task is verification-only. If it does surface an issue, fix it, re-run Steps 1-2, and commit the fix with a message describing what was wrong.
