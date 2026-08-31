<?php

use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Lembaga;
use Illuminate\Support\Facades\DB;

it('fails when a guru row still has no person_id', function () {
    $lembaga = Lembaga::factory()->create();
    try {
        DB::statement('ALTER TABLE guru MODIFY person_id BIGINT UNSIGNED NULL');
        DB::table('guru')->insert([
            'lembaga_id' => $lembaga->id,
            'jenis_ptk' => 'guru_mapel',
            'status_kepegawaian' => 'GTY',
            'status_aktif' => 'aktif',
            'person_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('identity:verify-backfill')
            ->expectsOutputToContain('guru')
            ->assertExitCode(1);
    } finally {
        DB::table('guru')->whereNull('person_id')->delete();
        DB::statement('ALTER TABLE guru MODIFY person_id BIGINT UNSIGNED NOT NULL');
    }
});

it('succeeds when every role table row has a person_id', function () {
    $lembaga = Lembaga::factory()->create();
    Guru::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => Person::factory()->create(['yayasan_id' => $lembaga->yayasan_id])->id]);

    $this->artisan('identity:verify-backfill')->assertExitCode(0);
});
