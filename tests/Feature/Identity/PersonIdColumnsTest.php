<?php

use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('adds nullable person_id to all 5 role tables', function () {
    foreach (['guru', 'karyawan', 'orang_tua', 'siswa', 'calon_murid'] as $table) {
        expect(Schema::hasColumn($table, 'person_id'))->toBeTrue();
    }
});

it('allows inserting guru without legacy identity columns populated', function () {
    $user = User::factory()->create();
    $lembaga = Lembaga::factory()->create();
    $nik = '1234567890123456';
    $nik_hash = hash('sha256', $nik);

    $id = DB::table('guru')->insertGetId([
        'user_id' => $user->id,
        'lembaga_id' => $lembaga->id,
        'nik' => $nik,
        'nik_hash' => $nik_hash,
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'PNS',
        'status_aktif' => 'aktif',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($id)->toBeGreaterThan(0);
});
