<?php

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('404s when a lembaga-scoped admin opens the edit page for a guru in another lembaga', function () {
    foreach (['guru.view', 'guru.create', 'guru.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['guru.view', 'guru.create', 'guru.edit']);

    $yayasan = Yayasan::factory()->create();
    $ownLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $manager = User::factory()->create(['lembaga_id' => $ownLembaga->id]);
    $manager->assignRole($role);

    $otherGuru = Guru::factory()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $otherLembaga->id])->id,
        'lembaga_id' => $otherLembaga->id,
        'nik' => '3201234567897777',
        'nama' => 'Guru Lembaga Lain',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $this->actingAs($manager)->get(route('admin.guru.edit', $otherGuru))->assertNotFound();
});

it('404s when a lembaga-scoped admin tries to activate a tahun ajaran belonging to another lembaga', function () {
    Permission::firstOrCreate(['name' => 'tahun-ajaran.activate', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('tahun-ajaran.activate');

    $yayasan = Yayasan::factory()->create();
    $ownLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $manager = User::factory()->create(['lembaga_id' => $ownLembaga->id]);
    $manager->assignRole($role);

    $otherTahunAjaran = TahunAjaran::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id,
        'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
    ]);

    $this->actingAs($manager)
        ->patch(route('admin.tahun-ajaran.activate', $otherTahunAjaran))
        ->assertNotFound();
});

it('lets a yayasan-scoped user filter the guru list down to one lembaga via the switcher, and back to all', function () {
    foreach (['guru.view', 'guru.create', 'guru.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['guru.view', 'guru.create', 'guru.edit']);

    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    Guru::factory()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaA->id])->id,
        'lembaga_id' => $lembagaA->id, 'nik' => '3201234567898881', 'nama' => 'Guru A',
        'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
    ]);
    Guru::factory()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaB->id])->id,
        'lembaga_id' => $lembagaB->id, 'nik' => '3201234567898882', 'nama' => 'Guru Kedua',
        'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
    ]);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    $this->actingAs($manager);

    // Switch to lembaga A via the ResolveTenant middleware query param.
    $this->get('/dashboard?switch_lembaga='.$lembagaA->id);

    $response = $this->get(route('admin.guru.index'));
    $response->assertSee('Guru A');
    $response->assertDontSee('Guru Kedua');

    // Switch back to "all".
    $this->get('/dashboard?switch_lembaga=all');

    $response = $this->get(route('admin.guru.index'));
    $response->assertSee('Guru A');
    $response->assertSee('Guru Kedua');
});
