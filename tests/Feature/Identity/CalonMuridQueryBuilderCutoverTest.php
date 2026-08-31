<?php

use App\Models\CalonMurid;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsPendaftaranManagerForQueryTest(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'spmb-pendaftaran.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'panitia_spmb', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['spmb-pendaftaran.view']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('searches pendaftaran by calon murid person nama_lengkap and exact NIK hash', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $gelombang = GelombangPpdb::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $jalur = JalurPpdb::factory()->create(['lembaga_id' => $lembaga->id]);

    $manager = actingAsPendaftaranManagerForQueryTest($lembaga);

    $cm1 = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => 'Rizky Ramadhan', 'nik' => '3201998877660001']);
    $cm2 = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => 'Nurul Hidayah', 'nik' => '3201998877660002']);

    $p1 = Pendaftaran::factory()->create([
        'lembaga_id' => $lembaga->id,
        'calon_murid_id' => $cm1->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'jalur_ppdb_id' => $jalur->id,
        'status' => 'menunggu_verifikasi',
    ]);

    $p2 = Pendaftaran::factory()->create([
        'lembaga_id' => $lembaga->id,
        'calon_murid_id' => $cm2->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'jalur_ppdb_id' => $jalur->id,
        'status' => 'menunggu_verifikasi',
    ]);

    // Search by name partial
    $response = $this->actingAs($manager)->getJson(route('admin.spmb-pendaftaran.data', ['search' => 'Ramadhan']));
    $response->assertOk();
    $response->assertSee('Rizky Ramadhan');
    $response->assertDontSee('Nurul Hidayah');

    // Search by exact NIK (matched via sha256 nik_hash)
    $response2 = $this->actingAs($manager)->getJson(route('admin.spmb-pendaftaran.data', ['search' => '3201998877660002']));
    $response2->assertOk();
    $response2->assertSee('Nurul Hidayah');
    $response2->assertDontSee('Rizky Ramadhan');
});

it('orders calon murid by person nama_lengkap correctly', function () {
    $yayasan = Yayasan::factory()->create();

    $cm1 = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => 'Zaky']);
    $cm2 = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => 'Anisa']);
    $cm3 = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => 'Farhan']);

    $ordered = CalonMurid::where('calon_murid.yayasan_id', $yayasan->id)->orderByNama()->get();
    expect($ordered->pluck('nama_lengkap')->toArray())->toBe(['Anisa', 'Farhan', 'Zaky']);
});
