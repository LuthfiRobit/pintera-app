<?php

use App\Events\StudentCreated;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('creates wallet idempotently when StudentCreated event is fired', function () {
    $siswa = Siswa::factory()->create();
    
    // Since Siswa::created() automatically fires StudentCreated, 
    // the listener should have already been triggered once.
    $wallets = Wallet::where('siswa_id', $siswa->id)->get();
    expect($wallets)->toHaveCount(1);
    
    // Fire event again manually (idempotency check)
    event(new StudentCreated($siswa));
    
    $walletsAfter = Wallet::where('siswa_id', $siswa->id)->get();
    expect($walletsAfter)->toHaveCount(1);
});
