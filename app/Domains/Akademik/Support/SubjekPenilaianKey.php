<?php

namespace App\Domains\Akademik\Support;

use Illuminate\Database\Eloquent\Model;

final class SubjekPenilaianKey
{
    public static function dari(Model $subjek): string
    {
        return $subjek->getMorphClass().':'.$subjek->getKey();
    }
}
