<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Defensive idempotent guard. `2026_07_25_131000_create_asesmen_tables` was edited in
     * place (not as a new migration) to fix nilai_siswa's schema during the Tahap 7
     * remediation, before this project had more than one real environment. This migration
     * is a no-op wherever nilai_siswa already has the corrected schema (a fresh `migrate`
     * from zero, or this project's own dev DB after the in-place edit). It only alters the
     * table if it detects the ORIGINAL (pre-fix) shape — recognizable by a `skor` column and
     * no `komponen_penilaian_id` column — which would happen only on an environment that ran
     * the pre-edit version of that migration file and never re-ran the corrected one.
     *
     * Scope limitation: this only safely handles an empty (or komponen_penilaian_id-less)
     * legacy table. A non-empty legacy table with real skor-based rows would need a product
     * decision about which komponen_penilaian each historical row belongs to before the new
     * NOT NULL foreign key could be added — out of scope for an automatic migration.
     */
    public function up(): void
    {
        if (Schema::hasColumn('nilai_siswa', 'komponen_penilaian_id')) {
            return;
        }

        Schema::table('nilai_siswa', function (Blueprint $table) {
            // On the legacy shape, `asesmen_id`'s foreign key was created in the same
            // Schema::create() call as the (asesmen_id, siswa_id) unique index, so MySQL
            // reused that composite unique as the FK's sole supporting index instead of
            // creating a redundant single-column one. Dropping the unique below would
            // therefore fail with "Cannot drop index ... needed in a foreign key constraint"
            // unless a dedicated single-column index for asesmen_id exists first.
            $table->index('asesmen_id');
            $table->dropUnique(['asesmen_id', 'siswa_id']);
            $table->foreignId('komponen_penilaian_id')->after('siswa_id')->constrained('komponen_penilaian')->cascadeOnDelete();
            $table->unsignedTinyInteger('nilai_angka')->nullable()->after('komponen_penilaian_id');
            $table->string('predikat')->nullable()->after('nilai_angka');
            $table->dropColumn('skor');
            $table->unique(['asesmen_id', 'siswa_id', 'komponen_penilaian_id'], 'nilai_siswa_unik');
            // The new composite unique above also covers asesmen_id as its leftmost column,
            // so it now supports the FK on its own -- drop the temporary helper index added
            // above to leave the exact same index set a fresh install would have.
            $table->dropIndex(['asesmen_id']);
        });
    }

    public function down(): void
    {
        // Intentionally a no-op: this migration only ever acts on a legacy schema it detects
        // at runtime, and reversing it would require re-deriving skor values that no longer
        // exist once komponen-scoped rows have been populated.
    }
};
