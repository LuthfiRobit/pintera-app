<?php
// tests/Feature/KasusSesiJadwalTest.php

use App\Enums\StatusKasus;
use App\Models\Guru;
use App\Models\Kasus;
use App\Models\KasusSesi;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatKasusDitugaskanKeGuruBk(Lembaga $lembaga): array
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

it('lets the assigned konselor schedule 3 sesi at once', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBk($lembaga);

    $payload = ['sesi' => [
        ['dijadwalkan_pada' => now()->addDays(1)->format('Y-m-d H:i:s'), 'peserta' => 'siswa', 'lokasi_mode' => 'Ruang BK'],
        ['dijadwalkan_pada' => now()->addDays(2)->format('Y-m-d H:i:s'), 'peserta' => 'orang_tua', 'lokasi_mode' => 'Google Meet'],
        ['dijadwalkan_pada' => now()->addDays(3)->format('Y-m-d H:i:s'), 'peserta' => 'keduanya', 'lokasi_mode' => 'Ruang BK'],
    ]];

    $this->actingAs($konselorUser)->post(route('kasus.sesi.store', $kasus), $payload)
        ->assertRedirect(route('kasus.show', $kasus));

    expect(KasusSesi::where('kasus_id', $kasus->id)->count())->toBe(3);
    expect(KasusSesi::where('kasus_id', $kasus->id)->where('status', 'terjadwal')->count())->toBe(3);
});

it('rolls back the whole submit when one row in a multi-row sesi form is invalid', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBk($lembaga);

    $payload = ['sesi' => [
        ['dijadwalkan_pada' => now()->addDays(1)->format('Y-m-d H:i:s'), 'peserta' => 'siswa', 'lokasi_mode' => 'Ruang BK'],
        ['dijadwalkan_pada' => '', 'peserta' => 'siswa', 'lokasi_mode' => 'Ruang BK'],
    ]];

    $this->actingAs($konselorUser)->post(route('kasus.sesi.store', $kasus), $payload)
        ->assertSessionHasErrors();

    expect(KasusSesi::where('kasus_id', $kasus->id)->count())->toBe(0);
});

it('403s a konselor who is not assigned to the kasus', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus] = buatKasusDitugaskanKeGuruBk($lembaga);
    [, $unrelatedKonselorUser] = buatKasusDitugaskanKeGuruBk($lembaga);

    $payload = ['sesi' => [
        ['dijadwalkan_pada' => now()->addDays(1)->format('Y-m-d H:i:s'), 'peserta' => 'siswa', 'lokasi_mode' => 'Ruang BK'],
    ]];

    $this->actingAs($unrelatedKonselorUser)->post(route('kasus.sesi.store', $kasus), $payload)
        ->assertForbidden();
});

it('notifies the relevant peserta when a sesi is scheduled', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser, $siswa] = buatKasusDitugaskanKeGuruBk($lembaga);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = \App\Models\OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Kontak Utama',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200004444',
        'email' => 'ortu.sesi@example.test',
    ]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    \Illuminate\Support\Facades\Notification::fake();

    $this->actingAs($konselorUser)->post(route('kasus.sesi.store', $kasus), ['sesi' => [
        ['dijadwalkan_pada' => now()->addDays(1)->format('Y-m-d H:i:s'), 'peserta' => 'orang_tua', 'lokasi_mode' => 'Ruang BK'],
    ]]);

    \Illuminate\Support\Facades\Notification::assertSentTo($orangTua, \App\Notifications\SesiDijadwalkanNotification::class);
});
