<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('yayasan_id')->nullable()->index()->after('lembaga_id');
        });

        // Backfill: derive yayasan_id from the user's own lembaga where possible,
        // otherwise (yayasan-scope users, whose lembaga_id is always null) fall back
        // to the single yayasan already known to own their session-selected lembaga
        // history is not recoverable, so default to the first yayasan in the system.
        // This keeps existing single-yayasan installs (the only kind that exist today)
        // working without manual intervention; multi-yayasan installs must review and
        // correct these values before onboarding a second yayasan tenant.
        DB::statement(<<<'SQL'
            UPDATE users
            LEFT JOIN lembaga ON lembaga.id = users.lembaga_id
            SET users.yayasan_id = lembaga.yayasan_id
            WHERE users.lembaga_id IS NOT NULL
        SQL);

        $fallbackYayasanId = DB::table('yayasan')->orderBy('id')->value('id');
        if ($fallbackYayasanId) {
            DB::table('users')->whereNull('yayasan_id')->update(['yayasan_id' => $fallbackYayasanId]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('yayasan_id');
        });
    }
};
