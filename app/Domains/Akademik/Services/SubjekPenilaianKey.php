<?php

namespace App\Domains\Akademik\Services;

use Illuminate\Database\Eloquent\Model;

final class SubjekPenilaianKey
{
    public static function dari(Model $subjek): string
    {
        return $subjek->getMorphClass().':'.$subjek->getKey();
    }
}
