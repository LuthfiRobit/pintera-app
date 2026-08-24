<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE roles MODIFY scope_level ENUM('yayasan', 'lembaga', 'diri_sendiri', 'platform') NOT NULL DEFAULT 'lembaga'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE roles MODIFY scope_level ENUM('yayasan', 'lembaga', 'diri_sendiri') NOT NULL DEFAULT 'lembaga'");
    }
};
