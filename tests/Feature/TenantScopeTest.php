<?php

use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class TenantScopeTestModel extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_scope_test_models';

    protected $fillable = ['lembaga_id', 'label'];
}

// Mirrors models like Karyawan/Rpp/AsetBarang that carry their own yayasan_id column for
// "pool" rows belonging directly to a yayasan (lembaga_id null), not tied to any one lembaga.
class TenantScopeTestPoolModel extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_scope_test_pool_models';

    protected $fillable = ['lembaga_id', 'yayasan_id', 'label'];
}

beforeEach(function () {
    Schema::create('tenant_scope_test_models', function ($table) {
        $table->id();
        $table->foreignId('lembaga_id')->nullable();
        $table->string('label');
        $table->timestamps();
    });
    Schema::create('tenant_scope_test_pool_models', function ($table) {
        $table->id();
        $table->foreignId('lembaga_id')->nullable();
        $table->foreignId('yayasan_id')->nullable();
        $table->string('label');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('tenant_scope_test_models');
    Schema::dropIfExists('tenant_scope_test_pool_models');
});

it('only returns rows for the lembaga-scoped user\'s own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    TenantScopeTestModel::withoutGlobalScopes()->create(['lembaga_id' => $lembagaA->id, 'label' => 'A']);
    TenantScopeTestModel::withoutGlobalScopes()->create(['lembaga_id' => $lembagaB->id, 'label' => 'B']);

    $user = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $user->assignRole('admin_administrasi');
    $this->actingAs($user);

    expect(TenantScopeTestModel::pluck('label')->all())->toBe(['A']);
});

it('returns rows for every lembaga under the user\'s own yayasan when no active_lembaga_id is set', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    TenantScopeTestModel::withoutGlobalScopes()->create(['lembaga_id' => $lembagaA->id, 'label' => 'A']);
    TenantScopeTestModel::withoutGlobalScopes()->create(['lembaga_id' => $lembagaB->id, 'label' => 'B']);

    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole('yayasan_super_admin');
    $this->actingAs($user);

    expect(TenantScopeTestModel::pluck('label')->all())->toBe(['A', 'B']);
});

it('never returns rows from another yayasan when the acting yayasan-scoped user has no active_lembaga_id set', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasanSaya->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    TenantScopeTestModel::withoutGlobalScopes()->create(['lembaga_id' => $lembagaSaya->id, 'label' => 'Milik Saya']);
    TenantScopeTestModel::withoutGlobalScopes()->create(['lembaga_id' => $lembagaLain->id, 'label' => 'Milik Yayasan Lain']);

    $user = User::factory()->create(['yayasan_id' => $yayasanSaya->id]);
    $user->assignRole('yayasan_super_admin');
    $this->actingAs($user);

    // Regression test for the cross-yayasan data leak: previously this branch applied no
    // where() clause at all when active_lembaga_id was empty (e.g. via the "Semua Lembaga"
    // switcher option), returning rows from every yayasan in the entire system.
    expect(TenantScopeTestModel::pluck('label')->all())->toBe(['Milik Saya']);
});

it('returns zero rows for a yayasan-scoped actor whose own yayasan_id is null, with no active_lembaga_id set (fail-closed, not fail-open)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    TenantScopeTestModel::withoutGlobalScopes()->create(['lembaga_id' => $lembaga->id, 'label' => 'A']);

    $user = User::factory()->create(['yayasan_id' => null]);
    $user->assignRole('yayasan_super_admin');
    $this->actingAs($user);

    expect(TenantScopeTestModel::count())->toBe(0);
});

it('shows a yayasan-scoped actor "pool" rows (lembaga_id null, yayasan_id matches own) on a model with its own yayasan_id column', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    TenantScopeTestPoolModel::withoutGlobalScopes()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id, 'label' => 'Pool Saya']);
    TenantScopeTestPoolModel::withoutGlobalScopes()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanLain->id, 'label' => 'Pool Yayasan Lain']);

    $user = User::factory()->create(['yayasan_id' => $yayasanSaya->id]);
    $user->assignRole('yayasan_super_admin');
    $this->actingAs($user);

    // Regression test for the "pool karyawan" gap found while fixing the yayasan-filter leak:
    // a naive lembaga_id-only filter would hide legitimate yayasan-level rows (lembaga_id
    // null) even from the actor's own yayasan. The model's own yayasan_id column must widen
    // visibility to those rows too, without leaking another yayasan's pool rows.
    expect(TenantScopeTestPoolModel::pluck('label')->all())->toBe(['Pool Saya']);
});

it('does not fail open to every null-yayasan_id pool row when the actor\'s own yayasan_id is also null', function () {
    $yayasan = Yayasan::factory()->create();
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    TenantScopeTestPoolModel::withoutGlobalScopes()->create(['lembaga_id' => null, 'yayasan_id' => null, 'label' => 'Orphan Pool']);
    TenantScopeTestPoolModel::withoutGlobalScopes()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id, 'label' => 'Pool Yayasan Lain']);

    $user = User::factory()->create(['yayasan_id' => null]);
    $user->assignRole('yayasan_super_admin');
    $this->actingAs($user);

    // orWhere('yayasan_id', $actingUser->yayasan_id) must never run when the actor's own
    // yayasan_id is null - Eloquent compiles where(col, null) to "col IS NULL", which would
    // otherwise match every orphaned pool row system-wide, reopening the fail-open hole.
    expect(TenantScopeTestPoolModel::count())->toBe(0);
});

it('respects a yayasan-scoped user\'s active_lembaga_id session filter', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    TenantScopeTestModel::withoutGlobalScopes()->create(['lembaga_id' => $lembagaA->id, 'label' => 'A']);
    TenantScopeTestModel::withoutGlobalScopes()->create(['lembaga_id' => $lembagaB->id, 'label' => 'B']);

    $user = User::factory()->create();
    $user->assignRole('yayasan_super_admin');
    $this->actingAs($user);
    session(['active_lembaga_id' => $lembagaA->id]);

    expect(TenantScopeTestModel::pluck('label')->all())->toBe(['A']);
});

it('auto-fills lembaga_id from the authenticated lembaga-scoped user on create', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');
    $this->actingAs($user);

    $model = TenantScopeTestModel::create(['label' => 'auto']);

    expect($model->lembaga_id)->toBe($lembaga->id);
});

it('does not recurse when a real session login resolves a tenant-scoped user', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $user = User::factory()->create([
        'lembaga_id' => $lembaga->id,
        'password' => Hash::make('password'),
    ]);
    $user->assignRole('admin_administrasi');

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    // A second, separate request forces SessionGuard to re-resolve the user from
    // the session id via a real database query, instead of reusing an in-memory
    // instance the way actingAs() does in the tests above.
    $this->get('/dashboard')->assertOk();
});

it('returns zero rows for a non-yayasan actor with a null lembaga_id (fail-closed, not fail-open)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    TenantScopeTestModel::withoutGlobalScopes()->create(['lembaga_id' => $lembaga->id, 'label' => 'A']);

    $actorWithNullLembaga = User::factory()->create(['lembaga_id' => null]);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $actorWithNullLembaga->assignRole('orang_tua');

    $this->actingAs($actorWithNullLembaga);

    expect(TenantScopeTestModel::count())->toBe(0);
});

it('only lists staff belonging to the acting lembaga-scoped user\'s own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $staffA = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    User::factory()->create(['lembaga_id' => $lembagaB->id]);

    $this->actingAs($staffA);

    expect(User::pluck('id')->all())->toBe([$staffA->id]);
});
