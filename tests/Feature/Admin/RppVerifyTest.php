<?php

declare(strict_types=1);

use App\Domains\Akademik\Enums\StatusRpp;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\Rpp;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('lets a yayasan-scope actor verify an RPP belonging to a lembaga under their own yayasan', function () {
    Permission::firstOrCreate(['name' => 'rpp.verify', 'guard_name' => 'web']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $role = Role::firstOrCreate(['name' => 'yayasan_verify_rpp', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['rpp.verify']);
    $verifier = User::factory()->create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null]);
    $verifier->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $rpp = Rpp::create([
        'yayasan_id' => $yayasan->id,
        'lembaga_id' => $lembaga->id,
        'guru_id' => $guru->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'semester_id' => $semester->id,
        'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $mapel->id,
        'judul_topik' => 'RPP Test',
        'alokasi_waktu' => '2 JP',
        'file_path' => 'rpp/test.pdf',
        'file_name' => 'test.pdf',
        'file_size_bytes' => 1024,
        'mime_type' => 'application/pdf',
        'status' => StatusRpp::Diajukan,
    ]);

    $response = $this->actingAs($verifier)->post(route('admin.rpp.verify', $rpp), [
        'status' => StatusRpp::Disetujui->value,
    ]);

    $response->assertRedirect();
    expect($rpp->fresh()->status)->toBe(StatusRpp::Disetujui);
});

it('rejects a yayasan-scope actor without active_lembaga_id in session when verifying RPP', function () {
    Permission::firstOrCreate(['name' => 'rpp.verify', 'guard_name' => 'web']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $role = Role::firstOrCreate(['name' => 'yayasan_verify_rpp2', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['rpp.verify']);
    $verifier = User::factory()->create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null]);
    $verifier->assignRole($role);
    session()->forget('active_lembaga_id');

    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $rpp = Rpp::create([
        'yayasan_id' => $yayasan->id,
        'lembaga_id' => $lembaga->id,
        'guru_id' => $guru->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'semester_id' => $semester->id,
        'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $mapel->id,
        'judul_topik' => 'RPP Test',
        'alokasi_waktu' => '2 JP',
        'file_path' => 'rpp/test.pdf',
        'file_name' => 'test.pdf',
        'file_size_bytes' => 1024,
        'mime_type' => 'application/pdf',
        'status' => StatusRpp::Diajukan,
    ]);

    $response = $this->actingAs($verifier)->post(route('admin.rpp.verify', $rpp), [
        'status' => StatusRpp::Disetujui->value,
    ]);

    $response->assertStatus(422);
});
