<?php

namespace Tests\Feature\Admin;

use App\Enums\StatusSiswa;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SiswaAccountGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Lembaga $lembaga;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $yayasan = Yayasan::factory()->create();
        $this->lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'SMP1']);

        foreach (['siswa.view', 'siswa.create', 'siswa.edit'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'admin_akademik_test', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $role->givePermissionTo(['siswa.view', 'siswa.create', 'siswa.edit']);
        Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

        $this->manager = User::factory()->create(['lembaga_id' => $this->lembaga->id]);
        $this->manager->assignRole($role);
    }

    public function test_can_generate_account_for_single_student(): void
    {
        $siswa = Siswa::factory()->create([
            'lembaga_id' => $this->lembaga->id,
            'nis' => '2026888',
            'nama_lengkap' => 'Banu Pratama',
            'user_id' => null,
            'status' => StatusSiswa::Aktif->value,
        ]);

        $response = $this->actingAs($this->manager)->post(route('admin.siswa.generate-akun', $siswa));

        $response->assertRedirect()->assertSessionHas('status');
        $siswa->refresh();
        $this->assertNotNull($siswa->user_id);
        $this->assertDatabaseHas('users', [
            'id' => $siswa->user_id,
            'name' => 'Banu Pratama',
            'lembaga_id' => $this->lembaga->id,
            'username' => 'SMP1-2026888',
        ]);
    }

    public function test_can_generate_accounts_in_bulk_for_active_unassigned_students_only(): void
    {
        $siswa1 = Siswa::factory()->create(['lembaga_id' => $this->lembaga->id, 'user_id' => null, 'status' => StatusSiswa::Aktif->value, 'nis' => '11111']);
        $siswa2 = Siswa::factory()->create(['lembaga_id' => $this->lembaga->id, 'user_id' => null, 'status' => StatusSiswa::Aktif->value, 'nis' => '22222']);
        $siswaInactive = Siswa::factory()->create(['lembaga_id' => $this->lembaga->id, 'user_id' => null, 'status' => StatusSiswa::Lulus->value, 'nis' => '33333']);

        $response = $this->actingAs($this->manager)->post(route('admin.siswa.generate-akun-massal'));

        $response->assertRedirect()->assertSessionHas('status');

        $this->assertNotNull($siswa1->refresh()->user_id);
        $this->assertNotNull($siswa2->refresh()->user_id);
        $this->assertNull($siswaInactive->refresh()->user_id);
    }
}
