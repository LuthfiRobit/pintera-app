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
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nik' => '1111111111111111', 'nama' => 'Budi Santoso']);

    $this->artisan('identity:backfill-persons')->assertExitCode(0);

    $guru->refresh();
    expect($guru->person_id)->not->toBeNull();

    $person = Person::withoutGlobalScopes()->find($guru->person_id);
    expect($person->yayasan_id)->toBe($yayasan->id);
    expect($person->nama_lengkap)->toBe('Budi Santoso');
    expect($person->nik_hash)->toBe(hash('sha256', '1111111111111111'));
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
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id]);

    $this->artisan('identity:backfill-persons')->assertExitCode(0);

    expect($siswa->refresh()->person_id)->not->toBeNull();
    expect($calonMurid->refresh()->person_id)->not->toBeNull();
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
        ->expectsOutputToContain('NIK collision')
        ->assertExitCode(0);

    // Both rows still get linked to Person rows -- collision is reported, not blocked or auto-merged
    expect(Guru::first()->person_id)->not->toBeNull();
    expect(Karyawan::first()->person_id)->not->toBeNull();
});
