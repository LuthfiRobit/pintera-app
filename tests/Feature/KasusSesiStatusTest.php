<?php

// tests/Feature/KasusSesiStatusTest.php

use App\Domains\Kasus\Enums\StatusKasus;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusSesi;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

// Declared with a distinct name from tests/Feature/KasusSesiJadwalTest.php's
// buatKasusDitugaskanKeGuruBk() — Pest/PHPUnit loads all Feature test files into the
// same PHP process, so re-declaring a same-named top-level function across files is a
// fatal "Cannot redeclare" error, not something Pest scopes per file.
function buatKasusDitugaskanKeGuruBkUntukStatus(Lembaga $lembaga): array
{
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $konselorUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kasus.view']);
    $konselorUser->assignRole('guru');
    $guruBk = Guru::factory()->create([
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

it('lets the assigned konselor mark a sesi selesai with a confidential note', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukStatus($lembaga);
    $sesi = KasusSesi::factory()->create(['kasus_id' => $kasus->id]);

    $this->actingAs($konselorUser)->patch(route('kasus.sesi.update-status', [$kasus, $sesi]), [
        'status' => 'selesai',
        'catatan_internal' => 'Siswa menunjukkan progres baik.',
    ])->assertRedirect(route('kasus.show', $kasus));

    $sesi->refresh();
    expect($sesi->status->value)->toBe('selesai');
    expect($sesi->catatan_internal)->toBe('Siswa menunjukkan progres baik.');
});

it('lets the assigned konselor mark a sesi batal with a reason', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukStatus($lembaga);
    $sesi = KasusSesi::factory()->create(['kasus_id' => $kasus->id]);

    $this->actingAs($konselorUser)->patch(route('kasus.sesi.update-status', [$kasus, $sesi]), [
        'status' => 'batal',
        'alasan_batal' => 'Siswa sakit.',
    ])->assertRedirect(route('kasus.show', $kasus));

    $sesi->refresh();
    expect($sesi->status->value)->toBe('batal');
    expect($sesi->alasan_batal)->toBe('Siswa sakit.');
});

it('hides catatan_internal from the orang tua kontak utama viewing kasus.show', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser, $siswa] = buatKasusDitugaskanKeGuruBkUntukStatus($lembaga);
    KasusSesi::factory()->create([
        'kasus_id' => $kasus->id, 'status' => 'selesai',
        'catatan_internal' => 'RAHASIA-CATATAN-KONSELOR',
    ]);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $orangTuaRole = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaRole->givePermissionTo(['kasus.view']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Kontak Utama',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200005555',
        'email' => 'ortu.confidential@example.test',
    ]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $this->actingAs($orangTuaUser)->get(route('kasus.show', $kasus))
        ->assertOk()
        ->assertDontSee('RAHASIA-CATATAN-KONSELOR');
});

it('403s an attempt to update an already-selesai sesi and leaves it unchanged', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukStatus($lembaga);
    $sesi = KasusSesi::factory()->create([
        'kasus_id' => $kasus->id, 'status' => 'selesai',
        'catatan_internal' => 'Catatan asli.',
    ]);

    $this->actingAs($konselorUser)->patch(route('kasus.sesi.update-status', [$kasus, $sesi]), [
        'status' => 'batal',
        'alasan_batal' => 'Coba ubah lagi.',
    ])->assertForbidden();

    $sesi->refresh();
    expect($sesi->status->value)->toBe('selesai');
    expect($sesi->catatan_internal)->toBe('Catatan asli.');
    expect($sesi->alasan_batal)->toBeNull();
});

it('lets an orang tua kontak utama see sesi metadata but not the create-sesi form or catatan_internal', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser, $siswa] = buatKasusDitugaskanKeGuruBkUntukStatus($lembaga);
    $sesi = KasusSesi::factory()->create([
        'kasus_id' => $kasus->id, 'status' => 'terjadwal',
        'dijadwalkan_pada' => now()->addDays(2),
    ]);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $orangTuaRole = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaRole->givePermissionTo(['kasus.view']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Kontak Sesi',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200006666',
        'email' => 'ortu.sesi@example.test',
    ]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $response = $this->actingAs($orangTuaUser)->get(route('kasus.show', $kasus))->assertOk();

    $response->assertSee($sesi->dijadwalkan_pada->format('d M Y H:i'));
    $response->assertSee($sesi->status->label());
    $response->assertDontSee('Jadwalkan Sesi');
});

it('403s a konselor who is not assigned from updating a sesi status', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus] = buatKasusDitugaskanKeGuruBkUntukStatus($lembaga);
    [, $unrelatedKonselorUser] = buatKasusDitugaskanKeGuruBkUntukStatus($lembaga);
    $sesi = KasusSesi::factory()->create(['kasus_id' => $kasus->id]);

    $this->actingAs($unrelatedKonselorUser)->patch(route('kasus.sesi.update-status', [$kasus, $sesi]), [
        'status' => 'selesai', 'catatan_internal' => 'x',
    ])->assertForbidden();
});
