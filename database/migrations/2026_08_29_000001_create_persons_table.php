<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->text('nik')->nullable();
            $table->char('nik_hash', 64)->nullable();
            $table->string('nama_lengkap');
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('agama', 50)->nullable();
            $table->string('kewarganegaraan', 50)->default('WNI');
            $table->string('no_hp', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('alamat_jalan')->nullable();
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->string('desa_kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kabupaten_kota')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kode_pos', 10)->nullable();
            $table->foreignId('merged_into_person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['yayasan_id', 'nik_hash'], 'uq_persons_yayasan_nik');
            $table->index('nama_lengkap');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('merged_into_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_user_id');
        });

        Schema::dropIfExists('persons');
    }
};
