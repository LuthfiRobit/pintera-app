<?php

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('encrypts nik and keeps a deterministic hash for uniqueness', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $guru = Guru::factory()->create([
        'user_id' => $user->id,
        'lembaga_id' => $lembaga->id,
        'nik' => '3201234567890001',
        'nama' => 'Budi Santoso',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $rawPerson = DB::table('persons')->where('id', $guru->person_id)->first();

    expect($rawPerson->nik)->not->toBe('3201234567890001');
    expect($guru->person->nik_hash)->toBe(hash('sha256', '3201234567890001'));
    expect($guru->fresh()->nik)->toBe('3201234567890001');
});

it('rejects a duplicate nik', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    Guru::factory()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id,
        'nik' => '3201234567890002',
        'nama' => 'Guru Satu',
        'jenis_kelamin' => 'P',
        'jenis_ptk' => 'guru_mapel',
        'status_kepegawaian' => 'Honorer',
    ]);

    expect(fn () => Guru::factory()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id,
        'nik' => '3201234567890002',
        'nama' => 'Guru Dua',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_mapel',
        'status_kepegawaian' => 'Honorer',
    ]))->toThrow(QueryException::class);
});
