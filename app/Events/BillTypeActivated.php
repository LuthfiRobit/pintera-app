<?php

namespace App\Events;

use App\Models\JenisTagihan;
use Illuminate\Foundation\Events\Dispatchable;

class BillTypeActivated
{
    use Dispatchable;

    public function __construct(public readonly JenisTagihan $jenisTagihan)
    {
    }
}
