<?php
// tests/Feature/Keuangan/TagihanBillingGeneratorTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Services\Finance\NotificationDispatcher;
use App\Domains\Keuangan\Services\JenisTagihanSasaranMatcher;
use App\Services\TagihanBillingGenerator;
use App\Domains\Keuangan\Services\TagihanNominalResolver;

function buatGenerator(): TagihanBillingGenerator
{
    $matcher = new JenisTagihanSasaranMatcher();

    return new TagihanBillingGenerator($matcher, new TagihanNominalResolver($matcher), app(NotificationDispatcher::class));
}

it('generates a belum_bayar tagihan for every matching siswa and logs a success job', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $siswaSatu = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaDua = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 200000, 'mode' => 'otomatis', 'hari_jatuh_tempo' => 10]);

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
    $lembaga = \App\Models\Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 500000, 'mode' => 'otomatis']);
    $kategori = \App\Models\KategoriKeringanan::create(['lembaga_id' => $jenisTagihan->lembaga_id, 'nama' => 'Anak Pegawai']);
    \App\Domains\Keuangan\Models\JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 100000]);
    \App\Models\SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    buatGenerator()->generate($jenisTagihan, 'cron');

    $tagihan = Tagihan::where('tagihable_id', $siswa->id)->first();
    expect((float) $tagihan->total_tagihan)->toBe(500000.0);
    expect((float) $tagihan->discount_amount)->toBe(100000.0);
    expect((float) $tagihan->net_amount)->toBe(400000.0);
});

it('generateForSiswa returns false and creates nothing when a tagihan for that period already exists', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 200000, 'mode' => 'otomatis']);
    $generator = buatGenerator();

    expect($generator->generateForSiswa($siswa, $jenisTagihan, 'event'))->toBeTrue();
    expect($generator->generateForSiswa($siswa, $jenisTagihan, 'event'))->toBeFalse();
    expect(Tagihan::where('tagihable_id', $siswa->id)->count())->toBe(1);
});

it('generateForSiswaViaEvent logs a single-siswa job with the given trigger_event', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 200000, 'mode' => 'otomatis']);

    $log = buatGenerator()->generateForSiswaViaEvent($siswa, $jenisTagihan, 'StudentCreated');

    expect($log->trigger_type)->toBe('event');
    expect($log->trigger_event)->toBe('StudentCreated');
    expect($log->bills_generated)->toBe(1);
    expect($log->status)->toBe('success');
});

it('does not abort the batch when one siswa throws — other siswa still get billed and the log is partial', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $siswaGagal = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaBerhasil = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 200000, 'mode' => 'otomatis']);

    $resolverAsli = new TagihanNominalResolver(new JenisTagihanSasaranMatcher());
    $resolverMock = \Mockery::mock(TagihanNominalResolver::class);
    $resolverMock->shouldReceive('resolve')
        ->with(\Mockery::on(fn (Siswa $s) => $s->id === $siswaGagal->id), \Mockery::any())
        ->andThrow(new \RuntimeException('Simulasi kegagalan resolusi nominal'));
    $resolverMock->shouldReceive('resolve')
        ->with(\Mockery::on(fn (Siswa $s) => $s->id === $siswaBerhasil->id), \Mockery::any())
        ->andReturnUsing(fn (Siswa $s, JenisTagihan $jt) => $resolverAsli->resolve($s, $jt));

    $generator = new TagihanBillingGenerator(new JenisTagihanSasaranMatcher(), $resolverMock, app(NotificationDispatcher::class));
    $log = $generator->generate($jenisTagihan, 'cron');

    expect($log->status)->toBe('partial');
    expect($log->bills_generated)->toBe(1);
    expect($log->error_log)->toHaveCount(1);
    expect($log->error_log[0]['siswa_id'])->toBe($siswaGagal->id);
    expect(Tagihan::where('tagihable_id', $siswaBerhasil->id)->exists())->toBeTrue();
    expect(Tagihan::where('tagihable_id', $siswaGagal->id)->exists())->toBeFalse();
});

it('rejects generate() for a pendaftaran-kategori jenis_tagihan without creating anything', function () {
    // Siswa is created before the PPDB-kategori JenisTagihan exists so the sync
    // StudentCreated -> GenerateTagihanForNewStudent listener (guarded in a later
    // task, not this one) finds nothing to act on and doesn't itself trip this
    // guard during Arrange — the only trigger under test is the manual generate() call below.
    $lembaga = \App\Models\Lembaga::factory()->create();
    Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'kategori' => 'pendaftaran', 'default_amount' => 200000]);

    expect(fn () => buatGenerator()->generate($jenisTagihan, 'manual'))->toThrow(\RuntimeException::class);

    expect(Tagihan::count())->toBe(0);
    expect(\App\Domains\Keuangan\Models\BillingJobLog::count())->toBe(0);
});

it('rejects generateForSiswaViaEvent() for a daftar_ulang-kategori jenis_tagihan without creating anything', function () {
    // Same ordering rationale as above: Siswa exists before the PPDB-kategori
    // JenisTagihan is created, so the sync StudentCreated listener has nothing to
    // match and the guard is only exercised by the explicit call below.
    $lembaga = \App\Models\Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'kategori' => 'daftar_ulang', 'default_amount' => 200000]);

    expect(fn () => buatGenerator()->generateForSiswaViaEvent($siswa, $jenisTagihan, 'StudentCreated'))->toThrow(\RuntimeException::class);

    expect(Tagihan::count())->toBe(0);
    expect(\App\Domains\Keuangan\Models\BillingJobLog::count())->toBe(0);
});
