<?php

// tests/Feature/Keuangan/DashboardTagihanPersonIdQueryTest.php
//
// Pins the behavior of DashboardController's "tagihanBelumLunas" query for both
// the siswa dashboard (site 1, ~line 131) and the orang_tua dashboard (site 2,
// ~line 202) across the Task 18 refactor: the OR-hack
// (tagihable_type=Siswa AND tagihable_id=X) OR pendaftaran_id=Y
// is replaced with a single where('person_id', ...). Both a "pendaftaran era"
// tagihan (identified by the legacy pendaftaran_id column) and a "siswa era"
// tagihan (identified by tagihable_type=Siswa) must still be summed together,
// because both were backfilled to the same person_id.

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\OrangTua;
use App\Models\Pendaftaran;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;

function buatTagihanDuaEra(Siswa $siswa): void
{
    // "Pendaftaran era" tagihan: legacy pendaftaran_id column set, matching
    // $siswa->pendaftaran_asal_id. This is what the old OR-hack's
    // `orWhere('pendaftaran_id', ...)` branch used to catch.
    Tagihan::factory()->create([
        'pendaftaran_id' => $siswa->pendaftaran_asal_id,
        'tagihable_type' => Pendaftaran::class,
        'tagihable_id' => $siswa->pendaftaran_asal_id,
        'person_id' => $siswa->person_id,
        'total_tagihan' => 100000,
        'net_amount' => 100000,
        'status' => 'belum_bayar',
    ]);

    // "Siswa era" tagihan: tagihable_type=Siswa, matching $siswa->id. This is
    // what the old OR-hack's first where() branch used to catch.
    Tagihan::factory()->create([
        'pendaftaran_id' => null,
        'tagihable_type' => Siswa::class,
        'tagihable_id' => $siswa->id,
        'person_id' => $siswa->person_id,
        'total_tagihan' => 250000,
        'net_amount' => 250000,
        'status' => 'belum_bayar',
    ]);
}

it('sums tagihan from both the pendaftaran era and the siswa era via person_id on the siswa dashboard', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $pendaftaran = Pendaftaran::factory()->create();
    $siswa = Siswa::factory()->create(['pendaftaran_asal_id' => $pendaftaran->id]);

    buatTagihanDuaEra($siswa);

    $user = User::factory()->create();
    $user->assignRole('siswa');
    $siswa->person->update(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    // 100.000 (pendaftaran era) + 250.000 (siswa era) = 350.000, unified via person_id.
    $response->assertViewHas('tagihanBelumLunas', 350000);
});

it('sums tagihan from both the pendaftaran era and the siswa era via person_id on the orang_tua dashboard', function () {
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $pendaftaran = Pendaftaran::factory()->create();
    $siswa = Siswa::factory()->create(['pendaftaran_asal_id' => $pendaftaran->id]);

    buatTagihanDuaEra($siswa);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create(['user_id' => $orangTuaUser->id]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $response = $this->actingAs($orangTuaUser)->get('/dashboard');

    $response->assertOk();
    $response->assertViewHas('tagihanBelumLunas', 350000);
});
