<?php
// tests/Feature/KasusAutoBerjalanTest.php

use App\Enums\StatusKasus;
use App\Models\Guru;
use App\Models\Kasus;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatKasusDitugaskanUntukAutoBerjalan(Lembaga $lembaga): array
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

    return [$kasus, $konselorUser];
}

it('moves kasus from ditugaskan to berjalan when the first sesi is scheduled', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanUntukAutoBerjalan($lembaga);

    $this->actingAs($konselorUser)->post(route('kasus.sesi.store', $kasus), ['sesi' => [
        ['dijadwalkan_pada' => now()->addDay()->format('Y-m-d H:i:s'), 'peserta' => 'siswa', 'lokasi_mode' => 'Ruang BK'],
    ]]);

    expect($kasus->refresh()->status)->toBe(StatusKasus::Berjalan);
});

it('moves kasus from ditugaskan to berjalan when the first tugas is given', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanUntukAutoBerjalan($lembaga);

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), ['tugas' => [
        ['judul' => 'Jurnal', 'instruksi' => 'x', 'frekuensi' => 'sekali', 'mulai_pada' => now()->toDateString(), 'batas_selesai_pada' => now()->addDays(3)->toDateString()],
    ]]);

    expect($kasus->refresh()->status)->toBe(StatusKasus::Berjalan);
});

it('does not change status when scheduling a second sesi on an already-berjalan kasus', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanUntukAutoBerjalan($lembaga);
    $kasus->update(['status' => StatusKasus::Berjalan]);

    $this->actingAs($konselorUser)->post(route('kasus.sesi.store', $kasus), ['sesi' => [
        ['dijadwalkan_pada' => now()->addDay()->format('Y-m-d H:i:s'), 'peserta' => 'siswa', 'lokasi_mode' => 'Ruang BK'],
    ]]);

    expect($kasus->refresh()->status)->toBe(StatusKasus::Berjalan);
});
