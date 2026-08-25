<?php

use App\Domains\Kasus\Enums\StatusKasus;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusEvaluasi;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use App\Models\Guru;
use Spatie\Permission\Models\Permission;

if (! function_exists('buatKasusBerjalanDenganKonselor')) {
    function buatKasusBerjalanDenganKonselor(Lembaga $lembaga): array
    {
        $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

        $konselorUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
        Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
        $guruRole = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
        $guruRole->givePermissionTo(['kasus.view']);
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
            'status' => StatusKasus::Berjalan, 'konselor_guru_id' => $guruBk->id,
        ]);

        return [$kasus, $konselorUser, $siswa];
    }
}

if (! function_exists('buatKasusEskalasi')) {
    function buatKasusEskalasi(Lembaga $lembaga): array
    {
        [$kasus, $konselorUser, $siswa] = buatKasusBerjalanDenganKonselor($lembaga);
        $kasus->update(['status' => StatusKasus::Eskalasi]);

        return [$kasus, $konselorUser, $siswa];
    }
}

if (! function_exists('buatAdminAkademik')) {
    function buatAdminAkademik(Lembaga $lembaga): User
    {
        foreach (['kasus.view', 'kasus.triase'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $role->givePermissionTo(['kasus.view', 'kasus.triase']);

        $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $admin->assignRole($role);

        return $admin;
    }
}

it('lets an assigned konselor submit an evaluasi form from kasus.show and see the history including confidential catatan', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusBerjalanDenganKonselor($lembaga);
    KasusEvaluasi::factory()->create([
        'kasus_id' => $kasus->id, 'catatan' => 'RAHASIA-CATATAN-EVALUASI', 'keputusan' => 'lanjut',
    ]);

    $response = $this->actingAs($konselorUser)->get(route('kasus.show', $kasus));

    $response->assertOk()->assertSee('RAHASIA-CATATAN-EVALUASI')->assertSee('Simpan Evaluasi');
});

it('lets operator_akademik see the evaluasi history and form on an eskalasi kasus', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus] = buatKasusEskalasi($lembaga);
    $admin = buatAdminAkademik($lembaga);
    KasusEvaluasi::factory()->create([
        'kasus_id' => $kasus->id, 'catatan' => 'CATATAN-UNTUK-ADMIN', 'keputusan' => 'eskalasi',
    ]);

    $response = $this->actingAs($admin)->get(route('kasus.show', $kasus));

    $response->assertOk()->assertSee('CATATAN-UNTUK-ADMIN')->assertSee('Simpan Evaluasi');
});

it('hides evaluasi catatan and the evaluasi form from orang tua kontak utama', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, , $siswa] = buatKasusBerjalanDenganKonselor($lembaga);
    KasusEvaluasi::factory()->create([
        'kasus_id' => $kasus->id, 'catatan' => 'RAHASIA-DARI-ORTU', 'keputusan' => 'lanjut',
    ]);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'kasus.consent', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kasus.view', 'kasus.consent']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = \App\Models\OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Evaluasi',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200002222',
        'email' => 'ortu.evaluasi@example.test',
    ]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $response = $this->actingAs($orangTuaUser)->get(route('kasus.show', $kasus));

    $response->assertOk()->assertDontSee('RAHASIA-DARI-ORTU')->assertDontSee('Simpan Evaluasi');
});
