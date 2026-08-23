<?php

namespace App\Domains\Keuangan\Events;

use App\Domains\Keuangan\Models\JenisTagihan;
use Illuminate\Foundation\Events\Dispatchable;

class BillTypeActivated
{
    use Dispatchable;

    public function __construct(public readonly JenisTagihan $jenisTagihan)
    {
    }
}