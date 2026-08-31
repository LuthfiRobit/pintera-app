<?php

use App\Domains\Sdm\Models\JabatanTambahanMaster;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\JabatanTambahanMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds the jabatan tambahan master list from Permendikdasmen 11/2025', function () {
    (new JabatanTambahanMasterSeeder)->run();

    expect(JabatanTambahanMaster::count())->toBe(16);
    expect(JabatanTambahanMaster::where('nama', 'Guru Wali')->where('kelompok', 'fungsional')->exists())->toBeTrue();
    expect(JabatanTambahanMaster::where('nama', 'Koordinator BK')->where('kelompok', 'struktural')->exists())->toBeTrue();
    expect(JabatanTambahanMaster::where('nama', 'Koordinator/Anggota TPPK')->where('kelompok', 'fungsional')->exists())->toBeTrue();
    expect(JabatanTambahanMaster::where('nama', 'Guru Pendidikan Khusus (GPK) / Pembimbing Khusus')->where('kelompok', 'fungsional')->exists())->toBeTrue();
});

it('assigns a jabatan tambahan to a guru with a period and SK number', function () {
    (new JabatanTambahanMasterSeeder)->run();

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id,
        'nik' => '3201234567890111',
        'nama' => 'Guru Wali Uji',
        'jenis_kelamin' => 'P',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $jabatan = JabatanTambahanMaster::where('nama', 'Guru Wali')->first();

    $guru->jabatanTambahan()->attach($jabatan->id, [
        'mulai_periode' => '2026-07-01',
        'no_sk' => 'SK/001/2026',
    ]);

    expect($guru->jabatanTambahan()->first()->pivot->no_sk)->toBe('SK/001/2026');
});
