<?php

namespace App\Jobs;

use App\Domains\Keuangan\Actions\Tagihan\RecalculateTagihanNominalAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateTagihanNominalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $tagihanId) {}

    public function handle(RecalculateTagihanNominalAction $action): void
    {
        $action->execute($this->tagihanId);
    }
}
