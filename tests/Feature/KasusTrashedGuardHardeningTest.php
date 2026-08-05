<?php
// tests/Feature/KasusTrashedGuardHardeningTest.php
//
// M3/M4 hardening (final whole-branch review, Sub-proyek 7): sesi/tugas/evaluasi store()
// and admin restore() must explicitly reject a trashed kasus, rather than relying on the
// current state machine (status stays 'selesai' after soft-delete) to block them by accident.

use App\Enums\StatusKasus;
use App\Models\Guru;
use App\Models\Kasus;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function buatKasusTrashedNamunStatusBerjalan(Lembaga $lembaga): array
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
        'nip' => fake()->unique()->numerify('##########'),
        'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_bk', 'status_kepegawaian' => 'GTY',
        'status_aktif' => 'aktif',
    ]);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
        'status' => StatusKasus::Berjalan, 'konselor_guru_id' => $guruBk->id,
    ]);
    // Force a trashed state directly (bypassing destroy()'s own status guard), simulating
    // any future path that could soft-delete a kasus outside the current 'selesai'-only rule.
    $kasus->delete();

    return [$kasus, $konselorUser];
}

it('404s kasus.sesi.store for a trashed kasus', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusTrashedNamunStatusBerjalan($lembaga);

    $this->actingAs($konselorUser)
        ->post(route('kasus.sesi.store', $kasus), ['sesi' => [
            ['dijadwalkan_pada' => now()->addDay()->format('Y-m-d H:i:s'), 'peserta' => 'siswa', 'lokasi_mode' => 'Ruang BK'],
        ]])
        ->assertNotFound();
});

it('404s kasus.tugas.store for a trashed kasus', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusTrashedNamunStatusBerjalan($lembaga);

    $this->actingAs($konselorUser)
        ->post(route('kasus.tugas.store', $kasus), ['tugas' => [
            ['judul' => 'T', 'instruksi' => 'I', 'frekuensi' => 'sekali', 'mulai_pada' => now()->format('Y-m-d'), 'batas_selesai_pada' => now()->addDay()->format('Y-m-d')],
        ]])
        ->assertNotFound();
});

it('404s kasus.evaluasi.store for a trashed kasus', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusTrashedNamunStatusBerjalan($lembaga);

    $this->actingAs($konselorUser)
        ->post(route('kasus.evaluasi.store', $kasus), ['catatan' => 'C', 'keputusan' => 'lanjut'])
        ->assertNotFound();
});

it('404s admin.kasus.restore when the kasus is not actually trashed', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['kasus.view', 'kasus.hapus', 'kasus.pulihkan'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kasus.view', 'kasus.hapus', 'kasus.pulihkan']);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'status' => StatusKasus::Selesai]);

    $this->actingAs($manager)
        ->post(route('admin.kasus.restore', $kasus))
        ->assertNotFound();
});
