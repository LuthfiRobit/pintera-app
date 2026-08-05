<?php
// database/migrations/2026_08_07_000000_backfill_tanggal_kasus_tugas_submission_harian.php
//
// Backfills `tanggal` for any kasus_tugas_submission row belonging to a harian
// (daily) kasus_tugas that was created before `tanggal` existed / was left null.
// Without this, such rows land in an unreachable '' bucket in the kasus detail
// view's per-date grouping and become invisible and unreviewable.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'UPDATE kasus_tugas_submission s
             INNER JOIN kasus_tugas t ON t.id = s.tugas_id
             SET s.tanggal = DATE(s.created_at)
             WHERE t.frekuensi = ? AND s.tanggal IS NULL',
            ['harian']
        );
    }

    public function down(): void
    {
        // Backfill is not reversible (original NULLs are not recoverable); no-op.
    }
};
