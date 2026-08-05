<?php
// tests/Feature/Admin/KasusTriaseTest.php

use App\Enums\StatusKasus;
use App\Models\Guru;
use App\Models\Kasus;
use App\Models\KasusConsent;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use App\Notifications\KonselorDipilihNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

function actingAsKasusTriaseManager(Lembaga $lembaga): User
{
    foreach (['kasus.view', 'kasus.triase'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kasus.view', 'kasus.triase']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('assigns a guru_bk konselor from the resolver, moves status to menunggu_consent, and creates two consent rows', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKasusTriaseManager($lembaga);

    $guruBk = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => fake()->unique()->numerify('################'),
        'nama' => 'Konselor BK', 'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_bk',
        'status_kepegawaian' => 'GTY', 'status_aktif' => 'aktif',
    ]);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser->assignRole('orang_tua');
    $kontakUtama = OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Kontak Utama',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200003333',
        'email' => 'kontak.utama.triase@example.test',
    ]);
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
    ]);

    Notification::fake();

    $this->actingAs($manager)->post(route('admin.kasus.assign-konselor', $kasus), [
        'tingkat_urgensi' => 'sedang',
        'konselor_tipe' => 'guru',
        'konselor_id' => $guruBk->id,
    ])->assertRedirect(route('admin.kasus.index'));

    $kasus->refresh();
    expect($kasus->status)->toBe(StatusKasus::MenungguConsent);
    expect($kasus->tingkat_urgensi)->toBe('sedang');
    expect($kasus->konselor_guru_id)->toBe($guruBk->id);
    expect($kasus->konselor_karyawan_id)->toBeNull();
    expect(KasusConsent::where('kasus_id', $kasus->id)->count())->toBe(2);
    expect(KasusConsent::where('kasus_id', $kasus->id)->where('jenis', 'sesi_pendampingan')->where('status', 'menunggu')->exists())->toBeTrue();
    expect(KasusConsent::where('kasus_id', $kasus->id)->where('jenis', 'pengumpulan_media')->where('status', 'menunggu')->exists())->toBeTrue();

    Notification::assertSentTo($kontakUtama, KonselorDipilihNotification::class);
});

it('rejects triase from an admin without kasus.triase permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
    ]);
    $noPermissionUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noPermissionUser)->post(route('admin.kasus.assign-konselor', $kasus), [
        'tingkat_urgensi' => 'rendah', 'konselor_tipe' => 'guru', 'konselor_id' => 1,
    ])->assertForbidden();
});

it('404s an admin_akademik from another lembaga trying to assign a konselor to a kasus that is not theirs', function () {
    // Regression test for Finding 3: the global Route::bind('kasus', ...) added earlier in
    // this sub-project bypasses TenantScope for the {kasus} route parameter everywhere, so
    // without an explicit lembaga guard inside the controller, an admin_akademik from
    // Lembaga A could triage/assign a konselor to a kasus belonging to Lembaga B.
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kasus = Kasus::create([
        'siswa_id' => $siswaB->id, 'lembaga_id' => $lembagaB->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Milik lembaga B.',
    ]);

    $guruBkLembagaB = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaB->id])->id,
        'lembaga_id' => $lembagaB->id, 'nik' => fake()->unique()->numerify('################'),
        'nama' => 'Konselor BK Lembaga B', 'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_bk',
        'status_kepegawaian' => 'GTY', 'status_aktif' => 'aktif',
    ]);

    $managerA = actingAsKasusTriaseManager($lembagaA);

    $this->actingAs($managerA)->get(route('admin.kasus.triase', $kasus))->assertNotFound();

    $this->actingAs($managerA)->post(route('admin.kasus.assign-konselor', $kasus), [
        'tingkat_urgensi' => 'tinggi',
        'konselor_tipe' => 'guru',
        'konselor_id' => $guruBkLembagaB->id,
    ])->assertNotFound();

    // A scoped read-back would be vacuous here (managerA's own TenantScope already hides
    // lembaga B's row), so bypass it explicitly to prove the row was genuinely untouched.
    $kasus = Kasus::withoutGlobalScope(TenantScope::class)->findOrFail($kasus->id);
    expect($kasus->status)->toBe(StatusKasus::Diajukan);
    expect($kasus->konselor_guru_id)->toBeNull();
    expect($kasus->konselor_karyawan_id)->toBeNull();
});

it('rejects a konselor_id that is not in the resolver\'s candidate list for the siswa', function () {
    // Regression test for Finding 4: assignKonselor() previously accepted any integer for
    // konselor_id without checking it against KonselorAllocationResolver's actual candidates.
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKasusTriaseManager($lembaga);

    // A real Guru row exists, but is not guru_bk -- so it must never appear as a valid
    // candidate for konselor_tipe = guru.
    $guruBukanBk = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => fake()->unique()->numerify('################'),
        'nama' => 'Guru Bukan BK', 'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY', 'status_aktif' => 'aktif',
    ]);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
    ]);

    $this->actingAs($manager)->post(route('admin.kasus.assign-konselor', $kasus), [
        'tingkat_urgensi' => 'sedang',
        'konselor_tipe' => 'guru',
        'konselor_id' => $guruBukanBk->id,
    ])->assertStatus(422);

    $kasus->refresh();
    expect($kasus->status)->toBe(StatusKasus::Diajukan);
    expect($kasus->konselor_guru_id)->toBeNull();
    expect(KasusConsent::where('kasus_id', $kasus->id)->count())->toBe(0);
});

it('renders the triase index page with KPI statistics and the triase assignment page with workload indicators', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Siswa Uji Triase']);
    $manager = actingAsKasusTriaseManager($lembaga);

    $guruBk = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => fake()->unique()->numerify('################'),
        'nama' => 'Konselor BK Triase View', 'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_bk',
        'status_kepegawaian' => 'GTY', 'status_aktif' => 'aktif',
        'kapasitas_kasus_aktif' => 10,
    ]);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Akademik dan Perilaku', 'deskripsi' => 'Uji coba view triase.',
    ]);

    $this->actingAs($manager)->get(route('admin.kasus.index'))
        ->assertOk()
        ->assertSee('Triase Kasus Pendampingan')
        ->assertSee('Siswa Uji Triase');

    $this->actingAs($manager)->get(route('admin.kasus.triase', $kasus))
        ->assertOk()
        ->assertSee('Triase: Siswa Uji Triase')
        ->assertSee('Konselor BK Triase View')
        ->assertSee('Beban Kerja')
        ->assertSee('/ 10');
});

it('sends the consent-diminta notification via whatsapp with the rendered template', function () {
    \Illuminate\Support\Facades\Http::fake(['api.fonnte.com/*' => \Illuminate\Support\Facades\Http::response(['status' => true], 200)]);
    \App\Models\WhatsAppTemplate::firstOrCreate(
        ['kode' => 'consent_diminta'],
        ['isi_template' => 'Konselor {nama_konselor} dipilih untuk {nama_siswa}.']
    );

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Budi Santoso']);
    $manager = actingAsKasusTriaseManager($lembaga);

    $guruBk = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => fake()->unique()->numerify('################'),
        'nama' => 'Konselor Whatsapp', 'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_bk',
        'status_kepegawaian' => 'GTY', 'status_aktif' => 'aktif',
    ]);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser->assignRole('orang_tua');
    $kontakUtama = OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu WhatsApp',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200009900',
        'email' => 'kontak.wa@example.test',
    ]);
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
    ]);

    $this->actingAs($manager)->post(route('admin.kasus.assign-konselor', $kasus), [
        'tingkat_urgensi' => 'sedang', 'konselor_tipe' => 'guru', 'konselor_id' => $guruBk->id,
    ]);

    \Illuminate\Support\Facades\Http::assertSent(function ($request) {
        return $request->url() === 'https://api.fonnte.com/send'
            && $request['target'] === '081200009900'
            && str_contains($request['message'], 'Konselor Whatsapp')
            && str_contains($request['message'], 'Budi Santoso');
    });
});

it('does NOT call Fonnte when the consent-diminta template row is missing', function () {
    \Illuminate\Support\Facades\Http::fake();
    \App\Models\WhatsAppTemplate::where('kode', 'consent_diminta')->delete();

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKasusTriaseManager($lembaga);
    $guruBk = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => fake()->unique()->numerify('################'),
        'nama' => 'Konselor Tanpa Template', 'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_bk',
        'status_kepegawaian' => 'GTY', 'status_aktif' => 'aktif',
    ]);
    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser->assignRole('orang_tua');
    $kontakUtama = OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Tanpa Template',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200008800',
        'email' => 'ortu.tanpa.template@example.test',
    ]);
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);
    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
    ]);

    $this->actingAs($manager)->post(route('admin.kasus.assign-konselor', $kasus), [
        'tingkat_urgensi' => 'sedang', 'konselor_tipe' => 'guru', 'konselor_id' => $guruBk->id,
    ]);

    \Illuminate\Support\Facades\Http::assertNothingSent();
});

it('still delivers database and mail when the Fonnte call fails', function () {
    // Spec: "kegagalan channel WhatsApp TIDAK menggagalkan pengiriman channel lain" — this
    // proves it against the REAL multi-channel notification (not the throwaway one from
    // Task 2's WhatsAppChannelTest), using Http::fake() plus a real (non-faked) mail send
    // rather than Notification::fake() (which would skip real channel execution entirely
    // and prove nothing here).
    //
    // Deliberate deviation from the brief's literal Mail::fake() + Mail::assertSent(...):
    // Illuminate\Notifications\Channels\MailChannel, when toMail() returns a Mailable
    // instance (as KonselorDipilihMail does, following the existing codebase pattern used
    // by all 13 Mailables), calls `$message->send($mailer)`. Illuminate\Mail\Mailable::send()
    // then calls `$mailer->send($this->buildView(), ...)` — i.e. it passes the *compiled
    // view*, not `$this`, to the mailer. Illuminate\Support\Testing\Fakes\MailFake::sendMail()
    // only records a mailable when the argument it receives `instanceof Mailable`, so this
    // path is never recorded by Mail::fake() and Mail::assertSent(KonselorDipilihMail::class)
    // fails every time, regardless of whether mail actually sent. Verified directly against
    // the vendored Laravel source and confirmed empirically (Http/database assertions below
    // passed while the literal Mail::assertSent() line failed). Fixing this generically would
    // mean reworking how all 13 Mailable-returning notifications in this app are dispatched —
    // out of scope for a WhatsApp channel task. Instead we let mail send for real (phpunit.xml
    // sets MAIL_MAILER=array) and assert against the array transport directly, which proves
    // the same thing the brief's spec requires without relying on the broken fake path.
    \Illuminate\Support\Facades\Http::fake(['api.fonnte.com/*' => \Illuminate\Support\Facades\Http::response(['status' => false], 500)]);
    \App\Models\WhatsAppTemplate::firstOrCreate(
        ['kode' => 'consent_diminta'],
        ['isi_template' => 'Konselor {nama_konselor} dipilih untuk {nama_siswa}.']
    );

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKasusTriaseManager($lembaga);
    $guruBk = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => fake()->unique()->numerify('################'),
        'nama' => 'Konselor Gagal WA', 'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_bk',
        'status_kepegawaian' => 'GTY', 'status_aktif' => 'aktif',
    ]);
    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser->assignRole('orang_tua');
    $kontakUtama = OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Gagal WA',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200006600',
        'email' => 'ortu.gagal.wa@example.test',
    ]);
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);
    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
    ]);

    $this->actingAs($manager)->post(route('admin.kasus.assign-konselor', $kasus), [
        'tingkat_urgensi' => 'sedang', 'konselor_tipe' => 'guru', 'konselor_id' => $guruBk->id,
    ]);

    // WhatsApp was attempted (and failed) — but database and mail still succeeded.
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request->url() === 'https://api.fonnte.com/send');
    expect($kontakUtama->notifications()->where('type', \App\Notifications\KonselorDipilihNotification::class)->exists())->toBeTrue();

    $sentMessages = app('mailer')->getSymfonyTransport()->messages();
    expect($sentMessages->count())->toBeGreaterThan(0);
    $lastMessage = $sentMessages->last()->getOriginalMessage();
    expect($lastMessage->getTo()[0]->getAddress())->toBe('ortu.gagal.wa@example.test');
});

it('does not 500 when assigning a konselor for an orang tua kontak utama with no email, and still attempts whatsapp', function () {
    // Regression test for the final whole-branch review's Finding 1/2: OrangTua.email is
    // nullable while no_hp is NOT NULL, so "has a phone but no email" is a normal record --
    // exactly the record this WhatsApp feature exists to serve. The old
    // method_exists($notifiable, 'routeNotificationForMail') guard in toMail() still returned
    // a Mailable with no recipient set when $email was null, and MailChannel::send() sends it
    // regardless, throwing LogicException("An email must have a To, Cc, or Bcc header") and
    // 500ing the whole admin action -- after the DB transaction had already committed.
    \Illuminate\Support\Facades\Http::fake(['api.fonnte.com/*' => \Illuminate\Support\Facades\Http::response(['status' => true], 200)]);
    \App\Models\WhatsAppTemplate::firstOrCreate(
        ['kode' => 'consent_diminta'],
        ['isi_template' => 'Konselor {nama_konselor} dipilih untuk {nama_siswa}.']
    );

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Siswa Tanpa Email Ortu']);
    $manager = actingAsKasusTriaseManager($lembaga);

    $guruBk = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => fake()->unique()->numerify('################'),
        'nama' => 'Konselor Tanpa Email Ortu', 'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_bk',
        'status_kepegawaian' => 'GTY', 'status_aktif' => 'aktif',
    ]);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser->assignRole('orang_tua');
    $kontakUtama = OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Tanpa Email',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200005500',
        'email' => null,
    ]);
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
    ]);

    $this->actingAs($manager)->post(route('admin.kasus.assign-konselor', $kasus), [
        'tingkat_urgensi' => 'sedang', 'konselor_tipe' => 'guru', 'konselor_id' => $guruBk->id,
    ])->assertRedirect(route('admin.kasus.index'));

    $kasus->refresh();
    expect($kasus->status)->toBe(StatusKasus::MenungguConsent);
    expect($kontakUtama->notifications()->where('type', KonselorDipilihNotification::class)->exists())->toBeTrue();

    \Illuminate\Support\Facades\Http::assertSent(function ($request) {
        return $request->url() === 'https://api.fonnte.com/send'
            && $request['target'] === '081200005500'
            && str_contains($request['message'], 'Siswa Tanpa Email Ortu');
    });
});

