---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## No repository/query-object layer
Controllers, Actions, and Services query Eloquent models directly. There is no Repository or Query-object layer — do not introduce one.

## Business logic: Actions for mutations, inline Eloquent for reads
Create/update/state-transition endpoints inject a Domain Action (method-injected, $action->execute(...)) and call it directly from the controller method. Read/listing endpoints (index()) stay fat: build the Eloquent query, paginate, and select the view directly in the controller — no Action needed there.

## Validation: FormRequest when Action/DTO exists, inline otherwise
A controller action already wired to a Domain Action + DTO validates via a dedicated FormRequest class. A simple CRUD/master-data endpoint without that pipeline validates inline with $request->validate([...]). Don't add a FormRequest to a simple endpoint just for consistency, and don't leave an Action-backed endpoint on inline validation.

## Controllers are multi-method, resource-shaped
Controllers use standard multi-method resource verbs (index/create/store/edit/update/destroy) plus extra named actions on the same controller for sub-resources. Don't reach for single-action __invoke controllers.

## Authorization: permission-string $this->authorize()
Default authorization check is $this->authorize('dot.permission.string') using Spatie permission names at the top of the controller method. Use $user->can('perm') combined with abort_unless/abort_if only when the check combines multiple permissions with OR/AND logic that authorize() can't express.

## No API Resource classes; JSON built manually
There are no app/Http/Resources classes. Any JSON response is built manually via response()->json([...]) arrays.

## Pagination: always paginate()
All paginated listings use ->paginate(). Don't reach for simplePaginate() or cursorPaginate() without a specific reason to deviate.

## URL generation: always route()
Links and redirects always use route('name'), never url('/path') or action([Controller::class, 'method']).

## List pages: AJAX-aware index() returning a _daftar partial
index() methods for list pages check $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest' and return a `..._daftar` partial view in that case, falling back to the full `...index` view otherwise. One route serves both the full page and its AJAX refresh.
