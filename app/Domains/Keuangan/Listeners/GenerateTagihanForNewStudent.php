<?php

// app/Domains/Keuangan/Listeners/GenerateTagihanForNewStudent.php

namespace App\Domains\Keuangan\Listeners;

use App\Domains\Keuangan\Enums\KategoriTagihan;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Services\JenisTagihanSasaranMatcher;
use App\Domains\Keuangan\Services\TagihanBillingGenerator;
use App\Events\StudentCreated;
use App\Models\Scopes\TenantScope;

class GenerateTagihanForNewStudent
{
    public function __construct(
        private readonly JenisTagihanSasaranMatcher $matcher,
        private readonly TagihanBillingGenerator $generator,
    ) {}

    public function handle(StudentCreated $event): void
    {
        $siswa = $event->siswa;

        JenisTagihan::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $siswa->lembaga_id)
            ->where('is_active', true)
            ->whereNotIn('kategori', [KategoriTagihan::Pendaftaran->value, KategoriTagihan::DaftarUlang->value])
            ->get()
            ->each(function (JenisTagihan $jenisTagihan) use ($siswa) {
                if ($this->matcher->siswaMatchesJenisTagihan($siswa, $jenisTagihan)) {
                    $this->generator->generateForSiswaViaEvent($siswa, $jenisTagihan, 'StudentCreated');
                }
            });
    }
}
