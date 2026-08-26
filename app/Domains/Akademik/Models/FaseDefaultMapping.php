<?php

namespace App\Domains\Akademik\Models;

use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Model;

class FaseDefaultMapping extends Model
{
    protected $table = 'fase_default_mapping';

    protected $fillable = ['lembaga_id', 'bentuk_pendidikan', 'tingkat', 'fase_id'];

    public function fase()
    {
        return $this->belongsTo(Fase::class);
    }

    public function lembaga()
    {
        return $this->belongsTo(Lembaga::class);
    }
}
