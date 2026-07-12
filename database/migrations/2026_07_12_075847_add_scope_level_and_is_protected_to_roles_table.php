<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->enum('scope_level', ['yayasan', 'lembaga', 'diri_sendiri'])
                ->default('lembaga')
                ->after('guard_name');
            $table->boolean('is_protected')->default(false)->after('scope_level');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['scope_level', 'is_protected']);
        });
    }
};
