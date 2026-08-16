<?php

namespace Tests\Feature\Sarpras;

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\SarprasPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapAsetGlobalTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_access_dashboard_and_rekap_aset_global(): void
    {
        $this->seed(SarprasPermissionSeeder::class);

        $yayasan = Yayasan::create(['nama' => 'Yayasan Pendidikan']);
        $lembaga = Lembaga::create([
            'yayasan_id' => $yayasan->id,
            'nama' => 'SMP IT Unggulan',
            'jenjang' => 'SMP',
            'npsn' => '11223344',
            'status_aktif' => true,
        ]);

        $superAdminRole = Role::firstOrCreate(
            ['name' => 'yayasan_super_admin', 'guard_name' => 'web'],
            ['scope_level' => 'yayasan', 'is_protected' => true]
        );

        $superAdmin = User::factory()->create([
            'email' => 'superadmin@sistem.test',
            'lembaga_id' => null,
        ]);
        $superAdmin->assignRole($superAdminRole);
        $superAdmin->givePermissionTo('sarpras.aset.view');

        // Test dashboard access without crashing on null lembaga_id
        $dashboardResponse = $this->actingAs($superAdmin)->get(route('dashboard'));
        $dashboardResponse->assertOk();

        // Test rekap global access without crashing on missing Lembaga::gedung relation
        $rekapResponse = $this->actingAs($superAdmin)->get(route('admin.sarpras.rekap-global'));
        $rekapResponse->assertOk();
        $rekapResponse->assertSeeText('Rekapitulasi Sarpras Yayasan');
        $rekapResponse->assertSeeText('SMP IT Unggulan');
    }
}
