<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function actingAsJenisTagihanManager(int $lembagaId): User
{
    $user = User::factory()->create(['lembaga_id' => $lembagaId]);
    $user->assignRole('bendahara_lembaga');

    return $user;
}

dataset('kombinasi mode+tipe wajib', [
    'manual + harian, no extra fields required' => ['manual', 'harian', []],
    'manual + mingguan, no extra fields required' => ['manual', 'mingguan', []],
    'manual + bulanan, no extra fields required' => ['manual', 'bulanan', []],
    'manual + tahunan, no extra fields required' => ['manual', 'tahunan', []],
    'manual + sekali, no extra fields required' => ['manual', 'sekali', []],
    'otomatis + harian requires offset_hari_jatuh_tempo' => ['otomatis', 'harian', ['offset_hari_jatuh_tempo' => 3]],
    'otomatis + mingguan requires hari_generate and offset_hari_jatuh_tempo' => ['otomatis', 'mingguan', ['hari_generate' => 1, 'offset_hari_jatuh_tempo' => 3]],
    'otomatis + bulanan requires tanggal_generate and hari_jatuh_tempo' => ['otomatis', 'bulanan', ['tanggal_generate' => 1, 'hari_jatuh_tempo' => 10]],
    'otomatis + tahunan requires bulan_generate, tanggal_generate, hari_jatuh_tempo' => ['otomatis', 'tahunan', ['bulan_generate' => 7, 'tanggal_generate' => 1, 'hari_jatuh_tempo' => 10]],
]);

it('accepts valid mode+tipe combinations with their required support fields', function (string $mode, string $tipe, array $extra) {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $admin = actingAsJenisTagihanManager($lembaga->id);
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.store'), array_merge([
        'nama' => 'Test Jenis Tagihan',
        'kategori' => 'spp',
        'mode' => $mode,
        'tipe' => $tipe,
        'tanggal_mulai' => $mode === 'otomatis' ? now()->toDateString() : null,
    ], $extra));

    $response->assertStatus(201);
})->with('kombinasi mode+tipe wajib');

it('rejects otomatis+mingguan when hari_generate is missing', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $admin = actingAsJenisTagihanManager($lembaga->id);
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Test', 'kategori' => 'spp', 'mode' => 'otomatis', 'tipe' => 'mingguan',
        'tanggal_mulai' => now()->toDateString(), 'offset_hari_jatuh_tempo' => 3,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('hari_generate');
});

it('rejects the contradictory combination mode=otomatis + tipe=sekali with an explicit message', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $admin = actingAsJenisTagihanManager($lembaga->id);
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Test', 'kategori' => 'spp', 'mode' => 'otomatis', 'tipe' => 'sekali',
        'tanggal_mulai' => now()->toDateString(),
    ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => "Tipe 'Sekali' tidak bisa dipasangkan dengan Mode Otomatis karena kontradiktif (generate berulang vs sekali saja)."]);
});

it('rejects tipe and its support fields for a PPDB kategori, same as other billing fields', function () {
    $lembaga = \App\Models\Lembaga::factory()->create();
    $admin = actingAsJenisTagihanManager($lembaga->id);
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Test PPDB', 'kategori' => 'pendaftaran', 'mode' => 'manual', 'tipe' => 'sekali',
        'hari_generate' => 1,
    ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => 'Target sasaran, tarif berdimensi, dan keringanan hanya berlaku untuk kategori selain Pendaftaran/Daftar Ulang.']);
});
