<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable(false)->change();
            $table->foreign('person_id')->references('id')->on('persons')->restrictOnDelete();
            $table->index('person_id', 'idx_tagihan_person');
        });
    }

    public function down(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropIndex('idx_tagihan_person');
            $table->unsignedBigInteger('person_id')->nullable()->change();
        });
    }
};
