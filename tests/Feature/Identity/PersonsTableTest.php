<?php

use Illuminate\Support\Facades\Schema;

it('creates the persons table with the correct columns and unique constraint', function () {
    expect(Schema::hasTable('persons'))->toBeTrue();
    expect(Schema::hasColumns('persons', [
        'id', 'yayasan_id', 'user_id', 'nik', 'nik_hash', 'nama_lengkap',
        'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'kewarganegaraan',
        'no_hp', 'email', 'alamat_jalan', 'rt', 'rw', 'desa_kelurahan', 'kecamatan',
        'kabupaten_kota', 'provinsi', 'kode_pos', 'merged_into_person_id',
        'deactivated_at', 'deleted_at', 'created_at', 'updated_at',
    ]))->toBeTrue();
    expect(Schema::hasColumn('users', 'merged_into_user_id'))->toBeTrue();
});
