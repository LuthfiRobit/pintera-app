<?php

use App\Domains\Identity\Models\Person;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\CalonMurid;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    foreach (['guru', 'karyawan', 'orang_tua', 'siswa', 'calon_murid'] as $table) {
        DB::statement("ALTER TABLE {$table} MODIFY person_id BIGINT UNSIGNED NULL");
    }
    DB::beginTransaction();
});

afterEach(function () {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    foreach (['guru', 'karyawan', 'orang_tua', 'siswa', 'calon_murid', 'persons', 'users', 'lembagas', 'yayasans'] as $table) {
        try {
            DB::table($table)->delete();
        } catch (Throwable) {
        }
    }
    foreach (['guru', 'karyawan', 'orang_tua', 'siswa', 'calon_murid'] as $table) {
        DB::statement("ALTER TABLE {$table} MODIFY person_id BIGINT UNSIGNED NOT NULL");
    }
    DB::beginTransaction();
});

it('backfills a Person for each guru row and links person_id', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $guruId = DB::table('guru')->insertGetId([
        'user_id' => $user->id,
        'lembaga_id' => $lembaga->id,
        'nik' => '1111111111111111',
        'nama' => 'Budi Santoso',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'PNS',
        'status_aktif' => 'aktif',
        'kewarganegaraan' => 'WNI',
        'alamat_jalan' => 'Jl. Merdeka No. 1',
        'rt' => '001',
        'rw' => '002',
        'desa_kelurahan' => 'Kelurahan Maju',
        'kecamatan' => 'Kecamatan Sejahtera',
        'kabupaten_kota' => 'Kabupaten Makmur',
        'provinsi' => 'Jawa Timur',
        'kode_pos' => '12345',
        'person_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('identity:backfill-persons')->assertExitCode(0);

    $guru = Guru::find($guruId);
    expect($guru->person_id)->not->toBeNull();

    $person = Person::withoutGlobalScopes()->find($guru->person_id);
    expect($person->yayasan_id)->toBe($yayasan->id);
    expect($person->nama_lengkap)->toBe('Budi Santoso');
    expect($person->nik_hash)->toBe(hash('sha256', '1111111111111111'));
    expect($person->kewarganegaraan)->toBe('WNI');
    expect($person->alamat_jalan)->toBe('Jl. Merdeka No. 1');
    expect($person->rt)->toBe('001');
    expect($person->rw)->toBe('002');
    expect($person->desa_kelurahan)->toBe('Kelurahan Maju');
    expect($person->kecamatan)->toBe('Kecamatan Sejahtera');
    expect($person->kabupaten_kota)->toBe('Kabupaten Makmur');
    expect($person->provinsi)->toBe('Jawa Timur');
    expect($person->kode_pos)->toBe('12345');
});

it('backfills karyawan using its own yayasan_id when lembaga_id is null (pool karyawan)', function () {
    $yayasan = Yayasan::factory()->create();
    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenisKaryawan = JenisKaryawanMaster::factory()->create();

    $karyawanId = DB::table('karyawan')->insertGetId([
        'user_id' => $user->id,
        'yayasan_id' => $yayasan->id,
        'lembaga_id' => null,
        'jenis_karyawan_id' => $jenisKaryawan->id,
        'status_aktif' => 'aktif',
        'nik' => '2222222222222222',
        'nama' => 'Staff Pool',
        'person_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('identity:backfill-persons')->assertExitCode(0);

    $karyawan = Karyawan::find($karyawanId);
    $person = Person::withoutGlobalScopes()->find($karyawan->person_id);
    expect($person->yayasan_id)->toBe($yayasan->id);
});

it('backfills orang_tua by deriving yayasan_id from its linked siswa', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create();

    $ortuId = DB::table('orang_tua')->insertGetId([
        'user_id' => $user->id,
        'nama_lengkap' => 'Ayah Siswa',
        'nik' => '3333333333333331',
        'person_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $siswa->orangTua()->attach($ortuId, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $this->artisan('identity:backfill-persons')->assertExitCode(0);

    $ortu = OrangTua::find($ortuId);
    $person = Person::withoutGlobalScopes()->find($ortu->person_id);
    expect($person->yayasan_id)->toBe($yayasan->id);
});

it('backfills siswa and calon_murid rows', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $siswaId = DB::table('siswa')->insertGetId([
        'lembaga_id' => $lembaga->id,
        'nama_lengkap' => 'Murid Satu',
        'agama' => 'Islam',
        'status' => 'aktif',
        'sumber_data' => 'manual',
        'nis' => '12345',
        'person_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $calonMuridId = DB::table('calon_murid')->insertGetId([
        'yayasan_id' => $yayasan->id,
        'nama_lengkap' => 'Calon Satu',
        'nik' => '9999999999999999',
        'person_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('identity:backfill-persons')->assertExitCode(0);

    $siswa = Siswa::find($siswaId);
    $calonMurid = CalonMurid::find($calonMuridId);
    expect($siswa->person_id)->not->toBeNull();
    expect($calonMurid->person_id)->not->toBeNull();

    $siswaPerson = Person::withoutGlobalScopes()->find($siswa->person_id);
    expect($siswaPerson->agama)->toBe('Islam');
});

it('is idempotent: running twice does not create duplicate Person rows', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    DB::table('guru')->insertGetId([
        'user_id' => User::factory()->create()->id,
        'lembaga_id' => $lembaga->id,
        'nik' => '5555555555555555',
        'nama' => 'Guru Idempotent',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'PNS',
        'status_aktif' => 'aktif',
        'person_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('identity:backfill-persons')->assertExitCode(0);
    $countAfterFirst = Person::withoutGlobalScopes()->count();

    $this->artisan('identity:backfill-persons')->assertExitCode(0);
    expect(Person::withoutGlobalScopes()->count())->toBe($countAfterFirst);
});

it('reports a NIK collision within one yayasan instead of auto-merging', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenisKaryawan = JenisKaryawanMaster::factory()->create();

    $guruId = DB::table('guru')->insertGetId([
        'user_id' => User::factory()->create()->id,
        'lembaga_id' => $lembaga->id,
        'nik' => '7777777777777777',
        'nama' => 'Guru Double',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'PNS',
        'status_aktif' => 'aktif',
        'person_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $karyawanId = DB::table('karyawan')->insertGetId([
        'user_id' => User::factory()->create()->id,
        'yayasan_id' => $yayasan->id,
        'lembaga_id' => $lembaga->id,
        'jenis_karyawan_id' => $jenisKaryawan->id,
        'status_aktif' => 'aktif',
        'nik' => '7777777777777777',
        'nama' => 'Karyawan Double',
        'person_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('identity:backfill-persons')
        ->expectsOutputToContain('NIK collision within yayasan_id='.$yayasan->id.': same NIK shared across [guru, karyawan]')
        ->assertExitCode(0);

    expect(Guru::find($guruId)->person_id)->not->toBeNull();
    expect(Karyawan::find($karyawanId)->person_id)->not->toBeNull();
});

it('reports a NIK collision between two distinct orang_tua rows sharing one NIK', function () {
    DB::statement('ALTER TABLE orang_tua DROP FOREIGN KEY orang_tua_person_id_foreign');
    DB::statement('ALTER TABLE orang_tua DROP INDEX uq_orang_tua_person');

    try {
        $yayasan = Yayasan::factory()->create();
        $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

        $siswaSatu = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
        $ortuSatuId = DB::table('orang_tua')->insertGetId([
            'user_id' => User::factory()->create()->id,
            'nama_lengkap' => 'Ortu Satu',
            'nik' => '8888888888888888',
            'person_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $siswaSatu->orangTua()->attach($ortuSatuId, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

        $siswaDua = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
        $ortuDuaId = DB::table('orang_tua')->insertGetId([
            'user_id' => User::factory()->create()->id,
            'nama_lengkap' => 'Ortu Dua',
            'nik' => '8888888888888888',
            'person_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $siswaDua->orangTua()->attach($ortuDuaId, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

        $this->artisan('identity:backfill-persons')
            ->expectsOutputToContain('NIK collision within yayasan_id='.$yayasan->id.': same NIK shared across [orang_tua]')
            ->assertExitCode(0);

        expect(OrangTua::find($ortuSatuId)->person_id)->not->toBeNull();
        expect(OrangTua::find($ortuDuaId)->person_id)->not->toBeNull();
        expect(OrangTua::find($ortuSatuId)->person_id)->toBe(OrangTua::find($ortuDuaId)->person_id);
    } finally {
        DB::table('orang_tua')->where('id', $ortuDuaId)->delete();
        DB::statement('ALTER TABLE orang_tua ADD UNIQUE KEY uq_orang_tua_person (person_id)');
        DB::statement('ALTER TABLE orang_tua ADD CONSTRAINT orang_tua_person_id_foreign FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE RESTRICT');
    }
});
