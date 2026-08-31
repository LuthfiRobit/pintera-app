<?php

use App\Models\CalonMurid;
use App\Models\Yayasan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('encrypts nik and no_kk and keeps a deterministic nik_hash for uniqueness', function () {
    $yayasan = Yayasan::factory()->create();

    $calonMurid = CalonMurid::factory()->create([
        'yayasan_id' => $yayasan->id,
        'nik' => '3201234567890123',
        'no_kk' => '3201234567890000',
        'nisn' => '0012345678',
        'nama_lengkap' => 'Ahmad Fauzan',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2015-03-10',
        'agama' => 'Islam',
        'golongan_darah' => 'O',
        'no_telepon' => '081234567890',
        'email_kontak' => 'wali@example.test',
    ]);

    expect($calonMurid->nik)->toBe('3201234567890123');
    expect($calonMurid->person->nik_hash)->toBe(hash('sha256', '3201234567890123'));

    $raw = DB::table('calon_murid')->where('id', $calonMurid->id)->first();
    expect($raw->nik)->not->toBe('3201234567890123');
    expect($raw->no_kk)->not->toBe('3201234567890000');
});

it('rejects a duplicate nik_hash', function () {
    $yayasan = Yayasan::factory()->create();

    CalonMurid::factory()->create([
        'yayasan_id' => $yayasan->id,
        'nik' => '3201234567890123',
        'nama_lengkap' => 'Ahmad Fauzan',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2015-03-10',
        'agama' => 'Islam',
    ]);

    expect(fn () => CalonMurid::factory()->create([
        'yayasan_id' => $yayasan->id,
        'nik' => '3201234567890123',
        'nama_lengkap' => 'Nama Lain',
        'jenis_kelamin' => 'P',
        'tempat_lahir' => 'Jakarta',
        'tanggal_lahir' => '2015-05-20',
        'agama' => 'Islam',
    ]))->toThrow(QueryException::class);
});

it('finds a calon murid by plaintext nik via findByNik', function () {
    $yayasan = Yayasan::factory()->create();
    $calonMurid = CalonMurid::factory()->create([
        'yayasan_id' => $yayasan->id,
        'nik' => '3201234567890123',
        'nama_lengkap' => 'Ahmad Fauzan',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2015-03-10',
        'agama' => 'Islam',
    ]);

    $found = CalonMurid::findByNik('3201234567890123');
    expect($found)->not->toBeNull();
    expect($found->id)->toBe($calonMurid->id);

    expect(CalonMurid::findByNik('9999999999999999'))->toBeNull();
});
