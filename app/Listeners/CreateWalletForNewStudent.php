<?php

namespace App\Listeners;

use App\Events\StudentCreated;
use App\Domains\Keuangan\Models\Wallet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateWalletForNewStudent
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(StudentCreated $event): void
    {
        Wallet::firstOrCreate([
            'siswa_id' => $event->siswa->id,
        ]);
    }
}
