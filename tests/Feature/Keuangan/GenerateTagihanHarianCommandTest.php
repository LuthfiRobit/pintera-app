<?php
// tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\Tagihan;
use Illuminate\Support\Carbon;

it('processes only jenis_tagihan whose tanggal_generate matches today and is within the active window', function () {
    Carbon::setTestNow('2026-09-15');

    $lembagaCocok = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaCocok->id]);
    $cocok = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaCocok->id,
        'default_amount' => 200000,
        'mode' => 'otomatis',
        'is_active' => true,
        'tanggal_generate' => 15,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => null,
    ]);

    $lembagaBedaTanggal = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaBedaTanggal->id]);
    $bedaTanggal = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaBedaTanggal->id,
        'default_amount' => 100000,
        'mode' => 'otomatis',
        'is_active' => true,
        'tanggal_generate' => 1,
        'tanggal_mulai' => '2026-01-01',
    ]);

    $lembagaSudahSelesai = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaSudahSelesai->id]);
    $sudahSelesai = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaSudahSelesai->id,
        'default_amount' => 100000,
        'mode' => 'otomatis',
        'is_active' => true,
        'tanggal_generate' => 15,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-06-30',
    ]);

    $lembagaTidakAktif = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaTidakAktif->id]);
    $tidakAktif = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaTidakAktif->id,
        'default_amount' => 100000,
        'mode' => 'otomatis',
        'is_active' => false,
        'tanggal_generate' => 15,
        'tanggal_mulai' => '2026-01-01',
    ]);

    $this->artisan('billing:generate-harian')
        ->expectsOutputToContain('1 jenis tagihan diproses')
        ->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $cocok->id)->count())->toBe(1);
    expect(Tagihan::where('jenis_tagihan_id', $bedaTanggal->id)->count())->toBe(0);
    expect(Tagihan::where('jenis_tagihan_id', $sudahSelesai->id)->count())->toBe(0);
    expect(Tagihan::where('jenis_tagihan_id', $tidakAktif->id)->count())->toBe(0);

    Carbon::setTestNow();
});

it('does not abort the whole run when one jenis_tagihan throws — others still get processed', function () {
    Carbon::setTestNow('2026-09-15');

    $lembagaBaik = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaBaik->id]);
    $baik = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaBaik->id, 'default_amount' => 200000, 'mode' => 'otomatis',
        'is_active' => true, 'tanggal_generate' => 15, 'tanggal_mulai' => '2026-01-01',
    ]);

    // Simulasi jenis_tagihan yang akan throw meski lolos filter mode=otomatis
    // (mis. data korup atau constraint yang berubah di masa depan) — dibuat kategori
    // PPDB secara paksa lewat query builder karena factory/validasi normal tidak
    // mengizinkan kombinasi mode=otomatis + kategori PPDB.
    $lembagaThrow = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaThrow->id]);
    $throw = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaThrow->id, 'default_amount' => 100000, 'mode' => 'otomatis',
        'is_active' => true, 'tanggal_generate' => 15, 'tanggal_mulai' => '2026-01-01',
    ]);
    JenisTagihan::withoutEvents(fn () => $throw->update(['kategori' => 'pendaftaran']));

    $this->artisan('billing:generate-harian')->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $baik->id)->count())->toBe(1);
    expect(Tagihan::where('jenis_tagihan_id', $throw->id)->count())->toBe(0);

    Carbon::setTestNow();
});
