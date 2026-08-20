<?php
// tests/Feature/KasusListingTest.php

use App\Domains\Kasus\Enums\StatusKasus;
use App\Models\Guru;
use App\Domains\Kasus\Models\Kasus;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

it('lets an orang tua kontak utama list and view their kasus end-to-end through submit, triase, and consent', function () {
    // Regression test for the reviewer's Finding 1+2: orang_tua accounts have a null
    // lembaga_id, so both KasusController::index() and ::show() previously let Siswa's
    // relation lazy-load fresh under TenantScope, nulling it out and breaking the page
    // (500 on index's implicit N+1, 404-via-false isKontakUtama on show). Walk the real
    // flow (orang tua submits -> admin triages -> orang tua views) to prove both are fixed.
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Orang Tua E2E']);
    [$user, $orangTua] = actingAsOrangTuaPengaju($siswa);

    $this->actingAs($user)->post(route('kasus.store'), [
        'siswa_id' => $siswa->id,
        'kategori_masalah' => 'Sosial',
        'deskripsi' => 'Deskripsi keluhan orang tua e2e.',
    ])->assertRedirect(route('kasus.index'));

    // Finding 1: index() must not 500 for an orang_tua actor.
    $indexResponse = $this->actingAs($user)->get(route('kasus.index'));
    $indexResponse->assertOk();
    $indexResponse->assertSee('Anak Orang Tua E2E');

    $kasus = Kasus::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
        ->where('siswa_id', $siswa->id)->firstOrFail();

    $guruBk = Guru::withoutGlobalScopes()->create([
        'user_id' => \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => fake()->unique()->numerify('################'),
        'nama' => 'Konselor BK E2E', 'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_bk',
        'status_kepegawaian' => 'GTY', 'status_aktif' => 'aktif',
    ]);
    $manager = actingAsKasusTriaseManager($lembaga);
    \Illuminate\Support\Facades\Notification::fake();
    $this->actingAs($manager)->post(route('admin.kasus.assign-konselor', $kasus), [
        'tingkat_urgensi' => 'sedang',
        'konselor_tipe' => 'guru',
        'konselor_id' => $guruBk->id,
    ])->assertRedirect(route('admin.kasus.index'));

    $kasus->refresh();
    expect($kasus->status)->toBe(StatusKasus::MenungguConsent);

    // Finding 2: show() must not 404 the kontak utama and must render the consent UI.
    $showResponse = $this->actingAs($user)->get(route('kasus.show', $kasus));
    $showResponse->assertOk();
    $showResponse->assertSee('Anak Orang Tua E2E');
    $showResponse->assertSee('Menunggu Persetujuan');
    // Finding (6th occurrence of the same bug class): orang tua must see the assigned
    // konselor's name for informed consent, not have it silently hidden by TenantScope.
    $showResponse->assertSee($guruBk->nama);
});

it('shows an orang tua kontak utama a kasus that a guru submitted, at every status', function () {
    // Bug found manually 2026-08-05: KasusController::index()'s orang_tua branch only
    // filtered by diajukan_oleh_orang_tua_id (who personally submitted it), so a kasus
    // submitted by the reporting TEACHER never appeared in the kontak-utama parent's own
    // list, at any status — not because of the status itself, but because that column is
    // always null when a guru (not the parent) filed the case.
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Diajukan Guru']);
    [$guruUser, $guru] = actingAsGuruPengaju($lembaga);
    [$ortuUser, $orangTua] = actingAsOrangTuaPengaju($siswa);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'diajukan_oleh_guru_id' => $guru->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Diajukan oleh guru, bukan orang tua.',
        'status' => StatusKasus::Selesai,
    ]);

    $response = $this->actingAs($ortuUser)->get(route('kasus.index'));

    $response->assertOk();
    $response->assertSee('Anak Diajukan Guru');
});

it('does not show a guru-submitted kasus to an orang tua who is not the kontak utama', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Bukan Kontak Utama']);
    [$guruUser, $guru] = actingAsGuruPengaju($lembaga);

    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('kasus.view');
    $bukanKontakUtamaUser = \App\Models\User::factory()->create(['lembaga_id' => null]);
    $bukanKontakUtamaUser->assignRole('orang_tua');
    $bukanKontakUtama = \App\Models\OrangTua::create([
        'user_id' => $bukanKontakUtamaUser->id, 'nama_lengkap' => 'Bukan Kontak Utama',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200009999',
    ]);
    $siswa->orangTua()->attach($bukanKontakUtama->id, ['hubungan' => 'ibu', 'is_kontak_utama' => false]);

    Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'diajukan_oleh_guru_id' => $guru->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Diajukan oleh guru.',
    ]);

    $response = $this->actingAs($bukanKontakUtamaUser)->get(route('kasus.index'));

    $response->assertOk();
    $response->assertDontSee('Anak Bukan Kontak Utama');
});
