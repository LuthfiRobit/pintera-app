<?php

use App\Domains\Identity\Models\Person;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\QueryException;
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
    $person = Person::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    $nik = '1234567890123456';
    $nik_hash = hash('sha256', $nik);

    $id = DB::table('guru')->insertGetId([
        'user_id' => $user->id,
        'person_id' => $person->id,
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

it('preserves the jenis_kelamin enum constraint and kewarganegaraan default after relaxing guru to nullable', function () {
    $lembaga = Lembaga::factory()->create();
    $person1 = Person::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    $person2 = Person::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);

    expect(fn () => DB::table('guru')->insert([
        'user_id' => User::factory()->create()->id,
        'person_id' => $person1->id,
        'lembaga_id' => $lembaga->id,
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'PNS',
        'status_aktif' => 'aktif',
        'jenis_kelamin' => 'X',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $id = DB::table('guru')->insertGetId([
        'user_id' => User::factory()->create()->id,
        'person_id' => $person2->id,
        'lembaga_id' => $lembaga->id,
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'PNS',
        'status_aktif' => 'aktif',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('guru')->where('id', $id)->value('kewarganegaraan'))->toBe('WNI');
});

it('allows inserting karyawan without legacy identity columns populated', function () {
    $yayasan = Yayasan::factory()->create();
    $jenisKaryawan = JenisKaryawanMaster::factory()->create();
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id]);

    $id = DB::table('karyawan')->insertGetId([
        'user_id' => User::factory()->create()->id,
        'person_id' => $person->id,
        'yayasan_id' => $yayasan->id,
        'jenis_karyawan_id' => $jenisKaryawan->id,
        'status_aktif' => 'aktif',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($id)->toBeGreaterThan(0);
});

it('allows inserting orang_tua without legacy identity columns populated', function () {
    $person = Person::factory()->create();

    $id = DB::table('orang_tua')->insertGetId([
        'user_id' => User::factory()->create()->id,
        'person_id' => $person->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($id)->toBeGreaterThan(0);
});

it('allows inserting calon_murid without legacy identity columns populated', function () {
    $yayasan = Yayasan::factory()->create();
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id]);

    $id = DB::table('calon_murid')->insertGetId([
        'yayasan_id' => $yayasan->id,
        'person_id' => $person->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($id)->toBeGreaterThan(0);
});
