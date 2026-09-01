<?php

namespace App\Domains\Keuangan\Listeners;

use App\Domains\Identity\Events\PersonsMerged;
use Illuminate\Support\Facades\DB;

// Deliberately NOT ShouldQueue: must run synchronously inside
// MergePersonsAction's transaction so a failure here rolls back the whole
// Person merge, not just this reparenting. See PersonsMerged's class comment.
class ReparentTagihanOnPersonsMerged
{
    public function handle(PersonsMerged $event): void
    {
        DB::table('tagihan')
            ->where('person_id', $event->losing->id)
            ->update(['person_id' => $event->winning->id]);
    }
}
