<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Tugas;

use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusTugas;
use App\Models\Scopes\TenantScope;
use App\Notifications\TugasSelesaiNotification;

final class TandaiTugasSelesaiAction
{
    public function execute(Kasus $kasus, KasusTugas $kasusTugas): void
    {
        $kasusTugas->update(['status' => 'selesai']);

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
        $kontakUtama?->notify(new TugasSelesaiNotification($kasusTugas));
    }
}
