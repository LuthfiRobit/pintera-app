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
            $table->string('username')->nullable()->unique()->after('email');
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        // Siswa tidak punya email sama sekali — kolom ini harus bisa NULL supaya
        // akun siswa bisa dibuat. Unique index tetap jalan normal karena MySQL
        // memperbolehkan banyak baris NULL pada kolom unique.
        DB::statement('ALTER TABLE users MODIFY COLUMN email VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users MODIFY COLUMN email VARCHAR(255) NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'must_change_password']);
        });
    }
};
