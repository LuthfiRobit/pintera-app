# Task 5 Report: Admin Pengaturan Yayasan page

## Summary

Implemented the admin-facing "Pengaturan Yayasan" settings page: a single view/edit-toggle page (no tabs) for managing the one `Yayasan` record's profile fields and logo, gated by the `yayasan.kelola` permission.

## Files changed

- **Created** `app/Http/Controllers/Admin/YayasanSettingController.php` — `edit()` and `update()` actions, each starting with `$this->authorize('yayasan.kelola')`.
- **Created** `resources/views/admin/yayasan/edit.blade.php` — hero card + Alpine `mode: 'view'/'edit'` toggle, mirroring `admin/lembaga/edit.blade.php`'s pattern but without the tab structure (Yayasan has no sub-collections).
- **Modified** `routes/admin.php` — added `GET /admin/pengaturan-yayasan` (`admin.yayasan.edit`) and `PUT /admin/pengaturan-yayasan` (`admin.yayasan.update`), inside the existing `admin`-prefixed/named group, right after the `lembaga` resource route.
- **Modified** `resources/views/layouts/sidebar.blade.php` — added a `Pengaturan Yayasan` nav item to the "Data Induk" group, gated by `Auth::user()->can('yayasan.kelola')`, using the `landmark` Lucide icon (this sidebar's icon set is `lucide-*` dynamic components, distinct from the app's custom `<x-icon>` component).
- **Created** `tests/Feature/Admin/YayasanSettingControllerTest.php` — the 5 tests from the brief, used verbatim.

## Deviations from the brief's literal code (with reasoning)

1. **Added `use AuthorizesRequests;` trait to the controller.** The brief's controller code sample extends `Illuminate\Routing\Controller as BaseController` but omits the trait. The task's own context note says to mirror `LembagaController`'s exact pattern — and `LembagaController` explicitly does `use AuthorizesRequests;` alongside the same `extends BaseController`. The app's own base `App\Http\Controllers\Controller` is an empty abstract class with no traits, so `$this->authorize()` would not resolve without adding the trait. Confirmed by running the tests: this was necessary for the tests to pass at all (authorization checks are exercised by the "denies access" test).
2. **Used the `apartment` icon instead of `account_balance`** for the hero-card fallback icon in `admin/yayasan/edit.blade.php`. `account_balance` is not a registered case in the app's custom `resources/views/components/icon.blade.php` component (that component has a fixed, hand-maintained `@switch` of SVGs, unlike the sidebar's Lucide-backed dynamic icons) — using an unknown name would silently fall through to the component's "unknown icon" placeholder glyph. `apartment` is an existing case in that component and is semantically close (building icon), consistent with how `admin/lembaga/edit.blade.php` uses the same icon for a similar institutional hero card.
3. **Added `@php use Illuminate\Support\Facades\Storage; @endphp` at the top of the view.** The brief's view snippet references bare `Storage::disk(...)` but the codebase has no global facade aliasing configured (`config/app.php` has no `aliases` array in this Laravel 12 skeleton), and no other Blade view in the codebase references `Storage::` unqualified. Without the import, the bare class name would not resolve inside the view's compiled (unnamespaced) PHP. Verified the sidebar's `landmark` Lucide icon name is valid and unrelated to this custom-component icon set, so no similar fix was needed there.

No other deviations — routes, controller validation rules/logic, and view structure/fields all follow the brief verbatim.

## Test commands run and results

```
php artisan test tests/Feature/Admin/YayasanSettingControllerTest.php
```

- First run (before implementation): 5 failed — `Route [admin.yayasan.edit]` / `[admin.yayasan.update]` not defined. Confirmed the tests fail for the expected reason.
- Second run (after implementation): **5 passed (13 assertions)**, 21.67s.

Only this scoped test file was run, per instructions — the full suite was not run, and no test command was backgrounded.

## Self-review notes

- Confirmed `yayasan.kelola` permission already exists in `database/seeders/PermissionSeeder.php` (seeded by Task 1), so no seeder changes were needed here.
- Confirmed no new migration was needed — all fields referenced (`nama`, `npwp_yayasan`, `akta_pendirian_nomor`, `akta_pendirian_tanggal`, `sk_kemenkumham_nomor`, `alamat`, `telepon`, `email`, `website`, `logo`, `nama_ketua_pembina`, `nama_ketua_pengurus`) are already in `Yayasan::$fillable` and the `yayasan` migration.
- Verified the old-logo-deletion logic only fires when a new file is actually uploaded (`$request->hasFile('logo')`), and only deletes if an old path existed, avoiding a `Storage::delete(null)` no-op-but-wasteful call.
- Verified `logo` validation (`mimes:jpg,jpeg,png,svg|max:1024`) matches the global constraint exactly.
- Verified `$this->authorize('yayasan.kelola')` is the literal first line of both `edit()` and `update()`, matching the established controller convention (not route middleware).
- Verified the sidebar entry pattern (`can('yayasan.kelola') ? [...] : null` inside `array_filter([...])`) matches the surrounding array entries exactly.
- No other test suites or files were touched.

## Commit

```
feat(admin): add pengaturan yayasan page for full profile + logo management
```

Files staged: `app/Http/Controllers/Admin/YayasanSettingController.php`, `resources/views/admin/yayasan/edit.blade.php`, `routes/admin.php`, `resources/views/layouts/sidebar.blade.php`, `tests/Feature/Admin/YayasanSettingControllerTest.php`.

(Pre-existing unrelated modifications to `.superpowers/sdd/final-review-fix-report.md` and `.superpowers/sdd/task-2-report.md`, present in the working tree before this task started, were left untouched and not included in this commit.)

Commit hash: see final report reply.
