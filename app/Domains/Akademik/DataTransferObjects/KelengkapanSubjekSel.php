<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

use App\Models\Siswa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final readonly class KelengkapanSubjekSel
{
    /**
     * @param  Collection<int, Siswa>  $siswaBelumLengkap
     */
    public function __construct(
        public Model $subjek,
        public int $totalSiswa,
        public Collection $siswaBelumLengkap,
    ) {}
}
