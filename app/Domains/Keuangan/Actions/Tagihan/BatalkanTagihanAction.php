<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Tagihan;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;

class BatalkanTagihanAction
{
    /**
     * Ownership check MUST run before any business-rule check — otherwise a status-based
     * 422/403 response difference leaks whether an arbitrary cross-tenant tagihan is
     * belum_bayar, before we've verified it even belongs to this jenisTagihan. Urutan ini
     * dipertahankan PERSIS dari JenisTagihanMonitoringController::batalTagihan() lama —
     * JANGAN dibalik, pelajaran langsung dari celah guard yang hilang di review SP1.
     */
    public function execute(JenisTagihan $jenisTagihan, Tagihan $tagihan, int $userId, string $cancelReason): void
    {
        if ($tagihan->jenis_tagihan_id !== $jenisTagihan->id) {
            abort(403, 'Tagihan tidak ditemukan untuk jenis tagihan ini.');
        }

        if ($tagihan->status !== 'belum_bayar') {
            abort(422, 'Hanya tagihan dengan status belum bayar yang dapat dibatalkan.');
        }

        $tagihan->update([
            'status' => 'dibatalkan',
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancel_reason' => $cancelReason,
        ]);
    }
}