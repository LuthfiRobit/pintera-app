<?php

use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Yayasan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('rejects a null person_id insert on guru after NOT NULL constraint', function () {
    $lembaga = Lembaga::factory()->create();

    DB::table('guru')->insert([
        'lembaga_id' => $lembaga->id,
        'person_id' => null,
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'tetap',
        'status_aktif' => 'aktif',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('rejects a null person_id insert on karyawan after NOT NULL constraint', function () {
    $lembaga = Lembaga::factory()->create();

    DB::table('karyawan')->insert([
        'lembaga_id' => $lembaga->id,
        'person_id' => null,
        'status_aktif' => 'aktif',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('rejects a null person_id insert on siswa after NOT NULL constraint', function () {
    $lembaga = Lembaga::factory()->create();

    DB::table('siswa')->insert([
        'lembaga_id' => $lembaga->id,
        'person_id' => null,
        'status' => 'aktif',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('enforces foreign key constraint on person_id', function () {
    $lembaga = Lembaga::factory()->create();

    DB::table('guru')->insert([
        'lembaga_id' => $lembaga->id,
        'person_id' => 999999999,
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'tetap',
        'status_aktif' => 'aktif',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('enforces uniqueness of person_id and lembaga_id on guru', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id]);

    Guru::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => $person->id]);

    // Inserting a second guru for the same person in the SAME lembaga should fail
    DB::table('guru')->insert([
        'lembaga_id' => $lembaga->id,
        'person_id' => $person->id,
        'jenis_ptk' => 'guru_mapel',
        'status_kepegawaian' => 'honorer',
        'status_aktif' => 'aktif',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('enforces uniqueness of person_id on orang_tua', function () {
    $yayasan = Yayasan::factory()->create();
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id]);

    OrangTua::factory()->create(['person_id' => $person->id]);

    // Inserting a second orang_tua for the same person should fail
    DB::table('orang_tua')->insert([
        'person_id' => $person->id,
        'nama_lengkap' => 'Duplicate Ortu',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);
