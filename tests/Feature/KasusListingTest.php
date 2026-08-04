<?php
// tests/Feature/KasusListingTest.php

use App\Models\Kasus;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\Yayasan;

it('shows a guru only the kasus they submitted, not another guru\'s', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaA = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Siswa Kasus Sendiri']);
    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Siswa Kasus Guru Lain']);
    [$user, $guru] = actingAsGuruPengaju($lembaga);
    $otherUser = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $otherGuru = \App\Models\Guru::create([
        'user_id' => $otherUser->id, 'lembaga_id' => $lembaga->id,
        'nik' => fake()->unique()->numerify('################'), 'nama' => 'Guru Lain',
        'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
    ]);

    Kasus::create([
        'siswa_id' => $siswaA->id, 'lembaga_id' => $lembaga->id, 'diajukan_oleh_guru_id' => $guru->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Punya sendiri.',
    ]);
    Kasus::create([
        'siswa_id' => $siswaB->id, 'lembaga_id' => $lembaga->id, 'diajukan_oleh_guru_id' => $otherGuru->id,
        'kategori_masalah' => 'Akademik', 'deskripsi' => 'Punya guru lain.',
    ]);

    $response = $this->actingAs($user)->get(route('kasus.index'));

    $response->assertOk();
    $response->assertSee('Siswa Kasus Sendiri');
    $response->assertDontSee('Siswa Kasus Guru Lain');
});

it('shows the submitting guru their own kasus detail page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Siswa Detail']);
    [$user, $guru] = actingAsGuruPengaju($lembaga);
    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'diajukan_oleh_guru_id' => $guru->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Deskripsi lengkap kasus.',
    ]);

    $response = $this->actingAs($user)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertSee('Siswa Detail');
    $response->assertSee('Deskripsi lengkap kasus.');
});

it('404s when an unrelated guru tries to view another guru\'s kasus detail', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    [$ownerUser, $ownerGuru] = actingAsGuruPengaju($lembaga);
    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'diajukan_oleh_guru_id' => $ownerGuru->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Rahasia.',
    ]);

    $otherUser = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $otherRole = \App\Models\Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $otherUser->assignRole($otherRole);
    \App\Models\Guru::create([
        'user_id' => $otherUser->id, 'lembaga_id' => $lembaga->id,
        'nik' => fake()->unique()->numerify('################'), 'nama' => 'Guru Tidak Terkait',
        'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
    ]);

    $this->actingAs($otherUser)->get(route('kasus.show', $kasus))->assertNotFound();
});
