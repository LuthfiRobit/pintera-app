<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gelombang_jalur', function (Blueprint $table) {
            $table->foreignId('gelombang_ppdb_id')->constrained('gelombang_ppdb')->cascadeOnDelete();
            $table->foreignId('jalur_ppdb_id')->constrained('jalur_ppdb')->cascadeOnDelete();
            $table->primary(['gelombang_ppdb_id', 'jalur_ppdb_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gelombang_jalur');
    }
};
