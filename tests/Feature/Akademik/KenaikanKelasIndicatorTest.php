<?php

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('menampilkan indikator kelas kosong/sudah diproses di halaman Kenaikan Kelas', function () {
    Permission::firstOrCreate(['name' => 'kenaikan-kelas.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'operator_indicator_test', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kenaikan-kelas.kelola']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);

    $tahunAjaranLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunAjaranBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasKosong = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranLama->id, 'nama' => 'Kelas Kosong Uji']);
    // Sengaja tidak buat Siswa untuk $kelasKosong -> siswa_count = 0

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('admin.kenaikan-kelas.index', [
        'tahun_ajaran_id' => $tahunAjaranLama->id,
        'tahun_ajaran_tujuan_id' => $tahunAjaranBaru->id,
    ]));

    $response->assertOk();
    $response->assertSee('Sudah diproses', false);
});
