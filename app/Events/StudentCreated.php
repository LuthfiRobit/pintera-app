<?php
// app/Events/StudentCreated.php

namespace App\Events;

use App\Models\Siswa;
use Illuminate\Foundation\Events\Dispatchable;

class StudentCreated
{
    use Dispatchable;

    public function __construct(public readonly Siswa $siswa)
    {
    }
}
