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

function siapkanGuruPiketDanSesiUntukAkuntabilitasTest(): array
{
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_akuntabilitas_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $guruPemilik = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $userPemilik = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Person::where('id', $guruPemilik->person_id)->update(['user_id' => $userPemilik->id]);
    $userPemilik->assignRole($role);

    $sesi = SesiPembelajaran::factory()->create([
        'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'guru_id' => $guruPemilik->id, 'tanggal' => now()->toDateString(),
    ]);

    $guruPiket = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $userPiket = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Person::where('id', $guruPiket->person_id)->update(['user_id' => $userPiket->id]);
    $userPiket->assignRole($role);
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guruPiket->id, 'tanggal' => now()->toDateString(), 'sumber' => 'override_manual']);

    return compact('sesi', 'guruPemilik', 'userPemilik', 'guruPiket', 'userPiket', 'siswa');
}

it('guru piket submit jurnal -- diisi_oleh_guru_id terisi ID guru piket', function () {
    ['sesi' => $sesi, 'userPiket' => $userPiket, 'guruPiket' => $guruPiket, 'siswa' => $siswa] = siapkanGuruPiketDanSesiUntukAkuntabilitasTest();

    $this->actingAs($userPiket)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Diisi guru piket', 'presensi' => [$siswa->id => 'hadir'],
    ]);

    expect($sesi->fresh()->diisi_oleh_guru_id)->toBe($guruPiket->id);
});

it('guru pemilik asli submit jurnal untuk sesinya sendiri -- diisi_oleh_guru_id TETAP null', function () {
    ['sesi' => $sesi, 'userPemilik' => $userPemilik, 'siswa' => $siswa] = siapkanGuruPiketDanSesiUntukAkuntabilitasTest();

    $this->actingAs($userPemilik)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Diisi guru pemilik', 'presensi' => [$siswa->id => 'hadir'],
    ]);

    expect($sesi->fresh()->diisi_oleh_guru_id)->toBeNull();
});

it('guru pemilik submit ulang SETELAH pernah diisi guru piket -- diisi_oleh_guru_id TIDAK tertimpa null', function () {
    ['sesi' => $sesi, 'userPiket' => $userPiket, 'guruPiket' => $guruPiket, 'userPemilik' => $userPemilik, 'siswa' => $siswa] = siapkanGuruPiketDanSesiUntukAkuntabilitasTest();

    // Guru piket isi duluan.
    $this->actingAs($userPiket)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Diisi guru piket', 'presensi' => [$siswa->id => 'hadir'],
    ]);
    expect($sesi->fresh()->diisi_oleh_guru_id)->toBe($guruPiket->id);

    // Guru pemilik asli submit ulang (koreksi kecil) beberapa saat kemudian.
    $this->actingAs($userPemilik)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Dikoreksi oleh guru pemilik', 'presensi' => [$siswa->id => 'hadir'],
    ]);

    // Jejak akuntabilitas HARUS tetap menunjuk ke guru piket, TIDAK tertimpa null.
    expect($sesi->fresh()->diisi_oleh_guru_id)->toBe($guruPiket->id);
    expect($sesi->fresh()->materi)->toBe('Dikoreksi oleh guru pemilik');
});

