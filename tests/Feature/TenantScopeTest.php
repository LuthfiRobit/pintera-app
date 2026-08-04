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

beforeEach(function () {
    Schema::create('tenant_scope_test_models', function ($table) {
        $table->id();
        $table->foreignId('lembaga_id')->nullable();
        $table->string('label');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('tenant_scope_test_models');
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

it('returns rows for all lembaga when the user has a yayasan-scoped role', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    TenantScopeTestModel::withoutGlobalScopes()->create(['lembaga_id' => $lembagaA->id, 'label' => 'A']);
    TenantScopeTestModel::withoutGlobalScopes()->create(['lembaga_id' => $lembagaB->id, 'label' => 'B']);

    $user = User::factory()->create();
    $user->assignRole('yayasan_super_admin');
    $this->actingAs($user);

    expect(TenantScopeTestModel::pluck('label')->all())->toBe(['A', 'B']);
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
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

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
