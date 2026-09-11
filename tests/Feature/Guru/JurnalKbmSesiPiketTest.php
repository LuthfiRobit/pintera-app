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

it('guru piket tetap terdeteksi piket pada jam dini hari WIB yang setara hari sebelumnya di UTC', function () {
    // 03:00 WIB tanggal 20 = 20:00 UTC tanggal 19 (hari SEBELUMNYA jika server pakai UTC bare now()).
    \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-08-20 03:00:00', 'Asia/Jakarta'));

    try {
        $yayasan = Yayasan::factory()->create();
        $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
        $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
        $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

        Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'guru_timezone_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
        $role->givePermissionTo(['presensi.isi']);

        $guruLain = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
        SesiPembelajaran::factory()->create([
            'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'guru_id' => $guruLain->id, 'tanggal' => '2026-08-20',
        ]);

        $guruPiket = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
        $userPiket = User::factory()->create(['lembaga_id' => $lembaga->id]);
        Person::where('id', $guruPiket->person_id)->update(['user_id' => $userPiket->id]);
        $userPiket->assignRole($role);

        // Baris PiketHarian untuk "20 Agustus" (tanggal WIB sungguhan saat ini).
        PiketHarian::create([
            'lembaga_id' => $lembaga->id, 'guru_id' => $guruPiket->id, 'tanggal' => '2026-08-20', 'sumber' => 'override_manual',
        ]);

        $response = $this->actingAs($userPiket)->get(route('guru.jurnal-kbm.index'));

        $response->assertOk();
        $response->assertSee('Sesi Piket Hari Ini');
    } finally {
        \Carbon\Carbon::setTestNow();
    }
});

