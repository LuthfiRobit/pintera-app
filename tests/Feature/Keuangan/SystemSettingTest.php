<?php

use App\Models\SystemSetting;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Wallet;
use App\Domains\Keuangan\Models\JenisTagihan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('resolves setting with fallback from lembaga to global', function () {
    $lembaga = Lembaga::factory()->create();

    // Global setting
    SystemSetting::create([
        'key' => 'auto_debit_enabled',
        'value' => '0',
    ]);

    expect(SystemSetting::getResolved('auto_debit_enabled', $lembaga->id, true))->toBe('0');

    // Override at lembaga level
    SystemSetting::create([
        'lembaga_id' => $lembaga->id,
        'key' => 'auto_debit_enabled',
        'value' => '1',
    ]);

    // Cache must be flushed because rememberForever caches it
    Cache::flush();

    expect(SystemSetting::getResolved('auto_debit_enabled', $lembaga->id, true))->toBe('1');
});

it('triggers auto allocation on topup when auto debit is enabled', function () {
    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $wallet = $siswa->wallet;

    SystemSetting::create([
        'lembaga_id' => $lembaga->id,
        'key' => 'auto_debit_enabled',
        'value' => '1',
    ]);

    $jenis = JenisTagihan::factory()->create(['priority_score' => 1]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id,
        'tagihable_type' => Siswa::class,
        'jenis_tagihan_id' => $jenis->id,
        'total_tagihan' => 100000,
        'net_amount' => 100000,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
    ]);

    $wallet->topup(150000);

    $wallet->refresh();
    $tagihan->refresh();

    // The tagihan should be paid, wallet balance 50,000
    expect($tagihan->status)->toBe('lunas');
    expect($wallet->balance)->toEqual(50000);
});

it('does not trigger auto allocation when auto debit is disabled', function () {
    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $wallet = $siswa->wallet;

    SystemSetting::create([
        'lembaga_id' => $lembaga->id,
        'key' => 'auto_debit_enabled',
        'value' => '0',
    ]);

    $jenis = JenisTagihan::factory()->create(['priority_score' => 1]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id,
        'tagihable_type' => Siswa::class,
        'jenis_tagihan_id' => $jenis->id,
        'total_tagihan' => 100000,
        'net_amount' => 100000,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
    ]);

    $wallet->topup(150000);

    $wallet->refresh();
    $tagihan->refresh();

    // The tagihan should NOT be paid, wallet balance intact
    expect($tagihan->status)->toBe('belum_bayar');
    expect($wallet->balance)->toEqual(150000);
});
