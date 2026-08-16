<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('nama_workflow');
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained('workflow_definitions')->cascadeOnDelete();
            $table->unsignedInteger('step_number');
            $table->string('step_name');
            $table->string('approver_type'); // ROLE, DIRECT_RELATION, SPECIFIC_USER
            $table->string('approver_value'); // e.g. kepala_sekolah, bendahara_yayasan, wali_kelas, or user_id
            $table->string('scope_level')->default('lembaga'); // lembaga, yayasan, global
            $table->boolean('is_final_step')->default(false);
            $table->timestamps();

            $table->unique(['workflow_definition_id', 'step_number']);
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained('workflow_definitions')->cascadeOnDelete();
            $table->morphs('approvable'); // Target entity being approved (e.g. PengajuanPengadaan)
            $table->nullableMorphs('requester'); // Requester entity (User, Siswa, Guru, OrangTua)
            $table->foreignId('current_step_id')->nullable()->constrained('workflow_steps')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, in_review, approved, rejected, revision_required
            $table->text('last_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->nullable()->constrained('workflow_steps')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action'); // APPROVE, REJECT, REQUEST_REVISION
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflow_definitions');
    }
};
