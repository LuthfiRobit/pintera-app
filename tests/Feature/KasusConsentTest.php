<?php

use App\Enums\StatusKasus;
use App\Models\Guru;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusConsent;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use App\Notifications\ConsentDisetujuiNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

function siapkanKasusMenungguConsent(?Guru $guruPengaju = null, ?Lembaga $lembaga = null): array
{
    if ($lembaga === null) {
        $yayasan = Yayasan::factory()->create();
        $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    }
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    foreach (['kasus.consent', 'kasus.view'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kasus.consent', 'kasus.view']);

    $kontakUtamaUser = User::factory()->create(['lembaga_id' => null]);
    $kontakUtamaUser->assignRole($role);
    $kontakUtama = OrangTua::create([
        'user_id' => $kontakUtamaUser->id, 'nama_lengkap' => 'Kontak Utama',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200004444',
        'email' => 'kontak.consent@example.test',
    ]);
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $nonKontakUtamaUser = User::factory()->create(['lembaga_id' => null]);
    $nonKontakUtamaUser->assignRole($role);
    $nonKontakUtama = OrangTua::create([
        'user_id' => $nonKontakUtamaUser->id, 'nama_lengkap' => 'Bukan Kontak Utama',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200005555',
    ]);
    $siswa->orangTua()->attach($nonKontakUtama->id, ['hubungan' => 'ayah', 'is_kontak_utama' => false]);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
        'status' => StatusKasus::MenungguConsent,
        'diajukan_oleh_guru_id' => $guruPengaju?->id,
    ]);
    $sesiConsent = KasusConsent::create(['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan']);
    $mediaConsent = KasusConsent::create(['kasus_id' => $kasus->id, 'jenis' => 'pengumpulan_media']);

    return [$kasus, $sesiConsent, $mediaConsent, $kontakUtamaUser, $nonKontakUtamaUser];
}

it('moves kasus status to ditugaskan when the kontak utama approves sesi_pendampingan, even if pengumpulan_media is still pending', function () {
    [$kasus, $sesiConsent, $mediaConsent, $kontakUtamaUser] = siapkanKasusMenungguConsent();

    $this->actingAs($kontakUtamaUser)->patch(route('kasus.consent.approve', [$kasus, $sesiConsent]))
        ->assertRedirect();

    $kasus->refresh();
    $sesiConsent->refresh();
    $mediaConsent->refresh();

    expect($kasus->status)->toBe(StatusKasus::Ditugaskan);
    expect($sesiConsent->status)->toBe('disetujui');
    expect($sesiConsent->disetujui_at)->not->toBeNull();
    expect($mediaConsent->status)->toBe('menunggu');
});

it('approving pengumpulan_media alone does not move the kasus to ditugaskan', function () {
    [$kasus, $sesiConsent, $mediaConsent, $kontakUtamaUser] = siapkanKasusMenungguConsent();

    $this->actingAs($kontakUtamaUser)->patch(route('kasus.consent.approve', [$kasus, $mediaConsent]))
        ->assertRedirect();

    $kasus->refresh();
    expect($kasus->status)->toBe(StatusKasus::MenungguConsent);
});

it('rejects consent approval from an orang tua who is linked but not kontak utama', function () {
    [$kasus, $sesiConsent, , , $nonKontakUtamaUser] = siapkanKasusMenungguConsent();

    $this->actingAs($nonKontakUtamaUser)->patch(route('kasus.consent.approve', [$kasus, $sesiConsent]))
        ->assertForbidden();

    expect($sesiConsent->fresh()->status)->toBe('menunggu');
});

it('notifies the submitting guru and lembaga admins when sesi_pendampingan consent is approved', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruPengaju = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => fake()->unique()->numerify('################'),
        'nama' => 'Guru Pengaju', 'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_bk',
        'status_kepegawaian' => 'GTY', 'status_aktif' => 'aktif',
    ]);

    [$kasus, $sesiConsent, , $kontakUtamaUser] = siapkanKasusMenungguConsent($guruPengaju, $lembaga);

    Permission::firstOrCreate(['name' => 'kasus.triase', 'guard_name' => 'web']);
    $adminRole = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $adminRole->givePermissionTo('kasus.triase');
    $lembagaAdmin = User::factory()->create(['lembaga_id' => $kasus->lembaga_id]);
    $lembagaAdmin->assignRole($adminRole);

    Notification::fake();

    $this->actingAs($kontakUtamaUser)->patch(route('kasus.consent.approve', [$kasus, $sesiConsent]));

    Notification::assertSentTo($lembagaAdmin, ConsentDisetujuiNotification::class);
    Notification::assertSentTo($guruPengaju, ConsentDisetujuiNotification::class);
});

it('renders the consent-approved notification for real without a fake, using the already-scoped siswa relation', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruPengaju = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => fake()->unique()->numerify('################'),
        'nama' => 'Guru Pengaju Nyata', 'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_bk',
        'status_kepegawaian' => 'GTY', 'status_aktif' => 'aktif',
        'email' => 'guru.pengaju.nyata@example.test',
    ]);

    [$kasus, $sesiConsent, , $kontakUtamaUser] = siapkanKasusMenungguConsent($guruPengaju, $lembaga);

    Mail::fake();

    $this->actingAs($kontakUtamaUser)
        ->patch(route('kasus.consent.approve', [$kasus, $sesiConsent]))
        ->assertRedirect();
});

it('does not 500 when notifying ConsentDisetujuiNotification for real (no Notification::fake)', function () {
    // Regression test for the same MailChannel::send() bug fixed for KonselorDipilihMail
    // and SesiReminderMail: toMail() returning a bare Mailable with no ->to() throws
    // LogicException("An email must have a To, Cc, or Bcc header") the instant a real
    // notifiable (with a real email) is notified outside Notification::fake().
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruPengaju = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => fake()->unique()->numerify('################'),
        'nama' => 'Guru Pengaju Konsent Real', 'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_bk',
        'status_kepegawaian' => 'GTY', 'status_aktif' => 'aktif',
        'email' => 'guru.pengaju.konsent.real@example.test',
    ]);

    [$kasus, $sesiConsent, , $kontakUtamaUser] = siapkanKasusMenungguConsent($guruPengaju, $lembaga);

    $this->actingAs($kontakUtamaUser)
        ->patch(route('kasus.consent.approve', [$kasus, $sesiConsent]))
        ->assertRedirect();
});
