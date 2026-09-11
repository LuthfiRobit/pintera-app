<?php

use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanSesiDanGuruPiketUntukAksesTest(): array
{
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_piket_akses_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $guruPemilik = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $sesi = SesiPembelajaran::factory()->create([
        'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'guru_id' => $guruPemilik->id, 'tanggal' => now()->toDateString(),
    ]);

    $guruPiket = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $userPiket = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Person::where('id', $guruPiket->person_id)->update(['user_id' => $userPiket->id]);
    $userPiket->assignRole($role);
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guruPiket->id, 'tanggal' => now()->toDateString(), 'sumber' => 'override_manual']);

    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruPiketLembagaLain = Guru::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $userPiketLembagaLain = User::factory()->create(['lembaga_id' => $lembagaLain->id]);
    Person::where('id', $guruPiketLembagaLain->person_id)->update(['user_id' => $userPiketLembagaLain->id]);
    $userPiketLembagaLain->assignRole($role);
    PiketHarian::create(['lembaga_id' => $lembagaLain->id, 'guru_id' => $guruPiketLembagaLain->id, 'tanggal' => now()->toDateString(), 'sumber' => 'override_manual']);

    return compact('sesi', 'userPiket', 'userPiketLembagaLain', 'siswa');
}

it('guru piket hari ini BISA akses show() sesi guru lain lembaga sama', function () {
    ['sesi' => $sesi, 'userPiket' => $userPiket] = siapkanSesiDanGuruPiketUntukAksesTest();

    $response = $this->actingAs($userPiket)->get(route('guru.jurnal-kbm.show', $sesi));

    $response->assertOk();
});

it('guru piket hari ini BISA update() sesi guru lain lembaga sama', function () {
    ['sesi' => $sesi, 'userPiket' => $userPiket, 'siswa' => $siswa] = siapkanSesiDanGuruPiketUntukAksesTest();

    $response = $this->actingAs($userPiket)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Diisi guru piket', 'presensi' => [$siswa->id => 'hadir'],
    ]);

    $response->assertSessionHas('status');
});

it('guru piket lembaga LAIN TIDAK BISA akses sesi', function () {
    ['sesi' => $sesi, 'userPiketLembagaLain' => $userPiketLembagaLain] = siapkanSesiDanGuruPiketUntukAksesTest();

    $response = $this->actingAs($userPiketLembagaLain)->get(route('guru.jurnal-kbm.show', $sesi));

    expect(in_array($response->status(), [403, 404], true))->toBeTrue();
});

it('guru bukan piket dan bukan pemilik lembaga SAMA ditolak 403', function () {
    ['sesi' => $sesi] = siapkanSesiDanGuruPiketUntukAksesTest();

    $role = Role::firstOrCreate(['name' => 'guru_piket_akses_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $guruBukanPiket = Guru::factory()->create(['lembaga_id' => $sesi->lembaga_id]);
    $userBukanPiket = User::factory()->create(['lembaga_id' => $sesi->lembaga_id]);
    Person::where('id', $guruBukanPiket->person_id)->update(['user_id' => $userBukanPiket->id]);
    $userBukanPiket->assignRole($role);

    $response = $this->actingAs($userBukanPiket)->get(route('guru.jurnal-kbm.show', $sesi));

    $response->assertForbidden();
});

it('menampilkan banner Mode Piket saat guru piket membuka sesi guru lain', function () {
    ['sesi' => $sesi, 'userPiket' => $userPiket] = siapkanSesiDanGuruPiketUntukAksesTest();

    $response = $this->actingAs($userPiket)->get(route('guru.jurnal-kbm.show', $sesi));

    $response->assertOk();
    $response->assertSee('Guru Piket');
});

it('tidak menampilkan banner Mode Piket saat guru pemilik membuka sesinya sendiri', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id])->id]);

    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_badge_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $guruPemilik = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $userPemilik = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Person::where('id', $guruPemilik->person_id)->update(['user_id' => $userPemilik->id]);
    $userPemilik->assignRole($role);

    $sesi = SesiPembelajaran::factory()->create([
        'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'guru_id' => $guruPemilik->id, 'tanggal' => now()->toDateString(),
    ]);

    $response = $this->actingAs($userPemilik)->get(route('guru.jurnal-kbm.show', $sesi));

    $response->assertOk();
    $response->assertDontSee('Guru Piket');
});

