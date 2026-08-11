<?php
// app/Events/StudentUpdatedClass.php

namespace App\Events;

use App\Models\Siswa;
use Illuminate\Foundation\Events\Dispatchable;

class StudentUpdatedClass
{
    use Dispatchable;

    public function __construct(public readonly Siswa $siswa)
    {
    }
}
