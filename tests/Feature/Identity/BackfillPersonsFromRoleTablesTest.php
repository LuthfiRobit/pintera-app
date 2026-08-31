<?php

use App\Domains\Identity\Models\Person;
use App\Models\CalonMurid;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\Yayasan;

it('backfills a Person for each guru row and links person_id', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create([
        'lembaga_id' => $lembaga->id,
        'nik' => '1111111111111111',
        'nama' => 'Budi Santoso',
        'kewarganegaraan' => 'WNI',
        'alamat_jalan' => 'Jl. Merdeka No. 1',
        'rt' => '001',
        'rw' => '002',
        'desa_kelurahan' => 'Kelurahan Maju',
        'kecamatan' => 'Kecamatan Sejahtera',
        'kabupaten_kota' => 'Kabupaten Makmur',
        'provinsi' => 'Jawa Timur',
        'kode_pos' => '12345',
    ]);

    $this->artisan('identity:backfill-persons')->assertExitCode(0);

    $guru->refresh();
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
    $karyawan = Karyawan::factory()->create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'nik' => '2222222222222222']);

    $this->artisan('identity:backfill-persons')->assertExitCode(0);

    $karyawan->refresh();
    $person = Person::withoutGlobalScopes()->find($karyawan->person_id);
    expect($person->yayasan_id)->toBe($yayasan->id);
});

it('backfills orang_tua by deriving yayasan_id from its linked siswa', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $ortu = OrangTua::factory()->create();
    $siswa->orangTua()->attach($ortu->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $this->artisan('identity:backfill-persons')->assertExitCode(0);

    $ortu->refresh();
    $person = Person::withoutGlobalScopes()->find($ortu->person_id);
    expect($person->yayasan_id)->toBe($yayasan->id);
});

it('backfills siswa and calon_murid rows', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'agama' => 'Islam']);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id]);

    $this->artisan('identity:backfill-persons')->assertExitCode(0);

    expect($siswa->refresh()->person_id)->not->toBeNull();
    expect($calonMurid->refresh()->person_id)->not->toBeNull();

    $siswaPerson = Person::withoutGlobalScopes()->find($siswa->person_id);
    expect($siswaPerson->agama)->toBe('Islam');
});

it('is idempotent: running twice does not create duplicate Person rows', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->artisan('identity:backfill-persons')->assertExitCode(0);
    $countAfterFirst = Person::withoutGlobalScopes()->count();

    $this->artisan('identity:backfill-persons')->assertExitCode(0);
    expect(Person::withoutGlobalScopes()->count())->toBe($countAfterFirst);
});

it('reports a NIK collision within one yayasan instead of auto-merging', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nik' => '3333333333333333']);
    Karyawan::factory()->create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nik' => '3333333333333333']);

    $this->artisan('identity:backfill-persons')
        ->expectsOutputToContain('NIK collision within yayasan_id='.$yayasan->id.': same NIK shared across [guru, karyawan]')
        ->assertExitCode(0);

    // Both rows still get linked to Person rows -- collision is reported, not blocked or auto-merged
    expect(Guru::first()->person_id)->not->toBeNull();
    expect(Karyawan::first()->person_id)->not->toBeNull();
});
