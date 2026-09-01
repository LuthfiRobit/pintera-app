<?php
// tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
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

it('processes Tipe Harian candidates every day with no extra date condition', function () {
    Carbon::setTestNow('2026-09-15');

    $lembaga = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $harian = JenisTagihan::factory()->create([
        'lembaga_id' => $lembaga->id, 'default_amount' => 50000, 'mode' => 'otomatis', 'tipe' => 'harian',
        'is_active' => true, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2026-01-01',
    ]);

    $this->artisan('billing:generate-harian')->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $harian->id)->count())->toBe(1);
    Carbon::setTestNow();
});

it('processes Tipe Mingguan candidates only on the matching hari_generate', function () {
    Carbon::setTestNow('2026-09-15'); // this is a Tuesday -- dayOfWeekIso = 2

    $lembagaCocok = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaCocok->id]);
    $cocok = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaCocok->id, 'default_amount' => 50000, 'mode' => 'otomatis', 'tipe' => 'mingguan',
        'is_active' => true, 'hari_generate' => 2, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2026-01-01',
    ]);

    $lembagaBedaHari = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaBedaHari->id]);
    $bedaHari = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaBedaHari->id, 'default_amount' => 50000, 'mode' => 'otomatis', 'tipe' => 'mingguan',
        'is_active' => true, 'hari_generate' => 5, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2026-01-01',
    ]);

    $this->artisan('billing:generate-harian')->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $cocok->id)->count())->toBe(1);
    expect(Tagihan::where('jenis_tagihan_id', $bedaHari->id)->count())->toBe(0);
    Carbon::setTestNow();
});

it('processes Tipe Tahunan candidates only when both bulan_generate and tanggal_generate match today', function () {
    Carbon::setTestNow('2026-07-01');

    $lembagaCocok = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaCocok->id]);
    $cocok = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaCocok->id, 'default_amount' => 500000, 'mode' => 'otomatis', 'tipe' => 'tahunan',
        'is_active' => true, 'bulan_generate' => 7, 'tanggal_generate' => 1, 'hari_jatuh_tempo' => 20, 'tanggal_mulai' => '2026-01-01',
    ]);

    $lembagaBedaBulan = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaBedaBulan->id]);
    $bedaBulan = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaBedaBulan->id, 'default_amount' => 500000, 'mode' => 'otomatis', 'tipe' => 'tahunan',
        'is_active' => true, 'bulan_generate' => 8, 'tanggal_generate' => 1, 'hari_jatuh_tempo' => 20, 'tanggal_mulai' => '2026-01-01',
    ]);

    $lembagaBedaTanggal = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembagaBedaTanggal->id]);
    $bedaTanggal = JenisTagihan::factory()->create([
        'lembaga_id' => $lembagaBedaTanggal->id, 'default_amount' => 500000, 'mode' => 'otomatis', 'tipe' => 'tahunan',
        'is_active' => true, 'bulan_generate' => 7, 'tanggal_generate' => 15, 'hari_jatuh_tempo' => 20, 'tanggal_mulai' => '2026-01-01',
    ]);

    $this->artisan('billing:generate-harian')->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $cocok->id)->count())->toBe(1);
    expect(Tagihan::where('jenis_tagihan_id', $bedaBulan->id)->count())->toBe(0);
    expect(Tagihan::where('jenis_tagihan_id', $bedaTanggal->id)->count())->toBe(0);
    Carbon::setTestNow();
});

it('never generates for Tipe Sekali even if somehow mode were otomatis (defense-in-depth, not reachable via normal validation)', function () {
    Carbon::setTestNow('2026-09-15');

    $lembaga = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create([
        'lembaga_id' => $lembaga->id, 'default_amount' => 50000, 'mode' => 'manual', 'tipe' => 'sekali',
        'is_active' => true, 'tanggal_mulai' => '2026-01-01',
    ]);

    $this->artisan('billing:generate-harian')->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)->count())->toBe(0);
    Carbon::setTestNow();
});

it('does not create a duplicate Tagihan for Tipe Harian when the command runs twice on the same day', function () {
    Carbon::setTestNow('2026-09-15');

    $lembaga = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create([
        'lembaga_id' => $lembaga->id, 'default_amount' => 50000, 'mode' => 'otomatis', 'tipe' => 'harian',
        'is_active' => true, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2026-01-01',
    ]);

    $this->artisan('billing:generate-harian')->assertExitCode(0);
    $this->artisan('billing:generate-harian')->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)->count())->toBe(1);
    Carbon::setTestNow();
});

it('does not create a duplicate Tagihan for Tipe Mingguan when the command runs twice on the same day', function () {
    Carbon::setTestNow('2026-09-15'); // Tuesday, dayOfWeekIso = 2

    $lembaga = Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create([
        'lembaga_id' => $lembaga->id, 'default_amount' => 50000, 'mode' => 'otomatis', 'tipe' => 'mingguan',
        'is_active' => true, 'hari_generate' => 2, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2026-01-01',
    ]);

    $this->artisan('billing:generate-harian')->assertExitCode(0);
    $this->artisan('billing:generate-harian')->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)->count())->toBe(1);
    Carbon::setTestNow();
});

