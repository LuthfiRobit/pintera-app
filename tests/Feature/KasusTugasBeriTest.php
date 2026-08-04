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

it('lets the assigned konselor give 2 tugas at once', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $payload = ['tugas' => [
        ['judul' => 'Jurnal Harian', 'instruksi' => 'Tulis 3 hal baik hari ini.', 'frekuensi' => 'harian', 'mulai_pada' => now()->toDateString(), 'batas_selesai_pada' => now()->addDays(7)->toDateString()],
        ['judul' => 'Latihan Pernapasan', 'instruksi' => 'Lakukan 5 menit sebelum tidur.', 'frekuensi' => 'sekali', 'mulai_pada' => now()->toDateString(), 'batas_selesai_pada' => now()->addDays(3)->toDateString()],
    ]];

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), $payload)
        ->assertRedirect(route('kasus.show', $kasus));

    expect(KasusTugas::where('kasus_id', $kasus->id)->count())->toBe(2);
    expect(KasusTugas::where('kasus_id', $kasus->id)->where('status', 'ditugaskan')->count())->toBe(2);
});

it('rolls back the whole submit when one row in a multi-row tugas form is invalid', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $payload = ['tugas' => [
        ['judul' => 'Jurnal Harian', 'instruksi' => 'Tulis 3 hal baik hari ini.', 'frekuensi' => 'harian', 'mulai_pada' => now()->toDateString(), 'batas_selesai_pada' => now()->addDays(7)->toDateString()],
        ['judul' => '', 'instruksi' => 'x', 'frekuensi' => 'sekali', 'mulai_pada' => now()->toDateString(), 'batas_selesai_pada' => now()->addDays(3)->toDateString()],
    ]];

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), $payload)->assertSessionHasErrors();

    expect(KasusTugas::where('kasus_id', $kasus->id)->count())->toBe(0);
});

it('403s a konselor who is not assigned from giving tugas', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);
    [, $unrelatedKonselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $payload = ['tugas' => [
        ['judul' => 'x', 'instruksi' => 'x', 'frekuensi' => 'sekali', 'mulai_pada' => now()->toDateString(), 'batas_selesai_pada' => now()->addDays(3)->toDateString()],
    ]];

    $this->actingAs($unrelatedKonselorUser)->post(route('kasus.tugas.store', $kasus), $payload)->assertForbidden();
});

it('notifies siswa and orang tua when a tugas is given', function () {
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

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), ['tugas' => [
        ['judul' => 'Jurnal Harian', 'instruksi' => 'x', 'frekuensi' => 'sekali', 'mulai_pada' => now()->toDateString(), 'batas_selesai_pada' => now()->addDays(3)->toDateString()],
    ]]);

    \Illuminate\Support\Facades\Notification::assertSentTo($siswaUser, \App\Notifications\TugasDitugaskanNotification::class);
    \Illuminate\Support\Facades\Notification::assertSentTo($orangTua, \App\Notifications\TugasDitugaskanNotification::class);
});
