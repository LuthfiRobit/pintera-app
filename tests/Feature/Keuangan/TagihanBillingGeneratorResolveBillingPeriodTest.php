<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\JenisTagihanSasaranMatcher;
use App\Domains\Keuangan\Services\TagihanBillingGenerator;
use App\Domains\Keuangan\Services\TagihanNominalResolver;
use App\Models\Siswa;
use App\Services\Finance\NotificationDispatcher;
use Illuminate\Support\Carbon;

function buatGeneratorBillingPeriod(): TagihanBillingGenerator
{
    $matcher = new JenisTagihanSasaranMatcher();

    return new TagihanBillingGenerator($matcher, new TagihanNominalResolver($matcher), app(NotificationDispatcher::class));
}

it('sets billing_period to null for Mode=Manual regardless of Tipe', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['mode' => 'manual', 'tipe' => 'harian', 'default_amount' => 100000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorBillingPeriod()->generateForSiswa($siswa, $jenisTagihan, 'manual');

    expect(Tagihan::first()->billing_period)->toBeNull();
});

it('formats billing_period as Y-m-d for Tipe Harian', function () {
    Carbon::setTestNow('2026-09-15');
    $jenisTagihan = JenisTagihan::factory()->create(['mode' => 'otomatis', 'tipe' => 'harian', 'default_amount' => 100000, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2026-01-01']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorBillingPeriod()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->billing_period)->toBe('2026-09-15');
    Carbon::setTestNow();
});

it('formats billing_period as ISO week for Tipe Mingguan using a normal mid-year date', function () {
    Carbon::setTestNow('2026-08-24');
    $jenisTagihan = JenisTagihan::factory()->create(['mode' => 'otomatis', 'tipe' => 'mingguan', 'default_amount' => 100000, 'hari_generate' => 1, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2026-01-01']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorBillingPeriod()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->billing_period)->toBe('2026-W35');
    Carbon::setTestNow();
});

it('formats billing_period as ISO week correctly at the year-boundary edge case 2027-01-01 (must be 2026-W53, not 2027-W01)', function () {
    Carbon::setTestNow('2027-01-01');
    $jenisTagihan = JenisTagihan::factory()->create(['mode' => 'otomatis', 'tipe' => 'mingguan', 'default_amount' => 100000, 'hari_generate' => 5, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2026-01-01']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorBillingPeriod()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->billing_period)->toBe('2026-W53');
    Carbon::setTestNow();
});

it('formats billing_period as ISO week correctly at the year-boundary edge case 2025-12-29 (must be 2026-W01, not 2025-W01)', function () {
    Carbon::setTestNow('2025-12-29');
    $jenisTagihan = JenisTagihan::factory()->create(['mode' => 'otomatis', 'tipe' => 'mingguan', 'default_amount' => 100000, 'hari_generate' => 1, 'offset_hari_jatuh_tempo' => 1, 'tanggal_mulai' => '2025-01-01']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorBillingPeriod()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->billing_period)->toBe('2026-W01');
    Carbon::setTestNow();
});

it('formats billing_period as Y-m for Tipe Bulanan, unchanged from current behavior', function () {
    Carbon::setTestNow('2026-09-15');
    $jenisTagihan = JenisTagihan::factory()->create(['mode' => 'otomatis', 'tipe' => 'bulanan', 'default_amount' => 100000, 'tanggal_generate' => 15, 'hari_jatuh_tempo' => 10, 'tanggal_mulai' => '2026-01-01']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorBillingPeriod()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->billing_period)->toBe('2026-09');
    Carbon::setTestNow();
});

it('formats billing_period as Y for Tipe Tahunan', function () {
    Carbon::setTestNow('2026-07-01');
    $jenisTagihan = JenisTagihan::factory()->create(['mode' => 'otomatis', 'tipe' => 'tahunan', 'default_amount' => 100000, 'bulan_generate' => 7, 'tanggal_generate' => 1, 'hari_jatuh_tempo' => 20, 'tanggal_mulai' => '2026-01-01']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorBillingPeriod()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->billing_period)->toBe('2026');
    Carbon::setTestNow();
});
