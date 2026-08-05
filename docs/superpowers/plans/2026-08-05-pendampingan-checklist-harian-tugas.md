# Checklist Per-Hari untuk Tugas Frekuensi "Harian" Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** For a `KasusTugas` with `frekuensi = 'harian'`, siswa/orang tua submit bukti pengerjaan
per tanggal (bukan satu bukti gabungan untuk seluruh rentang), and konselor reviews/requests
revision per tanggal without affecting the whole task's status.

**Architecture:** Add one nullable `tanggal` column to `kasus_tugas_submission` (only populated
for `harian` tasks). `KasusTugasSubmissionController::store()` validates and locks per date for
`harian` tasks; `::review()` stops flipping the parent `KasusTugas.status` to `Revisi` when the
task is `harian`. The tab-tugas Blade partial branches on `frekuensi === 'harian'` to render a
per-date list instead of the existing single generic submission zone — everything for
`sekali`/`mingguan`/`bulanan` stays byte-for-byte as it is today.

**Tech Stack:** Laravel 12, Pest 4, MySQL, Blade (server-rendered, no new Alpine state needed).

## Global Constraints

- Only `frekuensi = 'harian'` tasks are affected. `sekali`/`mingguan`/`bulanan` — form,
  controller validation, and status transitions — are not touched by any task in this plan.
- All dates in `mulai_pada`–`batas_selesai_pada` (inclusive) are always enterable — no
  "today-only" lock, no rejecting past or future dates.
- One active submission per date: once a date has a submission with `status_review` of
  `menunggu_review` or `diterima`, that date is locked (no new submission accepted for it)
  until a konselor sets that specific submission to `revisi_diminta`, which reopens only that
  date.
- For `harian` tasks, a konselor marking a submission `revisi_diminta` must NOT change the
  parent `KasusTugas.status` — it stays whatever it already was. For every other `frekuensi`,
  the existing behavior (flip to `Revisi`) is unchanged.
- `KasusTugas.status` auto-transition `Ditugaskan → Dikerjakan` on the first submission (any
  date) stays exactly as it is today for all frequencies.
- `Selesai`/`Terlewat` remain fully manual konselor decisions (existing "Tandai Selesai"
  button) — never computed from submission completeness, for any frequency.
- UI must match the existing tab+partials detail-page pattern already used throughout
  `resources/views/kasus/show.blade.php` and its partials (plain server-rendered Blade inside
  the tab, `rounded-xl border border-gray-200 bg-white` cards, `<x-badge>` for status pills,
  `<x-icon>` only with names already confirmed present as an `@case` in
  `resources/views/components/icon.blade.php` — do not introduce a new unregistered icon name).

---

### Task 1: Add `tanggal` column to `kasus_tugas_submission` + model support

**Files:**
- Create: `database/migrations/2026_08_06_100000_add_tanggal_to_kasus_tugas_submission.php`
- Modify: `app/Models/KasusTugasSubmission.php`
- Test: `tests/Feature/KasusTugasSubmissionTanggalSchemaTest.php`

**Interfaces:**
- Produces: `KasusTugasSubmission::$tanggal` — a nullable `Carbon`-cast `date` attribute, settable
  via mass-assignment (`fillable`). `null` for every submission belonging to a `sekali`/
  `mingguan`/`bulanan` task; a real date for every submission belonging to a `harian` task.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/KasusTugasSubmissionTanggalSchemaTest.php

use App\Models\Kasus;
use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;

it('stores and casts a nullable tanggal on kasus_tugas_submission', function () {
    $kasus = Kasus::factory()->create();
    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id, 'frekuensi' => 'harian']);

    $submission = KasusTugasSubmission::create([
        'tugas_id' => $tugas->id,
        'teks' => 'Bukti hari ini.',
        'tanggal' => '2026-08-10',
    ]);

    expect($submission->fresh()->tanggal)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($submission->fresh()->tanggal->toDateString())->toBe('2026-08-10');

    $tanpaTanggal = KasusTugasSubmission::create([
        'tugas_id' => $tugas->id,
        'teks' => 'Bukti tanpa tanggal (frekuensi lain).',
    ]);

    expect($tanpaTanggal->fresh()->tanggal)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=KasusTugasSubmissionTanggalSchemaTest`
Expected: FAIL — `Illuminate\Database\QueryException` (unknown column `tanggal`), since the
column doesn't exist yet and `tanggal` isn't in `$fillable`.

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_08_06_100000_add_tanggal_to_kasus_tugas_submission.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kasus_tugas_submission', function (Blueprint $table) {
            $table->date('tanggal')->nullable()->after('orang_tua_id');
        });
    }

    public function down(): void
    {
        Schema::table('kasus_tugas_submission', function (Blueprint $table) {
            $table->dropColumn('tanggal');
        });
    }
};
```

Run: `php artisan migrate`

- [ ] **Step 4: Add `tanggal` to the model's `$fillable` and casts**

In `app/Models/KasusTugasSubmission.php`, change:
```php
    protected $fillable = [
        'tugas_id', 'siswa_id', 'orang_tua_id', 'teks', 'lampiran',
        'status_review', 'catatan_revisi',
    ];
```
to:
```php
    protected $fillable = [
        'tugas_id', 'siswa_id', 'orang_tua_id', 'teks', 'lampiran',
        'status_review', 'catatan_revisi', 'tanggal',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=KasusTugasSubmissionTanggalSchemaTest`
Expected: PASS (1 test)

- [ ] **Step 6: Run the broader Kasus regression suite**

Run: `php artisan test --filter=Kasus`
Expected: all pass — a new nullable column with no default-value change can't affect any
existing query.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_06_100000_add_tanggal_to_kasus_tugas_submission.php app/Models/KasusTugasSubmission.php tests/Feature/KasusTugasSubmissionTanggalSchemaTest.php
git commit -m "feat(pendampingan): add tanggal column to kasus_tugas_submission"
```

---

### Task 2: Per-date lock in `store()` + skip whole-task revisi flip in `review()` for harian tasks

**Files:**
- Modify: `app/Http/Controllers/KasusTugasSubmissionController.php`
- Test: `tests/Feature/KasusTugasSubmissionHarianTest.php`

**Interfaces:**
- Consumes: `KasusTugasSubmission::$tanggal` (Task 1); `KasusTugas::$frekuensi`,
  `$mulai_pada`, `$batas_selesai_pada` (already exist, unchanged); the existing
  `assertKonselorPemegangKasus()` private method already in this controller — reuse verbatim,
  do not duplicate its logic.
- Produces: no new public method signatures — `store()` and `review()` keep their exact current
  signatures (`store(Request $request, Kasus $kasus, KasusTugas $kasusTugas): RedirectResponse`,
  `review(Request $request, Kasus $kasus, KasusTugas $kasusTugas, KasusTugasSubmission
  $kasusTugasSubmission): RedirectResponse`) — Task 3's view only ever posts to the existing
  `kasus.tugas.submission.store`/`kasus.tugas.submission.review` routes, no route changes.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/KasusTugasSubmissionHarianTest.php

use App\Models\Kasus;
use App\Models\KasusConsent;
use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

function buatKasusDenganTugasHarianDanKontakUtama(Lembaga $lembaga): array
{
    [$kasus, $konselorUser, $siswa] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);

    $siswaUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaRole = Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $siswaRole->givePermissionTo(['kasus.view']);
    $siswaUser->assignRole('siswa');
    $siswa->update(['user_id' => $siswaUser->id]);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    $orangTuaRole = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaRole->givePermissionTo(['kasus.view']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Harian',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200007777',
        'email' => 'ortu.harian@example.test',
    ]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $tugas = KasusTugas::factory()->create([
        'kasus_id' => $kasus->id,
        'frekuensi' => 'harian',
        'mulai_pada' => '2026-08-10',
        'batas_selesai_pada' => '2026-08-12',
    ]);

    KasusConsent::create(['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan', 'status' => 'disetujui', 'disetujui_at' => now()]);
    KasusConsent::create(['kasus_id' => $kasus->id, 'jenis' => 'pengumpulan_media']);

    return [$kasus, $tugas, $siswaUser, $orangTuaUser];
}

it('stores the submitted tanggal on a harian tugas submission', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Bukti hari kedua.',
        'tanggal' => '2026-08-11',
    ])->assertRedirect(route('kasus.show', $kasus));

    $submission = KasusTugasSubmission::where('tugas_id', $tugas->id)->first();
    expect($submission->tanggal->toDateString())->toBe('2026-08-11');
});

it('rejects a tanggal outside the tugas mulai_pada-batas_selesai_pada range', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Bukti di luar rentang.',
        'tanggal' => '2026-08-20',
    ])->assertSessionHasErrors('tanggal');

    expect(KasusTugasSubmission::where('tugas_id', $tugas->id)->exists())->toBeFalse();
});

it('requires tanggal for a harian tugas submission', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Tanpa tanggal.',
    ])->assertSessionHasErrors('tanggal');
});

it('locks a date once its submission is menunggu_review, rejecting a second submission for the same date', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);
    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Submission pertama.', 'tanggal' => '2026-08-10',
    ])->assertRedirect();

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Percobaan kedua di tanggal sama.', 'tanggal' => '2026-08-10',
    ])->assertStatus(422);

    expect(KasusTugasSubmission::where('tugas_id', $tugas->id)->where('tanggal', '2026-08-10')->count())->toBe(1);
});

it('lets orang tua kontak utama submit on behalf of the child for a specific date', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser, $orangTuaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);

    $this->actingAs($orangTuaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Dibantu ibu, hari pertama.', 'tanggal' => '2026-08-10',
    ])->assertRedirect(route('kasus.show', $kasus));

    $submission = KasusTugasSubmission::where('tugas_id', $tugas->id)->first();
    expect($submission->tanggal->toDateString())->toBe('2026-08-10');
    expect($submission->orang_tua_id)->not->toBeNull();
    expect($submission->siswa_id)->toBeNull();
});

it('does not lock other dates when one date is locked', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);
    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Hari pertama.', 'tanggal' => '2026-08-10',
    ])->assertRedirect();

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Hari kedua.', 'tanggal' => '2026-08-11',
    ])->assertRedirect();

    expect(KasusTugasSubmission::where('tugas_id', $tugas->id)->count())->toBe(2);
});

it('reopens only the revised date after a konselor requests revisi, and leaves tugas status alone', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);
    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Bukti hari pertama.', 'tanggal' => '2026-08-10',
    ])->assertRedirect();
    $submission = KasusTugasSubmission::where('tugas_id', $tugas->id)->where('tanggal', '2026-08-10')->firstOrFail();

    $konselorUser = User::where('id', '!=', $siswaUser->id)->whereHas('roles', fn ($q) => $q->where('name', 'guru'))->firstOrFail();
    Notification::fake();
    $this->actingAs($konselorUser)->patch(route('kasus.tugas.submission.review', [$kasus, $tugas, $submission]), [
        'status_review' => 'revisi_diminta',
        'catatan_revisi' => 'Perbaiki bukti hari pertama.',
    ])->assertRedirect(route('kasus.show', $kasus));

    expect($tugas->refresh()->status->value)->toBe('dikerjakan');

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Bukti hari pertama, revisi.', 'tanggal' => '2026-08-10',
    ])->assertRedirect(route('kasus.show', $kasus));

    expect(KasusTugasSubmission::where('tugas_id', $tugas->id)->where('tanggal', '2026-08-10')->count())->toBe(2);
});

it('still flips tugas status to revisi for a non-harian task, unchanged from before', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBk($lembaga);
    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id, 'status' => 'dikerjakan', 'frekuensi' => 'sekali']);
    $submission = KasusTugasSubmission::factory()->create(['tugas_id' => $tugas->id]);

    Notification::fake();
    $this->actingAs($konselorUser)->patch(route('kasus.tugas.submission.review', [$kasus, $tugas, $submission]), [
        'status_review' => 'revisi_diminta',
        'catatan_revisi' => 'Tolong lebih detail.',
    ])->assertRedirect(route('kasus.show', $kasus));

    expect($tugas->refresh()->status->value)->toBe('revisi');
});
```

`buatKasusDitugaskanKeGuruBkUntukTugas()` and `buatKasusDitugaskanKeGuruBk()` are existing
shared helpers already used by `tests/Feature/KasusTugasBeriTest.php` and
`tests/Feature/KasusTugasReviewTest.php` respectively — read those two files first to confirm
their exact current signatures and returned array shape before using them here; do not
redefine them in this new file (PHP fatals on duplicate function declaration across Pest test
files that are all loaded in one process).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=KasusTugasSubmissionHarianTest`
Expected: several FAIL — `tanggal` isn't validated/stored yet, no locking exists yet, and
`review()` still unconditionally flips `KasusTugas.status` to `revisi` for harian tasks too.

- [ ] **Step 3: Update `store()`'s validation and add the per-date lock**

In `app/Http/Controllers/KasusTugasSubmissionController.php`, change:
```php
        $rules = ['teks' => [$hasLampiran ? 'nullable' : 'required', 'string']];
        if ($mediaDisetujui) {
            $rules['lampiran'] = ['nullable', 'file', 'mimes:jpg,jpeg,png,mp4,mov', 'max:20480'];
        }
        $data = $request->validate($rules);
```
to:
```php
        $rules = ['teks' => [$hasLampiran ? 'nullable' : 'required', 'string']];
        if ($mediaDisetujui) {
            $rules['lampiran'] = ['nullable', 'file', 'mimes:jpg,jpeg,png,mp4,mov', 'max:20480'];
        }
        if ($kasusTugas->frekuensi === 'harian') {
            $rules['tanggal'] = [
                'required', 'date',
                'after_or_equal:'.$kasusTugas->mulai_pada->toDateString(),
                'before_or_equal:'.$kasusTugas->batas_selesai_pada->toDateString(),
            ];
        }
        $data = $request->validate($rules);

        if ($kasusTugas->frekuensi === 'harian') {
            $terkunci = KasusTugasSubmission::where('tugas_id', $kasusTugas->id)
                ->whereDate('tanggal', $data['tanggal'])
                ->whereIn('status_review', ['menunggu_review', 'diterima'])
                ->exists();
            abort_if($terkunci, 422, 'Tanggal ini sudah memiliki bukti pengerjaan yang menunggu atau sudah diterima.');
        }
```

Then change the `KasusTugasSubmission::create([...])` call from:
```php
        KasusTugasSubmission::create([
            'tugas_id' => $kasusTugas->id,
            'siswa_id' => $isSiswaTerkait ? $siswa->id : null,
            'orang_tua_id' => $isSiswaTerkait ? null : $user->orangTua->id,
            'teks' => $data['teks'] ?? null,
            'lampiran' => $lampiranPath,
        ]);
```
to:
```php
        KasusTugasSubmission::create([
            'tugas_id' => $kasusTugas->id,
            'siswa_id' => $isSiswaTerkait ? $siswa->id : null,
            'orang_tua_id' => $isSiswaTerkait ? null : $user->orangTua->id,
            'teks' => $data['teks'] ?? null,
            'lampiran' => $lampiranPath,
            'tanggal' => $data['tanggal'] ?? null,
        ]);
```

- [ ] **Step 4: Skip the whole-task revisi flip for harian tasks in `review()`**

Change:
```php
        if ($data['status_review'] === 'revisi_diminta') {
            $kasusTugas->update(['status' => 'revisi']);

            $notifiable = ...
```
to:
```php
        if ($data['status_review'] === 'revisi_diminta') {
            if ($kasusTugas->frekuensi !== 'harian') {
                $kasusTugas->update(['status' => 'revisi']);
            }

            $notifiable = ...
```
(leave everything else in that block — the notification dispatch — exactly as it is; only the
`$kasusTugas->update(...)` call gets the new condition around it).

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=KasusTugasSubmissionHarianTest`
Expected: PASS (8 tests)

- [ ] **Step 6: Run the broader Kasus regression suite**

Run: `php artisan test --filter=Kasus`
Expected: all pass, including the pre-existing `tests/Feature/KasusTugasReviewTest.php`'s
`'marks a submission revisi_diminta with a catatan and moves tugas status to revisi'` test
(that test's `KasusTugas::factory()->create(...)` uses the factory default `frekuensi =>
'sekali'`, so it must be completely unaffected by the new `!== 'harian'` guard).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/KasusTugasSubmissionController.php tests/Feature/KasusTugasSubmissionHarianTest.php
git commit -m "feat(pendampingan): lock harian tugas submissions per date, skip whole-task revisi flip"
```

---

### Task 3: Per-date checklist view for harian tasks

**Files:**
- Modify: `resources/views/kasus/partials/_tab-tugas.blade.php`
- Test: `tests/Feature/KasusTugasHarianViewTest.php`

**Interfaces:**
- Consumes: `$kasus`, `$isKonselor`, `$isSiswaTerkait`, `$isKontakUtama` — all already passed
  into this partial by `resources/views/kasus/show.blade.php` (unchanged, do not add new
  variables to that include call); `KasusTugasSubmission::$tanggal` (Task 1);
  `route('kasus.tugas.submission.store', [$kasus, $tugas])` and
  `route('kasus.tugas.submission.review', [$kasus, $tugas, $submission])` (both pre-existing
  route names, unchanged signatures per Task 2).

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/KasusTugasHarianViewTest.php

use App\Models\Kasus;
use App\Models\KasusConsent;
use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('shows one row per date in the harian range, each with its own submit form when open', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);

    $response = $this->actingAs($siswaUser)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertSeeInOrder(['10 Aug 2026', '11 Aug 2026', '12 Aug 2026']);
});

it('locks the submitted date row and shows its history, while other date rows stay open', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);
    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Bukti hari pertama yang unik.', 'tanggal' => '2026-08-10',
    ])->assertRedirect();

    $response = $this->actingAs($siswaUser)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertSee('Bukti hari pertama yang unik.');
    // The locked date's own row must not render a submit form again; count the remaining
    // open dates' submit-button label occurrences (2 of the 3 dates: 11 and 12 Aug).
    $response->assertSeeInOrder(['10 Aug 2026', 'Bukti hari pertama yang unik.', '11 Aug 2026', '12 Aug 2026']);
});

it('does not render the per-date checklist for a non-harian tugas', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasDanKontakUtama($lembaga);

    $response = $this->actingAs($siswaUser)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertDontSee('Aug 2026');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=KasusTugasHarianViewTest`
Expected: FAIL — no per-date rows exist yet; the current generic form renders the same way
regardless of `frekuensi`.

- [ ] **Step 3: Replace the submissions-and-submit-form section of the partial**

In `resources/views/kasus/partials/_tab-tugas.blade.php`, the section to replace spans from the
`{{-- Daftar Submisi / Bukti Pengerjaan --}}` comment through the end of the
`{{-- Form Submisi (Siswa / Orang Tua) --}}` block (i.e. everything between the closing
`@endif` of the "Instruksi Tugas" block and the `</div>` that closes one `$tugas` card, right
before the `@endforeach`). Read the current file first to find these exact boundaries — the
plan's earlier research read this file in full; the two blocks to replace are the ones shown
under "Daftar Submisi / Bukti Pengerjaan" and "Form Submisi (Siswa / Orang Tua)" in that
reading.

Replace both of those blocks with:

```blade
                    {{-- Submisi & Formulir: per-tanggal untuk harian, satu zona untuk lainnya --}}
                    @if ($tugas->frekuensi === 'harian')
                        @php
                            $tanggalList = collect();
                            $kursor = $tugas->mulai_pada->copy();
                            while ($kursor->lte($tugas->batas_selesai_pada)) {
                                $tanggalList->push($kursor->copy());
                                $kursor->addDay();
                            }
                            $submisiPerTanggal = $tugas->submissions->groupBy(fn ($s) => $s->tanggal?->toDateString());
                        @endphp
                        <div class="space-y-2.5 pt-1">
                            <p class="text-[11px] font-extrabold text-gray-400 uppercase tracking-wider">Checklist Harian:</p>
                            @foreach ($tanggalList as $tanggal)
                                @php
                                    $submisiHariIni = $submisiPerTanggal->get($tanggal->toDateString(), collect())->sortByDesc('created_at')->first();
                                    $terkunci = $submisiHariIni && in_array($submisiHariIni->status_review, ['menunggu_review', 'diterima'], true);
                                @endphp
                                <div class="rounded-lg border border-gray-200 bg-white p-3.5">
                                    <p class="text-xs font-bold text-gray-700 flex items-center gap-1.5">
                                        <x-icon name="calendar_month" class="h-3.5 w-3.5 text-gray-400" />
                                        {{ $tanggal->translatedFormat('d M Y') }}
                                        @if ($terkunci)
                                            <x-icon name="lock" class="h-3 w-3 text-gray-300" />
                                        @endif
                                    </p>

                                    @if ($submisiHariIni)
                                        <div class="mt-2 space-y-2 text-xs">
                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                <div>
                                                    <span class="font-bold text-gray-800">{{ $submisiHariIni->created_at->format('d M Y H:i') }}:</span>
                                                    <span class="text-gray-700 ml-1 font-medium">{{ $submisiHariIni->teks ?? '(Lampiran saja)' }}</span>
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    @if ($submisiHariIni->lampiran)
                                                        <a href="{{ route('kasus.tugas.submission.lampiran', [$kasus, $tugas, $submisiHariIni]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-bold text-brand-600 hover:text-brand-700 hover:underline bg-white px-2 py-1 rounded border border-brand-200 shadow-2xs text-[11px]">
                                                            Lihat Lampiran
                                                        </a>
                                                    @endif
                                                    <x-badge :tone="$submisiHariIni->status_review === 'diterima' ? 'green' : ($submisiHariIni->status_review === 'revisi_diminta' ? 'amber' : 'slate')" class="text-[10px] font-extrabold">
                                                        {{ str_replace('_', ' ', ucfirst($submisiHariIni->status_review)) }}
                                                    </x-badge>
                                                </div>
                                            </div>

                                            @if ($isKonselor && $submisiHariIni->status_review === 'menunggu_review')
                                                <div x-data="{ revisi: false }" class="rounded-lg bg-gray-50 p-3 border border-gray-200 shadow-2xs mt-2">
                                                    <div class="flex items-center justify-between gap-3 text-xs">
                                                        <span class="font-bold text-gray-700">Tindakan Review:</span>
                                                        <div class="flex items-center gap-2">
                                                            <form method="POST" action="{{ route('kasus.tugas.submission.review', [$kasus, $tugas, $submisiHariIni]) }}">
                                                                @csrf @method('PATCH')
                                                                <input type="hidden" name="status_review" value="diterima">
                                                                <button type="submit" class="inline-flex items-center gap-1 font-bold text-success-700 hover:bg-success-100 bg-success-50 px-3 py-1.5 rounded-lg transition border border-success-200">
                                                                    <x-icon name="check_circle" class="h-3.5 w-3.5" />
                                                                    Terima
                                                                </button>
                                                            </form>
                                                            <button type="button" @click="revisi = !revisi" class="inline-flex items-center gap-1 font-bold text-amber-700 hover:bg-amber-100 bg-amber-50 px-3 py-1.5 rounded-lg transition border border-amber-200">
                                                                Minta Revisi
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <form x-show="revisi" style="display: none;" method="POST" action="{{ route('kasus.tugas.submission.review', [$kasus, $tugas, $submisiHariIni]) }}" class="mt-3 pt-3 border-t border-gray-100 space-y-2">
                                                        @csrf @method('PATCH')
                                                        <input type="hidden" name="status_review" value="revisi_diminta">
                                                        <input type="text" name="catatan_revisi" required placeholder="Catatan perbaikan untuk hari ini..." class="block w-full rounded-lg border-gray-200 text-xs font-medium text-gray-900 shadow-2xs focus:border-amber-500 focus:ring-amber-500">
                                                        <button type="submit" class="font-bold text-white bg-amber-600 hover:bg-amber-700 px-4 py-2 rounded-lg text-xs transition shadow-sm">Kirim Catatan</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    @if (! $terkunci && ($isSiswaTerkait || $isKontakUtama) && in_array($tugas->status->value, ['ditugaskan', 'dikerjakan', 'revisi'], true))
                                        <form method="POST" action="{{ route('kasus.tugas.submission.store', [$kasus, $tugas]) }}" enctype="multipart/form-data" class="mt-2.5 space-y-2 border-t border-gray-100 pt-2.5">
                                            @csrf
                                            <input type="hidden" name="tanggal" value="{{ $tanggal->toDateString() }}">
                                            <textarea name="teks" rows="2" placeholder="Bukti/refleksi untuk tanggal ini..." class="block w-full rounded-lg border-gray-200 text-xs font-medium text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"></textarea>
                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                @if ($kasus->consents->firstWhere('jenis', 'pengumpulan_media')?->status === 'disetujui')
                                                    <input type="file" name="lampiran" class="block w-full text-xs text-gray-700 file:mr-3 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 transition">
                                                @else
                                                    <p class="text-[11px] font-medium text-gray-400 italic">Unggah media dinonaktifkan hingga informed consent media disetujui.</p>
                                                @endif
                                                <x-primary-button type="submit" class="px-4 py-1.5 rounded-lg text-xs font-bold shrink-0">Kirim</x-primary-button>
                                            </div>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        {{-- Daftar Submisi / Bukti Pengerjaan --}}
                        @if ($tugas->submissions->isNotEmpty())
                            <div class="space-y-2 pt-1">
                                <p class="text-[11px] font-extrabold text-gray-400 uppercase tracking-wider">Riwayat Bukti & Hasil Pengerjaan:</p>
                                <div class="space-y-2.5 divide-y divide-gray-100 rounded-xl border border-gray-100 bg-gray-50/50 p-3.5">
                                    @foreach ($tugas->submissions as $submission)
                                        <div class="pt-2.5 first:pt-0 space-y-2 text-xs">
                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                <div>
                                                    <span class="font-bold text-gray-800">{{ $submission->created_at->format('d M Y H:i') }}:</span>
                                                    <span class="text-gray-700 ml-1 font-medium">{{ $submission->teks ?? '(Lampiran saja)' }}</span>
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    @if ($submission->lampiran)
                                                        <a href="{{ route('kasus.tugas.submission.lampiran', [$kasus, $tugas, $submission]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-bold text-brand-600 hover:text-brand-700 hover:underline bg-white px-2 py-1 rounded border border-brand-200 shadow-2xs text-[11px]">
                                                            <x-icon name="attach_file" class="h-3 w-3" />
                                                            Lihat Lampiran
                                                        </a>
                                                    @endif
                                                    <x-badge :tone="$submission->status_review === 'diterima' ? 'green' : ($submission->status_review === 'revisi_diminta' ? 'amber' : 'slate')" class="text-[10px] font-extrabold">
                                                        {{ str_replace('_', ' ', ucfirst($submission->status_review)) }}
                                                    </x-badge>
                                                </div>
                                            </div>

                                            @if ($isKonselor && $submission->status_review === 'menunggu_review')
                                                <div x-data="{ revisi: false }" class="rounded-lg bg-white p-3 border border-gray-200 shadow-2xs mt-2">
                                                    <div class="flex items-center justify-between gap-3 text-xs">
                                                        <span class="font-bold text-gray-700">Tindakan Review:</span>
                                                        <div class="flex items-center gap-2">
                                                            <form method="POST" action="{{ route('kasus.tugas.submission.review', [$kasus, $tugas, $submission]) }}">
                                                                @csrf @method('PATCH')
                                                                <input type="hidden" name="status_review" value="diterima">
                                                                <button type="submit" class="inline-flex items-center gap-1 font-bold text-success-700 hover:bg-success-100 bg-success-50 px-3 py-1.5 rounded-lg transition border border-success-200">
                                                                    <x-icon name="thumb_up" class="h-3.5 w-3.5" />
                                                                    Terima Hasil
                                                                </button>
                                                            </form>
                                                            <button type="button" @click="revisi = !revisi" class="inline-flex items-center gap-1 font-bold text-amber-700 hover:bg-amber-100 bg-amber-50 px-3 py-1.5 rounded-lg transition border border-amber-200">
                                                                <x-icon name="rate_review" class="h-3.5 w-3.5" />
                                                                Minta Revisi
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <form x-show="revisi" style="display: none;" method="POST" action="{{ route('kasus.tugas.submission.review', [$kasus, $tugas, $submission]) }}" class="mt-3 pt-3 border-t border-gray-100 space-y-2">
                                                        @csrf @method('PATCH')
                                                        <input type="hidden" name="status_review" value="revisi_diminta">
                                                        <label class="block text-[11px] font-bold text-amber-900">Catatan Perbaikan untuk Siswa:</label>
                                                        <div class="flex items-center gap-2">
                                                            <input type="text" name="catatan_revisi" required placeholder="Contoh: Harap lampirkan bukti foto refleksi..." class="block w-full rounded-lg border-gray-200 text-xs font-medium text-gray-900 shadow-2xs focus:border-amber-500 focus:ring-amber-500">
                                                            <button type="submit" class="whitespace-nowrap font-bold text-white bg-amber-600 hover:bg-amber-700 px-4 py-2 rounded-lg text-xs transition shadow-sm">Kirim Catatan</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Form Submisi (Siswa / Orang Tua) --}}
                        @if (($isSiswaTerkait || $isKontakUtama) && in_array($tugas->status->value, ['ditugaskan', 'dikerjakan', 'revisi'], true))
                            <form method="POST" action="{{ route('kasus.tugas.submission.store', [$kasus, $tugas]) }}" enctype="multipart/form-data" class="space-y-3 border-t border-gray-100 pt-4">
                                @csrf
                                <div class="rounded-xl border border-brand-200 bg-brand-50/10 p-4 space-y-3">
                                    <h5 class="font-display text-xs font-bold text-brand-900 flex items-center gap-1.5">
                                        <x-icon name="upload_file" class="h-4 w-4 text-brand-600" />
                                        Kirim Hasil / Bukti Pengerjaan Tugas
                                    </h5>
                                    <div>
                                        <textarea name="teks" rows="2" placeholder="Ceritakan bukti atau jelaskan hasil refleksi pengerjaan tugas Anda di sini..." class="block w-full rounded-lg border-gray-200 text-xs font-medium text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"></textarea>
                                    </div>
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                                        <div>
                                            @if ($kasus->consents->firstWhere('jenis', 'pengumpulan_media')?->status === 'disetujui')
                                                <label class="block text-[11px] font-bold text-gray-600 mb-1">Unggah File/Foto Bukti (Opsional):</label>
                                                <input type="file" name="lampiran" class="block w-full text-xs text-gray-700 file:mr-3 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 transition">
                                            @else
                                                <p class="text-[11px] font-medium text-gray-400 italic">Unggah media dinonaktifkan hingga informed consent media disetujui.</p>
                                            @endif
                                        </div>
                                        <x-primary-button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold shrink-0">
                                            <x-icon name="send" class="mr-1.5 h-3.5 w-3.5" />
                                            Kirim Bukti
                                        </x-primary-button>
                                    </div>
                                </div>
                            </form>
                        @endif
                    @endif
```

This preserves the `sekali`/`mingguan`/`bulanan` branch (the `@else`) as an EXACT copy of what
was there before — same markup, same classes, same icon names — only reachable when
`$tugas->frekuensi !== 'harian'`. The `harian` branch is new.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=KasusTugasHarianViewTest`
Expected: PASS (3 tests). If the date-format assertion (`'10 Aug 2026'`) doesn't match what
`translatedFormat('d M Y')` actually renders in this app's configured locale, adjust the test's
expected strings to match the app's real locale output — inspect the actual rendered response
content (`$response->getContent()`) to confirm the exact string before deciding this is a
locale mismatch rather than a real bug.

- [ ] **Step 5: Run the broader Kasus regression suite**

Run: `php artisan test --filter=Kasus`
Expected: all pass, including every pre-existing `KasusTugasSubmissionTest.php`/
`KasusTugasReviewTest.php` test (all of which use `frekuensi = 'sekali'` via the factory
default, so they must render through the untouched `@else` branch identically to before).

- [ ] **Step 6: Commit**

```bash
git add resources/views/kasus/partials/_tab-tugas.blade.php tests/Feature/KasusTugasHarianViewTest.php
git commit -m "feat(pendampingan): render a per-date submission checklist for harian tugas"
```

---

### Task 4: Full regression pass

**Files:** none (verification only)

- [ ] **Step 1: Run the full Kasus-prefixed regression**

Run: `php artisan test --filter=Kasus`
Expected: all pass.

- [ ] **Step 2: Run the full project suite**

Run: `php artisan test`
Expected: all pass, with no unrelated regressions anywhere else in the app.

- [ ] **Step 3: Migrate and verify the real dev DB**

Run: `php artisan migrate` (applies Task 1's new `tanggal` column to the real dev DB — the test
suite uses a separate testing DB, so this is a required manual step, per
[[project_pintera_app_dev_environment]]).

- [ ] **Step 4: Manual smoke check**

As a konselor, create a `harian` tugas with a 3-day range on a real seeded kasus in the local
dev environment; confirm the kasus detail page renders 3 date rows, each with its own submit
form; submit one date as the siswa/orang-tua account; confirm that date locks and the other two
stay open; as the konselor, request revisi on the submitted date; confirm the tugas's overall
status badge does NOT change to "Revisi" and only that one date reopens.
