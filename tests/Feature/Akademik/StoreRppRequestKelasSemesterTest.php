<?php

use App\Domains\Akademik\Enums\StatusRpp;
use App\Domains\Akademik\Models\Rpp;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

function siapkanRppRequestFixture(): array
{
    Storage::fake('public');
    Permission::firstOrCreate(['name' => 'rpp.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'rpp.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_rpp_validasi', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['rpp.view', 'rpp.kelola']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunA = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2025/2026']);
    $tahunB = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027']);
    $semesterTahunA = Semester::factory()->create(['tahun_ajaran_id' => $tahunA->id]);
    $kelasTahunA = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunA->id]);
    $kelasTahunB = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunB->id]);

    $userGuru = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userGuru->assignRole($role);
    $guru = Guru::factory()->create(['user_id' => $userGuru->id, 'lembaga_id' => $lembaga->id]);
    $kelasTahunA->update(['wali_kelas_guru_id' => $guru->id]);

    return [$userGuru, $semesterTahunA, $kelasTahunA, $kelasTahunB];
}

it('rejects store when kelas belongs to a different tahun ajaran than the selected semester', function () {
    [$userGuru, $semesterTahunA, $kelasTahunA, $kelasTahunB] = siapkanRppRequestFixture();
    $file = UploadedFile::fake()->create('rpp.pdf', 100, 'application/pdf');

    $response = $this->actingAs($userGuru)->post(route('admin.rpp.store'), [
        'kelas_id' => $kelasTahunB->id,
        'semester_id' => $semesterTahunA->id,
        'judul_topik' => 'Topik Tidak Konsisten',
        'alokasi_waktu' => '2 JP',
        'file' => $file,
    ]);

    $response->assertSessionHasErrors(['kelas_id']);
    $this->assertDatabaseMissing('rpp', ['judul_topik' => 'Topik Tidak Konsisten']);
});

it('allows store when kelas and semester share the same tahun ajaran', function () {
    [$userGuru, $semesterTahunA, $kelasTahunA, $kelasTahunB] = siapkanRppRequestFixture();
    $file = UploadedFile::fake()->create('rpp.pdf', 100, 'application/pdf');

    $response = $this->actingAs($userGuru)->post(route('admin.rpp.store'), [
        'kelas_id' => $kelasTahunA->id,
        'semester_id' => $semesterTahunA->id,
        'judul_topik' => 'Topik Konsisten',
        'alokasi_waktu' => '2 JP',
        'file' => $file,
    ]);

    $response->assertSessionDoesntHaveErrors();
    $this->assertDatabaseHas('rpp', ['judul_topik' => 'Topik Konsisten', 'kelas_id' => $kelasTahunA->id]);
});

it('rejects update when the new kelas belongs to a different tahun ajaran than the RPP semester', function () {
    [$userGuru, $semesterTahunA, $kelasTahunA, $kelasTahunB] = siapkanRppRequestFixture();
    $guru = Guru::where('user_id', $userGuru->id)->first();
    $rpp = Rpp::create([
        'yayasan_id' => $kelasTahunA->lembaga->yayasan_id, 'lembaga_id' => $kelasTahunA->lembaga_id, 'guru_id' => $guru->id,
        'tahun_ajaran_id' => $semesterTahunA->tahun_ajaran_id, 'semester_id' => $semesterTahunA->id, 'kelas_id' => $kelasTahunA->id,
        'judul_topik' => 'RPP Sebelum Update', 'alokasi_waktu' => '2 JP',
        'file_path' => 'rpp/existing.pdf', 'file_name' => 'existing.pdf', 'file_size_bytes' => 1024, 'mime_type' => 'application/pdf',
        'status' => StatusRpp::Draft,
    ]);

    $response = $this->actingAs($userGuru)->put(route('admin.rpp.update', $rpp), [
        'kelas_id' => $kelasTahunB->id,
        'judul_topik' => 'RPP Sesudah Update',
        'alokasi_waktu' => '2 JP',
    ]);

    $response->assertSessionHasErrors(['kelas_id']);
    expect($rpp->fresh()->kelas_id)->toBe($kelasTahunA->id);
});

it('allows update when the new kelas shares the same tahun ajaran as the RPP semester', function () {
    [$userGuru, $semesterTahunA, $kelasTahunA, $kelasTahunB] = siapkanRppRequestFixture();
    $guru = Guru::where('user_id', $userGuru->id)->first();
    $kelasTahunALain = Kelas::factory()->create(['lembaga_id' => $kelasTahunA->lembaga_id, 'tahun_ajaran_id' => $kelasTahunA->tahun_ajaran_id]);
    $rpp = Rpp::create([
        'yayasan_id' => $kelasTahunA->lembaga->yayasan_id, 'lembaga_id' => $kelasTahunA->lembaga_id, 'guru_id' => $guru->id,
        'tahun_ajaran_id' => $semesterTahunA->tahun_ajaran_id, 'semester_id' => $semesterTahunA->id, 'kelas_id' => $kelasTahunA->id,
        'judul_topik' => 'RPP Sebelum Update Valid', 'alokasi_waktu' => '2 JP',
        'file_path' => 'rpp/existing2.pdf', 'file_name' => 'existing2.pdf', 'file_size_bytes' => 1024, 'mime_type' => 'application/pdf',
        'status' => StatusRpp::Draft,
    ]);

    $response = $this->actingAs($userGuru)->put(route('admin.rpp.update', $rpp), [
        'kelas_id' => $kelasTahunALain->id,
        'judul_topik' => 'RPP Sesudah Update Valid',
        'alokasi_waktu' => '2 JP',
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($rpp->fresh()->kelas_id)->toBe($kelasTahunALain->id);
});
