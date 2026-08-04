<?php
// tests/Feature/KasusEvaluasiTest.php

use App\Enums\StatusKasus;
use App\Models\Guru;
use App\Models\Kasus;
use App\Models\KasusEvaluasi;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use App\Notifications\KasusDikembalikanNotification;
use App\Notifications\KasusEskalasiNotification;
use App\Notifications\KasusSelesaiNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

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

function buatKasusEskalasi(Lembaga $lembaga): array
{
    [$kasus, $konselorUser, $siswa] = buatKasusBerjalanDenganKonselor($lembaga);
    $kasus->update(['status' => StatusKasus::Eskalasi]);

    return [$kasus, $konselorUser, $siswa];
}

function buatAdminAkademik(Lembaga $lembaga): User
{
    foreach (['kasus.view', 'kasus.triase'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kasus.view', 'kasus.triase']);

    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    return $admin;
}

it('lets the konselor evaluate a berjalan kasus with keputusan lanjut, status stays berjalan', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusBerjalanDenganKonselor($lembaga);

    $this->actingAs($konselorUser)->post(route('kasus.evaluasi.store', $kasus), [
        'catatan' => 'Progres bagus.', 'keputusan' => 'lanjut',
    ])->assertRedirect(route('kasus.show', $kasus));

    expect($kasus->refresh()->status)->toBe(StatusKasus::Berjalan);
    expect(KasusEvaluasi::where('kasus_id', $kasus->id)->where('keputusan', 'lanjut')->exists())->toBeTrue();
});

it('lets the konselor evaluate a berjalan kasus with keputusan eskalasi, notifies admin_akademik', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusBerjalanDenganKonselor($lembaga);
    $admin = buatAdminAkademik($lembaga);

    Notification::fake();

    $this->actingAs($konselorUser)->post(route('kasus.evaluasi.store', $kasus), [
        'catatan' => 'Butuh keterlibatan admin.', 'keputusan' => 'eskalasi',
    ])->assertRedirect(route('kasus.show', $kasus));

    expect($kasus->refresh()->status)->toBe(StatusKasus::Eskalasi);
    Notification::assertSentTo($admin, KasusEskalasiNotification::class);
});

it('lets the konselor evaluate a berjalan kasus with keputusan selesai, no approval needed', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser, $siswa] = buatKasusBerjalanDenganKonselor($lembaga);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Selesai',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200009999',
        'email' => 'ortu.selesai@example.test',
    ]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    Notification::fake();

    $this->actingAs($konselorUser)->post(route('kasus.evaluasi.store', $kasus), [
        'catatan' => 'Kasus tuntas.', 'keputusan' => 'selesai',
    ])->assertRedirect(route('kasus.show', $kasus));

    expect($kasus->refresh()->status)->toBe(StatusKasus::Selesai);
    Notification::assertSentTo($orangTua, KasusSelesaiNotification::class);
});

it('lets admin_akademik evaluate an eskalasi kasus with keputusan lanjut, notifies the konselor, konselor unchanged', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusEskalasi($lembaga);
    $admin = buatAdminAkademik($lembaga);

    Notification::fake();

    $this->actingAs($admin)->post(route('kasus.evaluasi.store', $kasus), [
        'catatan' => 'Silakan lanjutkan penanganan.', 'keputusan' => 'lanjut',
    ])->assertRedirect(route('kasus.show', $kasus));

    $kasus->refresh();
    expect($kasus->status)->toBe(StatusKasus::Berjalan);
    expect($kasus->konselor_guru_id)->not->toBeNull();
    Notification::assertSentTo($konselorUser, KasusDikembalikanNotification::class);
});

it('lets admin_akademik evaluate an eskalasi kasus with keputusan selesai', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus] = buatKasusEskalasi($lembaga);
    $admin = buatAdminAkademik($lembaga);

    $this->actingAs($admin)->post(route('kasus.evaluasi.store', $kasus), [
        'catatan' => 'Ditutup oleh admin.', 'keputusan' => 'selesai',
    ])->assertRedirect(route('kasus.show', $kasus));

    expect($kasus->refresh()->status)->toBe(StatusKasus::Selesai);
});

it('403s a konselor who is not assigned from evaluating a berjalan kasus', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus] = buatKasusBerjalanDenganKonselor($lembaga);
    [, $unrelatedKonselorUser] = buatKasusBerjalanDenganKonselor($lembaga);

    $this->actingAs($unrelatedKonselorUser)->post(route('kasus.evaluasi.store', $kasus), [
        'catatan' => 'x', 'keputusan' => 'lanjut',
    ])->assertForbidden();
});

it('404s an admin from another lembaga trying to evaluate an eskalasi kasus', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    [$kasus] = buatKasusEskalasi($lembagaB);
    $adminA = buatAdminAkademik($lembagaA);

    $this->actingAs($adminA)->post(route('kasus.evaluasi.store', $kasus), [
        'catatan' => 'x', 'keputusan' => 'selesai',
    ])->assertNotFound();
});

it('rejects keputusan eskalasi when the kasus is already eskalasi', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus] = buatKasusEskalasi($lembaga);
    $admin = buatAdminAkademik($lembaga);

    $this->actingAs($admin)->post(route('kasus.evaluasi.store', $kasus), [
        'catatan' => 'x', 'keputusan' => 'eskalasi',
    ])->assertSessionHasErrors('keputusan');
});

it('lets the konselor keep scheduling sesi and giving tugas while the kasus is eskalasi', function () {
    // Spec: eskalasi is a signal for admin attention, not an operational freeze — the
    // konselor must remain able to schedule sesi/tugas the whole time. No new guard code
    // exists to enforce this (KasusSesiController/KasusTugasController::store() never
    // checked kasus status in the first place), so this test proves the absence of an
    // accidental restriction, not a new feature.
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusEskalasi($lembaga);

    $this->actingAs($konselorUser)->post(route('kasus.sesi.store', $kasus), ['sesi' => [
        ['dijadwalkan_pada' => now()->addDay()->format('Y-m-d H:i:s'), 'peserta' => 'siswa', 'lokasi_mode' => 'Ruang BK'],
    ]])->assertRedirect(route('kasus.show', $kasus));

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), ['tugas' => [
        ['judul' => 'Tugas Saat Eskalasi', 'instruksi' => 'x', 'frekuensi' => 'sekali', 'mulai_pada' => now()->toDateString(), 'batas_selesai_pada' => now()->addDays(3)->toDateString()],
    ]])->assertRedirect(route('kasus.show', $kasus));

    expect(\App\Models\KasusSesi::where('kasus_id', $kasus->id)->count())->toBe(1);
    expect(\App\Models\KasusTugas::where('kasus_id', $kasus->id)->count())->toBe(1);
    expect($kasus->refresh()->status)->toBe(StatusKasus::Eskalasi);
});
