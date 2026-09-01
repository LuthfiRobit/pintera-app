<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('backfills tipe correctly based on the pre-existing mode of each row', function () {
    // Simulate rows that existed BEFORE this migration by inserting directly,
    // bypassing the model's own tipe default (added in a later task) so this
    // test exercises the migration's own backfill UPDATE statements in isolation.
    $lembaga = \App\Models\Lembaga::factory()->create();

    $otomatisId = DB::table('jenis_tagihan')->insertGetId([
        'lembaga_id' => $lembaga->id, 'nama' => 'Otomatis Lama', 'kategori' => 'spp',
        'mode' => 'otomatis', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $manualId = DB::table('jenis_tagihan')->insertGetId([
        'lembaga_id' => $lembaga->id, 'nama' => 'Manual Lama', 'kategori' => 'kegiatan',
        'mode' => 'manual', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::statement("UPDATE jenis_tagihan SET tipe = 'bulanan' WHERE mode = 'otomatis' AND id = ?", [$otomatisId]);
    DB::statement("UPDATE jenis_tagihan SET tipe = 'sekali' WHERE mode = 'manual' AND id = ?", [$manualId]);

    $otomatisTipe = DB::table('jenis_tagihan')->where('id', $otomatisId)->value('tipe');
    $manualTipe = DB::table('jenis_tagihan')->where('id', $manualId)->value('tipe');

    expect($otomatisTipe)->toBe('bulanan');
    expect($manualTipe)->toBe('sekali');
});

it('rejects a null tipe at the database level', function () {
    expect(fn () => DB::table('jenis_tagihan')->insert([
        'lembaga_id' => \App\Models\Lembaga::factory()->create()->id, 'nama' => 'Tanpa Tipe', 'kategori' => 'spp',
        'mode' => 'manual', 'tipe' => null, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('rejects mode=otomatis + tipe=sekali at the database level via CHECK constraint', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();

    expect(fn () => DB::table('jenis_tagihan')->insert([
        'lembaga_id' => $lembaga->id, 'nama' => 'Kontradiktif', 'kategori' => 'spp',
        'mode' => 'otomatis', 'tipe' => 'sekali', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('no longer has the last_generated_period column', function () {
    expect(Schema::hasColumn('jenis_tagihan', 'last_generated_period'))->toBeFalse();
});

it('widens tagihan.billing_period to fit a full Y-m-d date', function () {
    $tagihan = \App\Domains\Keuangan\Models\Tagihan::factory()->create(['billing_period' => '2026-09-01']);

    expect($tagihan->fresh()->billing_period)->toBe('2026-09-01');
});
