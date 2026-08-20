<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class JamPelajaranData
{
    /**
     * @param  array<int, string>  $hari  nilai enum App\Enums\Hari (mis. 'senin', 'selasa')
     */
    public function __construct(
        public int $polaJamId,
        public array $hari,
        public int $urutan,
        public string $label,
        public string $jamMulai,
        public string $jamSelesai,
        public bool $isPelajaran,
    ) {}
}
