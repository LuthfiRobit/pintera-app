<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\Person;
use Illuminate\Database\Eloquent\Collection;

class PersonDuplicateFinder
{
    public function find(string $namaLengkap, ?string $tanggalLahir): Collection
    {
        return Person::query()
            ->where('nama_lengkap', $namaLengkap)
            ->when($tanggalLahir !== null, fn ($q) => $q->where('tanggal_lahir', $tanggalLahir))
            ->get();
    }
}
