<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->text('catatan_keputusan')->nullable()->after('status');
            $table->foreignId('ditetapkan_oleh_user_id')->nullable()->after('catatan_keputusan')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('ditetapkan_pada')->nullable()->after('ditetapkan_oleh_user_id');
            $table->foreignId('sk_ppdb_id')->nullable()->after('ditetapkan_pada')
                ->constrained('sk_ppdb')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sk_ppdb_id');
            $table->dropConstrainedForeignId('ditetapkan_oleh_user_id');
            $table->dropColumn(['catatan_keputusan', 'ditetapkan_pada']);
        });
    }
};
