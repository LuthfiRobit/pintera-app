<?php

// tests/Feature/KasusSubmissionTest.php

use App\Enums\StatusKasus;
use App\Models\Guru;
use App\Domains\Kasus\Models\Kasus;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use App\Notifications\KasusDiajukanNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

function actingAsGuruPengaju(Lembaga $lembaga): array
{
    foreach (['kasus.ajukan', 'kasus.view'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kasus.ajukan', 'kasus.view']);

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('guru');
    $guru = Guru::create([
        'user_id' => $user->id, 'lembaga_id' => $lembaga->id,
        'nik' => fake()->unique()->numerify('################'), 'nama' => 'Guru Pengaju',
        'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
        'email' => 'guru.pengaju@example.test',
    ]);

    return [$user, $guru];
}

function actingAsOrangTuaPengaju(Siswa $siswa): array
{
    foreach (['kasus.ajukan', 'kasus.view'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kasus.ajukan', 'kasus.view']);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Orang Tua Pengaju',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200001111',
        'email' => 'ortu.pengaju@example.test',
    ]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    return [$user, $orangTua];
}

it('shows the create form with the orang tua\'s linked children in the siswa dropdown', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Terdaftar']);
    [$user, $orangTua] = actingAsOrangTuaPengaju($siswa);

    $response = $this->actingAs($user)->get(route('kasus.create'));

    $response->assertOk();
    $response->assertSee('Anak Terdaftar');
});

it('lets a guru submit a kasus and notifies the kontak utama orang tua', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    [$user, $guru] = actingAsGuruPengaju($lembaga);

    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    $orangTuaUser->assignRole('orang_tua');
    $kontakUtama = OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Kontak Utama',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200002222',
        'email' => 'kontak.utama@example.test',
    ]);
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    Notification::fake();

    $this->actingAs($user)->post(route('kasus.store'), [
        'siswa_id' => $siswa->id,
        'kategori_masalah' => 'Perilaku',
        'deskripsi' => 'Deskripsi observasi guru.',
    ])->assertRedirect(route('kasus.index'));

    $kasus = Kasus::where('siswa_id', $siswa->id)->firstOrFail();
    expect($kasus->status)->toBe(StatusKasus::Diajukan);
    expect($kasus->diajukan_oleh_guru_id)->toBe($guru->id);
    expect($kasus->diajukan_oleh_orang_tua_id)->toBeNull();
    expect($kasus->lembaga_id)->toBe($lembaga->id);

    Notification::assertSentTo($kontakUtama, KasusDiajukanNotification::class);
});

it('does not 500 when notifying KasusDiajukanNotification for real (no Notification::fake)', function () {
    // Regression test for the same MailChannel::send() bug fixed for KonselorDipilihMail
    // and SesiReminderMail: toMail() returning a bare Mailable with no ->to() throws
    // LogicException("An email must have a To, Cc, or Bcc header") the instant a real
    // notifiable (with a real email) is notified outside Notification::fake().
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    [$user] = actingAsGuruPengaju($lembaga);

    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    $orangTuaUser->assignRole('orang_tua');
    $kontakUtama = OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Kontak Utama Real',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200002233',
        'email' => 'kontak.utama.real@example.test',
    ]);
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $this->actingAs($user)->post(route('kasus.store'), [
        'siswa_id' => $siswa->id,
        'kategori_masalah' => 'Perilaku',
        'deskripsi' => 'Deskripsi observasi guru.',
    ])->assertRedirect(route('kasus.index'));
});

it('lets an orang tua submit a kasus for their own linked child and notifies the wali kelas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    [$waliUser, $waliGuru] = actingAsGuruPengaju($lembaga);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'wali_kelas_guru_id' => $waliGuru->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    [$user, $orangTua] = actingAsOrangTuaPengaju($siswa);

    Notification::fake();

    $this->actingAs($user)->post(route('kasus.store'), [
        'siswa_id' => $siswa->id,
        'kategori_masalah' => 'Sosial',
        'deskripsi' => 'Deskripsi keluhan orang tua.',
    ])->assertRedirect(route('kasus.index'));

    // The still-authenticated actor here is the orang_tua, whose null lembaga_id makes the
    // default TenantScope fail-closed on Kasus (a real, non-null lembaga_id row can never
    // satisfy `lembaga_id IS NULL`). Bypass it explicitly for this read-back assertion, same
    // as the controller does for its own orang_tua-facing queries.
    $kasus = Kasus::withoutGlobalScope(TenantScope::class)->where('siswa_id', $siswa->id)->firstOrFail();
    expect($kasus->diajukan_oleh_orang_tua_id)->toBe($orangTua->id);
    expect($kasus->diajukan_oleh_guru_id)->toBeNull();

    Notification::assertSentTo($waliGuru, KasusDiajukanNotification::class);
});

it('rejects an orang tua submitting a kasus for a siswa they are not linked to', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $unrelatedSiswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    [$user, $orangTua] = actingAsOrangTuaPengaju($siswa);

    $this->actingAs($user)->post(route('kasus.store'), [
        'siswa_id' => $unrelatedSiswa->id,
        'kategori_masalah' => 'Perilaku',
        'deskripsi' => 'Percobaan.',
    ])->assertNotFound();

    // The still-authenticated orang_tua actor's own null lembaga_id would make the default
    // TenantScope fail-closed to zero rows regardless of whether the bug this test guards
    // against is present, so this assertion must bypass the scope to be meaningful.
    expect(Kasus::withoutGlobalScope(TenantScope::class)->where('siswa_id', $unrelatedSiswa->id)->exists())->toBeFalse();
});

it('rejects a guru submitting a kasus for a siswa in a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaLembagaB = Siswa::factory()->create(['lembaga_id' => $lembagaB->id]);
    [$user, $guru] = actingAsGuruPengaju($lembagaA);

    $this->actingAs($user)->post(route('kasus.store'), [
        'siswa_id' => $siswaLembagaB->id,
        'kategori_masalah' => 'Perilaku',
        'deskripsi' => 'Percobaan lintas lembaga.',
    ])->assertNotFound();

    // Same reasoning as above: the acting guru's TenantScope would already exclude a row
    // written under a different lembaga, so bypass it to make this a real assertion.
    expect(Kasus::withoutGlobalScope(TenantScope::class)->where('siswa_id', $siswaLembagaB->id)->exists())->toBeFalse();
});

it('guru submission always leaves diajukan_oleh_orang_tua_id null (FK exclusivity is structural, not separately validated)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    [$user, $guru] = actingAsGuruPengaju($lembaga);

    // A direct submission always fills exactly one column via the controller; this test
    // documents that expectation by checking the successful guru-submission path leaves
    // diajukan_oleh_orang_tua_id null (the "both empty" / "both filled" cases are structurally
    // unreachable through the controller, since it always fills exactly one based on role).
    $this->actingAs($user)->post(route('kasus.store'), [
        'siswa_id' => $siswa->id, 'kategori_masalah' => 'Akademik', 'deskripsi' => 'Contoh.',
    ]);

    $kasus = Kasus::where('siswa_id', $siswa->id)->firstOrFail();
    expect($kasus->diajukan_oleh_guru_id)->not->toBeNull();
    expect($kasus->diajukan_oleh_orang_tua_id)->toBeNull();
});
