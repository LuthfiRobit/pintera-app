<?php
// tests/Feature/KasusTugasBeriTest.php

use App\Enums\StatusKasus;
use App\Models\Guru;
use App\Models\Kasus;
use App\Models\KasusTugas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use App\Notifications\TugasBatchDibuatNotification;
use Spatie\Permission\Models\Permission;

// Declared with a distinct name from the other tests/Feature/*.php files' own
// buatKasusDitugaskanKeGuruBk*() helpers — Pest/PHPUnit loads all Feature test files
// into the same PHP process, so re-declaring a same-named top-level function across
// files is a fatal "Cannot redeclare" error, not something Pest scopes per file.
function buatKasusDitugaskanKeGuruBkUntukTugas(Lembaga $lembaga): array
{
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $konselorUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kasus.view']);
    $konselorUser->assignRole('guru');
    $guruBk = Guru::withoutGlobalScopes()->create([
        'user_id' => $konselorUser->id, 'lembaga_id' => $lembaga->id,
        'nik' => fake()->unique()->numerify('################'), 'nama' => 'Konselor BK',
        'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_bk', 'status_kepegawaian' => 'GTY',
        'status_aktif' => 'aktif',
    ]);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
        'status' => StatusKasus::Ditugaskan, 'konselor_guru_id' => $guruBk->id,
    ]);

    return [$kasus, $konselorUser, $siswa];
}

it('lets the assigned konselor give a sekali tugas', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), [
        'judul' => 'Refleksi Mingguan',
        'instruksi' => 'Tulis refleksi kegiatan minggu ini.',
        'frekuensi' => 'sekali',
        'tanggal_mulai' => now()->toDateString(),
        'tanggal_selesai' => now()->addDays(3)->toDateString(),
    ])->assertRedirect(route('kasus.show', $kasus));

    expect(KasusTugas::where('kasus_id', $kasus->id)->count())->toBe(1);
});

it('generates multiple tugas rows sharing one batch_id for a harian submission', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), [
        'judul' => 'Jurnal Emosi', 'instruksi' => 'Tulis jurnal harian.', 'frekuensi' => 'harian',
        'tanggal_mulai' => '2026-08-10', 'tanggal_selesai' => '2026-08-12',
    ])->assertRedirect(route('kasus.show', $kasus));

    $barisBatch = KasusTugas::where('kasus_id', $kasus->id)->orderBy('batch_urutan')->get();
    expect($barisBatch)->toHaveCount(3);
    expect($barisBatch->pluck('batch_id')->unique())->toHaveCount(1);
    expect($barisBatch->pluck('batch_total')->unique()->first())->toBe(3);
    expect($barisBatch->pluck('batch_urutan')->all())->toBe([1, 2, 3]);
});

it('rejects an invalid payload and creates no row', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), [
        'judul' => '', 'instruksi' => 'Y', 'frekuensi' => 'sekali',
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->toDateString(),
    ])->assertSessionHasErrors('judul');

    expect(KasusTugas::where('kasus_id', $kasus->id)->count())->toBe(0);
});

it('403s a konselor who is not assigned from giving tugas', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);
    [, $unrelatedKonselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $this->actingAs($unrelatedKonselorUser)->post(route('kasus.tugas.store', $kasus), [
        'judul' => 'x', 'instruksi' => 'x', 'frekuensi' => 'sekali',
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->addDays(3)->toDateString(),
    ])->assertForbidden();
});

it('notifies siswa and orang tua once for the whole batch when a tugas is given', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser, $siswa] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $siswaUser = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa->update(['user_id' => $siswaUser->id]);

    $orangTuaUser = \App\Models\User::factory()->create(['lembaga_id' => null]);
    \App\Models\Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = \App\Models\OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Tugas',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200007777',
        'email' => 'ortu.tugas@example.test',
    ]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    \Illuminate\Support\Facades\Notification::fake();

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), [
        'judul' => 'Jurnal Harian', 'instruksi' => 'x', 'frekuensi' => 'harian',
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->addDays(2)->toDateString(),
    ]);

    \Illuminate\Support\Facades\Notification::assertSentTimes(TugasBatchDibuatNotification::class, 2);
    \Illuminate\Support\Facades\Notification::assertSentTo($siswaUser, TugasBatchDibuatNotification::class);
    \Illuminate\Support\Facades\Notification::assertSentTo($orangTua, TugasBatchDibuatNotification::class);
});

it('does not 500 when notifying TugasBatchDibuatNotification for real (no Notification::fake)', function () {
    // Regression test for the same MailChannel::send() bug fixed for KonselorDipilihMail
    // and SesiReminderMail: toMail() returning a bare Mailable with no ->to() throws
    // LogicException("An email must have a To, Cc, or Bcc header") the instant a real
    // notifiable (with a real email) is notified outside Notification::fake(). Uses a
    // multi-row (harian, 3 hari) batch so the real-mail path is exercised for the
    // many-rows-under-one-notification case, not just a single-row batch.
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser, $siswa] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $siswaUser = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa->update(['user_id' => $siswaUser->id]);

    $orangTuaUser = \App\Models\User::factory()->create(['lembaga_id' => null]);
    \App\Models\Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = \App\Models\OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Tugas Real',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200007799',
        'email' => 'ortu.tugas.real@example.test',
    ]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), [
        'judul' => 'Jurnal Harian', 'instruksi' => 'x', 'frekuensi' => 'harian',
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->addDays(2)->toDateString(),
    ])->assertRedirect(route('kasus.show', $kasus));
});

it('does not 500 when notifying TugasSelesaiNotification for real (no Notification::fake)', function () {
    // Regression test for the same MailChannel::send() bug fixed for KonselorDipilihMail
    // and SesiReminderMail: toMail() returning a bare Mailable with no ->to() throws
    // LogicException("An email must have a To, Cc, or Bcc header") the instant a real
    // notifiable (with a real email) is notified outside Notification::fake().
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser, $siswa] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $orangTuaUser = \App\Models\User::factory()->create(['lembaga_id' => null]);
    \App\Models\Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = \App\Models\OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Tugas Selesai Real',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200007700',
        'email' => 'ortu.tugas.selesai.real@example.test',
    ]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id, 'status' => 'dikerjakan']);

    $this->actingAs($konselorUser)->patch(route('kasus.tugas.selesai', [$kasus, $tugas]))
        ->assertRedirect(route('kasus.show', $kasus));
});

it('403s a POST to give tugas against an already-selesai kasus and creates no row', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);
    $kasus->update(['status' => StatusKasus::Selesai]);

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), [
        'judul' => 'x', 'instruksi' => 'x', 'frekuensi' => 'sekali',
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->addDays(3)->toDateString(),
    ])->assertForbidden();

    expect(KasusTugas::where('kasus_id', $kasus->id)->count())->toBe(0);
});

it('403s a POST to give tugas against a kasus still menunggu_consent and creates no row', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);
    $kasus->update(['status' => StatusKasus::MenungguConsent]);

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), [
        'judul' => 'x', 'instruksi' => 'x', 'frekuensi' => 'sekali',
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->addDays(3)->toDateString(),
    ])->assertForbidden();

    expect(KasusTugas::where('kasus_id', $kasus->id)->count())->toBe(0);
});

it('403s a POST to give tugas against a kasus still diajukan and creates no row', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);
    $kasus->update(['status' => StatusKasus::Diajukan]);

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), [
        'judul' => 'x', 'instruksi' => 'x', 'frekuensi' => 'sekali',
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->addDays(3)->toDateString(),
    ])->assertForbidden();

    expect(KasusTugas::where('kasus_id', $kasus->id)->count())->toBe(0);
});

it('lets the assigned konselor give tugas against a berjalan kasus', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);
    $kasus->update(['status' => StatusKasus::Berjalan]);

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), [
        'judul' => 'x', 'instruksi' => 'x', 'frekuensi' => 'sekali',
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->addDays(3)->toDateString(),
    ])->assertRedirect(route('kasus.show', $kasus));

    expect(KasusTugas::where('kasus_id', $kasus->id)->count())->toBe(1);
});

it('does not call Fonnte when a tugas is given (TugasBatchDibuatNotification has no whatsapp channel)', function () {
    \Illuminate\Support\Facades\Http::fake();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), [
        'judul' => 'Tugas Tanpa WA', 'instruksi' => 'x', 'frekuensi' => 'sekali',
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->addDays(3)->toDateString(),
    ]);

    \Illuminate\Support\Facades\Http::assertNothingSent();
});
