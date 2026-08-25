<?php

use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatPlatformSuperAdmin(): User
{
    Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform', 'is_protected' => true]);
    $admin = User::factory()->create();
    $admin->assignRole('platform_super_admin');

    return $admin;
}

it('lets a platform_super_admin see User rows across multiple yayasan and lembaga', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $userA = User::factory()->create(['lembaga_id' => $lembagaA->id, 'email' => 'usera@example.test']);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $userB = User::factory()->create(['lembaga_id' => $lembagaB->id, 'email' => 'userb@example.test']);

    $platformAdmin = buatPlatformSuperAdmin();

    $this->actingAs($platformAdmin);

    $visibleEmails = User::pluck('email');

    expect($visibleEmails)->toContain('usera@example.test');
    expect($visibleEmails)->toContain('userb@example.test');
});

it('does not extend the platform bypass to other tenant-scoped models like Karyawan', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    Karyawan::factory()->create(['yayasan_id' => $yayasanA->id, 'lembaga_id' => $lembagaA->id, 'nik' => '1111111111111111']);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    Karyawan::factory()->create(['yayasan_id' => $yayasanB->id, 'lembaga_id' => $lembagaB->id, 'nik' => '2222222222222222']);

    $platformAdmin = buatPlatformSuperAdmin();

    $this->actingAs($platformAdmin);

    // platformAdmin punya lembaga_id null, jadi cabang default (where lembaga_id = null)
    // tetap berlaku untuk Karyawan -- membuktikan bypass TIDAK menyebar ke model lain.
    expect(Karyawan::count())->toBe(0);
});

it('still isolates a yayasan-scoped admin to their own yayasan after the platform bypass is added', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $userA = User::factory()->create(['lembaga_id' => $lembagaA->id, 'email' => 'staffa@example.test']);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    User::factory()->create(['lembaga_id' => $lembagaB->id, 'email' => 'staffb@example.test']);

    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $managerA = User::factory()->create(['yayasan_id' => $yayasanA->id]);
    $managerA->assignRole('yayasan_super_admin');

    $this->actingAs($managerA);

    $visibleEmails = User::pluck('email');

    expect($visibleEmails)->toContain('staffa@example.test');
    expect($visibleEmails)->not->toContain('staffb@example.test');
});
