<?php
// app/Listeners/GenerateTagihanForUpdatedClass.php

namespace App\Listeners;

use App\Events\StudentUpdatedClass;
use App\Models\JenisTagihan;
use App\Models\Scopes\TenantScope;
use App\Services\JenisTagihanSasaranMatcher;
use App\Services\TagihanBillingGenerator;

class GenerateTagihanForUpdatedClass
{
    public function __construct(
        private readonly JenisTagihanSasaranMatcher $matcher,
        private readonly TagihanBillingGenerator $generator,
    ) {
    }

    public function handle(StudentUpdatedClass $event): void
    {
        $siswa = $event->siswa;

        JenisTagihan::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $siswa->lembaga_id)
            ->where('is_active', true)
            ->whereNotIn('kategori', ['pendaftaran', 'daftar_ulang'])
            ->get()
            ->each(function (JenisTagihan $jenisTagihan) use ($siswa) {
                if ($this->matcher->siswaMatchesJenisTagihan($siswa, $jenisTagihan)) {
                    $this->generator->generateForSiswaViaEvent($siswa, $jenisTagihan, 'StudentUpdatedClass');
                }
            });
    }
}
