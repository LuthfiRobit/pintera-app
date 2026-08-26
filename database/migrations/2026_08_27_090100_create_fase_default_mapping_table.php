<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fase_default_mapping', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->string('bentuk_pendidikan', 10);
            $table->string('tingkat', 10)->nullable();
            $table->foreignId('fase_id')->constrained('fase')->restrictOnDelete();
            $table->unsignedBigInteger('lembaga_key')->virtualAs('COALESCE(lembaga_id, 0)');
            $table->string('tingkat_key', 10)->virtualAs("COALESCE(tingkat, '*')");
            $table->timestamps();

            $table->unique(['lembaga_key', 'bentuk_pendidikan', 'tingkat_key'], 'fase_default_mapping_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fase_default_mapping');
    }
};
