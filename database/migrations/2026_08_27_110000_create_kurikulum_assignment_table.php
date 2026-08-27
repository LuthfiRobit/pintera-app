<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kurikulum_assignment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->string('bentuk_pendidikan', 10);
            $table->string('tingkat', 10)->nullable();
            $table->string('kurikulum', 20);
            $table->unsignedBigInteger('lembaga_key')->virtualAs('COALESCE(lembaga_id, 0)');
            $table->string('tingkat_key', 10)->virtualAs("COALESCE(tingkat, '*')");
            $table->timestamps();

            $table->unique(
                ['lembaga_key', 'tahun_ajaran_id', 'bentuk_pendidikan', 'tingkat_key'],
                'kurikulum_assignment_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kurikulum_assignment');
    }
};
