<?php

// tests/Feature/Keuangan/SiswaKeringananPermissionTest.php

use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('a user without siswa-keringanan.kelola cannot access the keringanan routes', function () {
    $siswa = Siswa::factory()->create();
    // Matching lembaga_id so the {siswa} route-model-binding resolves under TenantScope --
    // otherwise a mismatched (null) lembaga_id yields a 404 before authorization ever runs,
    // masking the 403 this test is actually checking for.
    $user = User::factory()->create(['lembaga_id' => $siswa->lembaga_id]); // no permission

    $this->actingAs($user)->get(route('admin.siswa.keringanan.index', $siswa))->assertForbidden();
});
