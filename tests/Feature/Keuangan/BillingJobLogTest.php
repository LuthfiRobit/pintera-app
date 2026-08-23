<?php

use App\Domains\Keuangan\Models\BillingJobLog;
use App\Domains\Keuangan\Models\JenisTagihan;

it('stores a billing job log with an error_log array and relates back to jenis_tagihan', function () {
    $jenisTagihan = JenisTagihan::factory()->create();

    $log = BillingJobLog::create([
        'jenis_tagihan_id' => $jenisTagihan->id,
        'trigger_type' => 'cron',
        'trigger_event' => null,
        'period' => '2026-09',
        'bills_generated' => 3,
        'status' => 'partial',
        'error_log' => [['siswa_id' => 42, 'message' => 'Something failed']],
        'executed_at' => now(),
    ]);

    $retrievedLog = $log->fresh();
    expect($retrievedLog->error_log)->toHaveCount(1);
    expect($retrievedLog->error_log[0])->toHaveKeys(['siswa_id', 'message']);
    expect($retrievedLog->error_log[0]['siswa_id'])->toBe(42);
    expect($retrievedLog->error_log[0]['message'])->toBe('Something failed');
    expect($retrievedLog->jenisTagihan->id)->toBe($jenisTagihan->id);
});

it('allows a null error_log and trigger_event for a clean cron run', function () {
    $jenisTagihan = JenisTagihan::factory()->create();

    $log = BillingJobLog::create([
        'jenis_tagihan_id' => $jenisTagihan->id,
        'trigger_type' => 'cron',
        'trigger_event' => null,
        'period' => '2026-09',
        'bills_generated' => 5,
        'status' => 'success',
        'error_log' => null,
        'executed_at' => now(),
    ]);

    expect($log->fresh()->error_log)->toBeNull();
});
