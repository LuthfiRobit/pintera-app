<?php
// tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php

use App\Models\JenisTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use Illuminate\Support\Carbon;

it('processes only jenis_tagihan whose tanggal_generate matches today and is within the active window', function () {
    Carbon::setTestNow('2026-09-15');

    $cocok = JenisTagihan::factory()->create([
        'default_amount' => 200000,
        'mode' => 'otomatis',
        'is_active' => true,
        'tanggal_generate' => 15,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => null,
    ]);
    Siswa::factory()->create(['lembaga_id' => $cocok->lembaga_id]);

    $bedaTanggal = JenisTagihan::factory()->create([
        'default_amount' => 100000,
        'mode' => 'otomatis',
        'is_active' => true,
        'tanggal_generate' => 1,
        'tanggal_mulai' => '2026-01-01',
    ]);
    Siswa::factory()->create(['lembaga_id' => $bedaTanggal->lembaga_id]);

    $sudahSelesai = JenisTagihan::factory()->create([
        'default_amount' => 100000,
        'mode' => 'otomatis',
        'is_active' => true,
        'tanggal_generate' => 15,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-06-30',
    ]);
    Siswa::factory()->create(['lembaga_id' => $sudahSelesai->lembaga_id]);

    $tidakAktif = JenisTagihan::factory()->create([
        'default_amount' => 100000,
        'mode' => 'otomatis',
        'is_active' => false,
        'tanggal_generate' => 15,
        'tanggal_mulai' => '2026-01-01',
    ]);
    Siswa::factory()->create(['lembaga_id' => $tidakAktif->lembaga_id]);

    $this->artisan('billing:generate-harian')
        ->expectsOutputToContain('1 jenis tagihan diproses')
        ->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $cocok->id)->count())->toBe(1);
    expect(Tagihan::where('jenis_tagihan_id', $bedaTanggal->id)->count())->toBe(0);
    expect(Tagihan::where('jenis_tagihan_id', $sudahSelesai->id)->count())->toBe(0);
    expect(Tagihan::where('jenis_tagihan_id', $tidakAktif->id)->count())->toBe(0);

    Carbon::setTestNow();
});
