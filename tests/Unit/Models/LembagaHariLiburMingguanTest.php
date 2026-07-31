<?php

use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('defaults hari_libur_mingguan to Sunday and casts it to an array', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    expect($lembaga->fresh()->hari_libur_mingguan)->toBe([0]);
});

it('defaults hari_libur_mingguan to [0] at the DB layer even for non-Eloquent inserts', function () {
    $yayasan = Yayasan::factory()->create();

    // Bypass Eloquent entirely (no model events, no casts) to prove the
    // column's default is enforced by MySQL itself, not by app-layer hooks.
    $id = DB::table('lembaga')->insertGetId([
        'yayasan_id' => $yayasan->id,
        'npsn' => '12345678',
        'nama' => 'Sekolah Non-Eloquent',
        'slug' => 'sekolah-non-eloquent',
        'kode_lembaga' => 'SKLNEQ-1',
        'bentuk_pendidikan' => 'SD',
        'status_sekolah' => 'negeri',
        'naungan' => 'kemendikdasmen',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $raw = DB::table('lembaga')->where('id', $id)->value('hari_libur_mingguan');
    expect(json_decode($raw, true))->toBe([0]);

    expect(Lembaga::find($id)->hari_libur_mingguan)->toBe([0]);
});

it('can store a custom set of weekly off-days, e.g. Friday for a pesantren', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembaga->update(['hari_libur_mingguan' => [5]]);

    expect($lembaga->fresh()->hari_libur_mingguan)->toBe([5]);
});
