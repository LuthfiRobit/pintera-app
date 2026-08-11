<?php
// tests/Feature/Keuangan/TagihanBackfillTest.php

use App\Models\Pendaftaran;
use Illuminate\Support\Facades\DB;

it('backfills tagihable_type and tagihable_id for legacy rows that only have pendaftaran_id', function () {
    $pendaftaran = Pendaftaran::factory()->create();

    $legacyId = DB::table('tagihan')->insertGetId([
        'pendaftaran_id' => $pendaftaran->id,
        'kategori' => 'pendaftaran',
        'total_tagihan' => 150000,
        'net_amount' => 150000,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
        'source_trigger' => 'manual',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('tagihan')->where('id', $legacyId)->value('tagihable_type'))->toBeNull();

    (require database_path('migrations/2026_08_10_140000_backfill_tagihan_tagihable_columns.php'))->up();

    $row = DB::table('tagihan')->where('id', $legacyId)->first();
    expect($row->tagihable_type)->toBe(Pendaftaran::class);
    expect((int) $row->tagihable_id)->toBe($pendaftaran->id);
});

it('does not touch rows that already have tagihable columns set', function () {
    $pendaftaran = Pendaftaran::factory()->create();
    $siswa = \App\Models\Siswa::factory()->create();

    $alreadySetId = DB::table('tagihan')->insertGetId([
        'tagihable_type' => \App\Models\Siswa::class,
        'tagihable_id' => $siswa->id,
        'kategori' => 'spp',
        'total_tagihan' => 300000,
        'net_amount' => 300000,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
        'source_trigger' => 'cron',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (require database_path('migrations/2026_08_10_140000_backfill_tagihan_tagihable_columns.php'))->up();

    $row = DB::table('tagihan')->where('id', $alreadySetId)->first();
    expect($row->tagihable_type)->toBe(\App\Models\Siswa::class);
    expect((int) $row->tagihable_id)->toBe($siswa->id);
});

it('backfills net_amount for pre-existing tagihan rows left null', function () {
    $pendaftaran = Pendaftaran::factory()->create();

    $noDiscountId = DB::table('tagihan')->insertGetId([
        'pendaftaran_id' => $pendaftaran->id,
        'kategori' => 'pendaftaran',
        'total_tagihan' => 150000,
        'net_amount' => null,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
        'source_trigger' => 'manual',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $withDiscountId = DB::table('tagihan')->insertGetId([
        'pendaftaran_id' => $pendaftaran->id,
        'kategori' => 'daftar_ulang',
        'total_tagihan' => 200000,
        'discount_amount' => 50000,
        'net_amount' => null,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
        'source_trigger' => 'manual',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (require database_path('migrations/2026_08_10_140000_backfill_tagihan_tagihable_columns.php'))->up();

    $noDiscountRow = DB::table('tagihan')->where('id', $noDiscountId)->first();
    $withDiscountRow = DB::table('tagihan')->where('id', $withDiscountId)->first();

    expect((float) $noDiscountRow->net_amount)->toBe(150000.0);
    expect((float) $withDiscountRow->net_amount)->toBe(150000.0);
});
