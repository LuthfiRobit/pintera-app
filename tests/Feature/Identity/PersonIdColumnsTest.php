<?php

use App\Domains\Identity\Models\Person;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('adds person_id to all 5 role tables', function () {
    foreach (['guru', 'karyawan', 'orang_tua', 'siswa', 'calon_murid'] as $table) {
        expect(Schema::hasColumn($table, 'person_id'))->toBeTrue();
    }
});

it('allows inserting guru without legacy identity columns populated', function () {
    $lembaga = Lembaga::factory()->create();
    $person = Person::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);

    $id = DB::table('guru')->insertGetId([
        'person_id' => $person->id,
        'lembaga_id' => $lembaga->id,
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'PNS',
        'status_aktif' => 'aktif',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($id)->toBeGreaterThan(0);
});

it('preserves the jenis_kelamin enum constraint and kewarganegaraan default on persons', function () {
    $yayasan = Yayasan::factory()->create();

    expect(fn () => DB::table('persons')->insert([
        'yayasan_id' => $yayasan->id,
        'nama_lengkap' => 'Invalid Enum Person',
        'jenis_kelamin' => 'X',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $id = DB::table('persons')->insertGetId([
        'yayasan_id' => $yayasan->id,
        'nama_lengkap' => 'Default WNI Person',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('persons')->where('id', $id)->value('kewarganegaraan'))->toBe('WNI');
});

it('allows inserting karyawan without legacy identity columns populated', function () {
    $yayasan = Yayasan::factory()->create();
    $jenisKaryawan = JenisKaryawanMaster::factory()->create();
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id]);

    $id = DB::table('karyawan')->insertGetId([
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
