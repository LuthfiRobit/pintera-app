<?php
// tests/Feature/Keuangan/TagihanBillingGeneratorTest.php

use App\Models\JenisTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Services\JenisTagihanSasaranMatcher;
use App\Services\TagihanBillingGenerator;
use App\Services\TagihanNominalResolver;

function buatGenerator(): TagihanBillingGenerator
{
    $matcher = new JenisTagihanSasaranMatcher();

    return new TagihanBillingGenerator($matcher, new TagihanNominalResolver($matcher));
}

it('generates a belum_bayar tagihan for every matching siswa and logs a success job', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 200000, 'mode' => 'otomatis', 'hari_jatuh_tempo' => 10]);
    $siswaSatu = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    $siswaDua = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    $log = buatGenerator()->generate($jenisTagihan, 'cron');

    expect($log->status)->toBe('success');
    expect($log->bills_generated)->toBe(2);
    expect($log->trigger_type)->toBe('cron');
    expect($log->period)->toBe(now()->format('Y-m'));

    $tagihanSatu = Tagihan::where('tagihable_id', $siswaSatu->id)->where('tagihable_type', Siswa::class)->first();
    expect($tagihanSatu)->not->toBeNull();
    expect((float) $tagihanSatu->net_amount)->toBe(200000.0);
    expect($tagihanSatu->status)->toBe('belum_bayar');
    expect($tagihanSatu->jatuh_tempo->format('Y-m-d'))->toBe(now()->startOfMonth()->addDays(9)->format('Y-m-d'));

    expect(Tagihan::where('tagihable_id', $siswaDua->id)->exists())->toBeTrue();
});

it('does not create a duplicate tagihan for the same siswa and billing_period on a second run', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 200000, 'mode' => 'otomatis']);
    Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    $generator = buatGenerator();
    $generator->generate($jenisTagihan, 'cron');
    $secondLog = $generator->generate($jenisTagihan, 'cron');

    expect($secondLog->bills_generated)->toBe(0);
    expect(Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)->count())->toBe(1);
});

it('sets billing_period to null for a manual-mode jenis_tagihan regardless of trigger', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 150000, 'mode' => 'manual']);
    Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    $log = buatGenerator()->generate($jenisTagihan, 'manual');

    expect($log->period)->toBeNull();
    expect(Tagihan::first()->billing_period)->toBeNull();
});

it('applies the discount from TagihanNominalResolver to net_amount', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 500000, 'mode' => 'otomatis']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    $kategori = \App\Models\KategoriKeringanan::create(['lembaga_id' => $jenisTagihan->lembaga_id, 'nama' => 'Anak Pegawai']);
    \App\Models\JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 100000]);
    \App\Models\SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    buatGenerator()->generate($jenisTagihan, 'cron');

    $tagihan = Tagihan::where('tagihable_id', $siswa->id)->first();
    expect((float) $tagihan->total_tagihan)->toBe(500000.0);
    expect((float) $tagihan->discount_amount)->toBe(100000.0);
    expect((float) $tagihan->net_amount)->toBe(400000.0);
});

it('generateForSiswa returns false and creates nothing when a tagihan for that period already exists', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 200000, 'mode' => 'otomatis']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    $generator = buatGenerator();

    expect($generator->generateForSiswa($siswa, $jenisTagihan, 'event'))->toBeTrue();
    expect($generator->generateForSiswa($siswa, $jenisTagihan, 'event'))->toBeFalse();
    expect(Tagihan::where('tagihable_id', $siswa->id)->count())->toBe(1);
});

it('generateForSiswaViaEvent logs a single-siswa job with the given trigger_event', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 200000, 'mode' => 'otomatis']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    $log = buatGenerator()->generateForSiswaViaEvent($siswa, $jenisTagihan, 'StudentCreated');

    expect($log->trigger_type)->toBe('event');
    expect($log->trigger_event)->toBe('StudentCreated');
    expect($log->bills_generated)->toBe(1);
    expect($log->status)->toBe('success');
});
