<?php

namespace App\Domains\Akademik\Models;

use Illuminate\Database\Eloquent\Model;

class Fase extends Model
{
    protected $table = 'fase';

    protected $fillable = ['kode', 'nama', 'urutan'];
}
