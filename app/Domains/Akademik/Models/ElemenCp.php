<?php

namespace App\Domains\Akademik\Models;

use App\Domains\Akademik\Contracts\SubjekPenilaian;
use Database\Factories\ElemenCpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ElemenCp extends Model implements SubjekPenilaian
{
    use HasFactory;

    protected $table = 'elemen_cp';

    protected $fillable = ['kode', 'nama', 'no_urut'];

    protected static function newFactory(): ElemenCpFactory
    {
        return ElemenCpFactory::new();
    }
}
