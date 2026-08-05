<?php

use App\Enums\StatusKasus;
use App\Models\Kasus;
use App\Models\KasusEvaluasi;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('lets an assigned konselor submit an evaluasi form from kasus.show and see the history including confidential catatan', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusBerjalanDenganKonselor($lembaga);
    KasusEvaluasi::factory()->create([
        'kasus_id' => $kasus->id, 'catatan' => 'RAHASIA-CATATAN-EVALUASI', 'keputusan' => 'lanjut',
    ]);

    $response = $this->actingAs($konselorUser)->get(route('kasus.show', $kasus));

    $response->assertOk()->assertSee('RAHASIA-CATATAN-EVALUASI')->assertSee('Simpan Evaluasi');
});

it('lets admin_akademik see the evaluasi history and form on an eskalasi kasus', function () {
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
