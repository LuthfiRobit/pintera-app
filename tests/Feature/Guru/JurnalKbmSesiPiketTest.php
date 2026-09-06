<?php

use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanGuruUntukSesiPiketTest(bool $piket): array
{
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_sesi_piket_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $guruLain = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    SesiPembelajaran::factory()->create([
        'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'guru_id' => $guruLain->id, 'tanggal' => now()->toDateString(),
    ]);

    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Person::where('id', $guru->person_id)->update(['user_id' => $user->id]);
    $user->assignRole($role);

    if ($piket) {
        PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => now()->toDateString(), 'sumber' => 'override_manual']);
    }

    return compact('user');
}

it('guru YANG PIKET hari ini melihat seksi Sesi Piket Hari Ini', function () {
    ['user' => $user] = siapkanGuruUntukSesiPiketTest(piket: true);

    $response = $this->actingAs($user)->get(route('guru.jurnal-kbm.index'));

    $response->assertOk();
    $response->assertSee('Sesi Piket Hari Ini');
});

it('guru YANG BUKAN piket hari ini TIDAK melihat seksi Sesi Piket Hari Ini sama sekali', function () {
    ['user' => $user] = siapkanGuruUntukSesiPiketTest(piket: false);

    $response = $this->actingAs($user)->get(route('guru.jurnal-kbm.index'));

    $response->assertOk();
    $response->assertDontSee('Sesi Piket Hari Ini');
});
