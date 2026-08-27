<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Rpp;

use App\Domains\Akademik\Enums\StatusRpp;
use App\Domains\Akademik\Models\Rpp;
use App\Domains\Shared\Context\TenantContext;
use App\Models\Guru;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListRppAction
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array{rppList: LengthAwarePaginator, stats: array<string, int>, status: ?string, targetLembagaId: ?int}
     */
    public function execute(
        User $user,
        string $tab,
        ?string $search,
        ?int $tahunAjaranId,
        ?int $semesterId,
        ?int $kelasId,
        ?int $mapelId,
        ?string $status,
        int $perPage,
        ?string $kurikulum = null,
    ): array {
        $targetLembagaId = $this->tenantContext->activeLembagaId();

        $baseQuery = Rpp::query();
        if ($targetLembagaId) {
            $baseQuery->where('lembaga_id', $targetLembagaId);
        }

        if ($tab === 'saya') {
            $guru = Guru::where('user_id', $user->id)->first();
            if ($guru) {
                $baseQuery->where('guru_id', $guru->id);
            }
        } elseif ($tab === 'verifikasi' && $status === null) {
            $status = StatusRpp::Diajukan->value;
        }

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'draft' => (clone $baseQuery)->where('status', StatusRpp::Draft)->count(),
            'diajukan' => (clone $baseQuery)->where('status', StatusRpp::Diajukan)->count(),
            'disetujui' => (clone $baseQuery)->where('status', StatusRpp::Disetujui)->count(),
            'perlu_revisi' => (clone $baseQuery)->where('status', StatusRpp::PerluRevisi)->count(),
        ];

        $query = (clone $baseQuery)->with(['guru', 'kelas', 'mataPelajaran', 'semester', 'tahunAjaran', 'verifiedBy']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('judul_topik', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%")
                    ->orWhereHas('guru', fn ($g) => $g->where('nama', 'like', "%{$search}%"))
                    ->orWhereHas('mataPelajaran', fn ($m) => $m->where('nama', 'like', "%{$search}%"));
            });
        }
        if ($tahunAjaranId) {
            $query->where('tahun_ajaran_id', $tahunAjaranId);
        }
        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }
        if ($kelasId) {
            $query->where('kelas_id', $kelasId);
        }
        if ($mapelId) {
            $query->where('mata_pelajaran_id', $mapelId);
        }
        if ($kurikulum) {
            $query->whereHas('kelas', fn ($q) => $q->where('kurikulum', $kurikulum));
        }
        if ($status && in_array($status, array_column(StatusRpp::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $rppList = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        return [
            'rppList' => $rppList,
            'stats' => $stats,
            'status' => $status,
            'targetLembagaId' => $targetLembagaId,
        ];
    }
}
