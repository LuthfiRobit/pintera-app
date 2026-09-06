<?php

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanGuruMapelBiasa(int $batasEditHari = 3): array
{
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_batas_edit_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'batas_edit_absen_hari' => $batasEditHari]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Person::where('id', $guru->person_id)->update(['user_id' => $user->id]);
    $user->assignRole($role);

    $jadwal = JadwalPelajaran::factory()->create(['guru_id' => $guru->id, 'kelas_id' => $kelas->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return compact('user', 'guru', 'lembaga', 'kelas', 'jadwal', 'siswa');
}

it('guru mapel biasa bisa edit sesi dalam batas hari', function () {
    ['user' => $user, 'guru' => $guru, 'kelas' => $kelas, 'jadwal' => $jadwal, 'siswa' => $siswa] = siapkanGuruMapelBiasa(3);
    $sesi = SesiPembelajaran::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'jadwal_pelajaran_id' => $jadwal->id,
        'lembaga_id' => $guru->lembaga_id, 'tanggal' => now()->subDays(2)->toDateString(),
    ]);
    Presensi::create(['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id, 'status' => 'hadir']);

    $response = $this->actingAs($user)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Materi baru', 'presensi' => [$siswa->id => 'hadir'],
    ]);

    $response->assertRedirect(route('guru.jurnal-kbm.index'));
    $response->assertSessionHas('status');
    expect($sesi->fresh()->materi)->toBe('Materi baru');
});

it('guru mapel biasa DITOLAK edit sesi di luar batas hari, tidak ada perubahan tersimpan', function () {
    ['user' => $user, 'guru' => $guru, 'kelas' => $kelas, 'jadwal' => $jadwal, 'siswa' => $siswa] = siapkanGuruMapelBiasa(3);
    $sesi = SesiPembelajaran::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'jadwal_pelajaran_id' => $jadwal->id,
        'lembaga_id' => $guru->lembaga_id, 'tanggal' => now()->subDays(10)->toDateString(), 'materi' => 'Materi lama',
    ]);
    Presensi::create(['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id, 'status' => 'hadir']);

    $response = $this->actingAs($user)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Coba diubah', 'presensi' => [$siswa->id => 'hadir'],
    ]);

    $response->assertRedirect(route('guru.jurnal-kbm.index'));
    $response->assertSessionHas('error');
    expect($sesi->fresh()->materi)->toBe('Materi lama');
});

it('wali kelas kelasnya sendiri BISA edit sesi di luar batas hari', function () {
    ['user' => $user, 'guru' => $guru, 'kelas' => $kelas, 'jadwal' => $jadwal, 'siswa' => $siswa] = siapkanGuruMapelBiasa(3);
    $kelas->update(['wali_kelas_guru_id' => $guru->id]);
    $sesi = SesiPembelajaran::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'jadwal_pelajaran_id' => $jadwal->id,
        'lembaga_id' => $guru->lembaga_id, 'tanggal' => now()->subDays(10)->toDateString(),
    ]);
    Presensi::create(['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id, 'status' => 'hadir']);

    $response = $this->actingAs($user)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Koreksi wali kelas', 'presensi' => [$siswa->id => 'hadir'],
    ]);

    $response->assertRedirect(route('guru.jurnal-kbm.index'));
    $response->assertSessionHas('status');
    expect($sesi->fresh()->materi)->toBe('Koreksi wali kelas');
});

it('wali kelas KELAS LAIN tetap DITOLAK edit sesi di luar batas hari', function () {
    ['user' => $user, 'guru' => $guru, 'kelas' => $kelas, 'jadwal' => $jadwal, 'lembaga' => $lembaga, 'siswa' => $siswa] = siapkanGuruMapelBiasa(3);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'wali_kelas_guru_id' => $guru->id]);
    $sesi = SesiPembelajaran::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'jadwal_pelajaran_id' => $jadwal->id,
        'lembaga_id' => $lembaga->id, 'tanggal' => now()->subDays(10)->toDateString(), 'materi' => 'Materi lama',
    ]);
    Presensi::create(['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id, 'status' => 'hadir']);

    $response = $this->actingAs($user)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Coba diubah', 'presensi' => [$siswa->id => 'hadir'],
    ]);

    $response->assertSessionHas('error');
    expect($sesi->fresh()->materi)->toBe('Materi lama');
});
