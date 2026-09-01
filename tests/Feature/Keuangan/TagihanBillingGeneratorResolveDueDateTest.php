<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\JenisTagihanSasaranMatcher;
use App\Domains\Keuangan\Services\TagihanBillingGenerator;
use App\Domains\Keuangan\Services\TagihanNominalResolver;
use App\Models\Siswa;
use App\Services\Finance\NotificationDispatcher;
use Illuminate\Support\Carbon;

function buatGeneratorDueDate(): TagihanBillingGenerator
{
    $matcher = new JenisTagihanSasaranMatcher();

    return new TagihanBillingGenerator($matcher, new TagihanNominalResolver($matcher), app(NotificationDispatcher::class));
}

it('resolves due date to null for Tipe Sekali', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['mode' => 'manual', 'tipe' => 'sekali', 'default_amount' => 100000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorDueDate()->generateForSiswa($siswa, $jenisTagihan, 'manual');

    expect(Tagihan::first()->jatuh_tempo)->toBeNull();
});

it('resolves due date as generate-date-plus-offset for Tipe Harian', function () {
    Carbon::setTestNow('2026-09-15');
    $jenisTagihan = JenisTagihan::factory()->create([
        'mode' => 'otomatis', 'tipe' => 'harian', 'default_amount' => 100000,
        'offset_hari_jatuh_tempo' => 3, 'tanggal_mulai' => '2026-09-01',
    ]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorDueDate()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->jatuh_tempo->toDateString())->toBe('2026-09-18');
    Carbon::setTestNow();
});

it('resolves due date as generate-date-plus-offset for Tipe Mingguan', function () {
    Carbon::setTestNow('2026-09-15');
    $jenisTagihan = JenisTagihan::factory()->create([
        'mode' => 'otomatis', 'tipe' => 'mingguan', 'default_amount' => 100000,
        'hari_generate' => 2, 'offset_hari_jatuh_tempo' => 5, 'tanggal_mulai' => '2026-09-01',
    ]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorDueDate()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->jatuh_tempo->toDateString())->toBe('2026-09-20');
    Carbon::setTestNow();
});

it('resolves due date as an absolute day-of-month for Tipe Bulanan, unchanged from current behavior', function () {
    Carbon::setTestNow('2026-09-15');
    $jenisTagihan = JenisTagihan::factory()->create([
        'mode' => 'otomatis', 'tipe' => 'bulanan', 'default_amount' => 100000,
        'tanggal_generate' => 15, 'hari_jatuh_tempo' => 10, 'tanggal_mulai' => '2026-01-01',
    ]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorDueDate()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->jatuh_tempo->toDateString())->toBe('2026-09-10');
    Carbon::setTestNow();
});

it('resolves due date to an absolute day within bulan_generate for Tipe Tahunan', function () {
    Carbon::setTestNow('2026-07-01');
    $jenisTagihan = JenisTagihan::factory()->create([
        'mode' => 'otomatis', 'tipe' => 'tahunan', 'default_amount' => 100000,
        'bulan_generate' => 7, 'tanggal_generate' => 1, 'hari_jatuh_tempo' => 20, 'tanggal_mulai' => '2026-01-01',
    ]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    buatGeneratorDueDate()->generateForSiswa($siswa, $jenisTagihan, 'cron');

    expect(Tagihan::first()->jatuh_tempo->toDateString())->toBe('2026-07-20');
    Carbon::setTestNow();
});
