<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\SkipAlertResolver;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not treat a perlu_ditinjau_ulang tagihan as a skip candidate at all', function () {
    $siswa = Siswa::factory()->create();
    $siswa->wallet->update(['balance' => 0]);

    $jenis = JenisTagihan::factory()->create(['priority_score' => 1]);
    Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'total_tagihan' => 100000, 'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
        'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh',
    ]);

    $result = app(SkipAlertResolver::class)->resolve($siswa);

    expect($result)->toBeNull();
});

it('still surfaces a normal (non-flagged) tagihan as a skip candidate', function () {
    $siswa = Siswa::factory()->create();
    $siswa->wallet->update(['balance' => 0]);

    $jenis = JenisTagihan::factory()->create(['priority_score' => 1, 'nama' => 'SPP']);
    Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'total_tagihan' => 100000, 'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);

    $result = app(SkipAlertResolver::class)->resolve($siswa);

    expect($result)->not->toBeNull();
    expect($result['selisih'])->toBe(100000.0);
});
