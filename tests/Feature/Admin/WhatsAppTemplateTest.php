<?php
// tests/Feature/Admin/WhatsAppTemplateTest.php

use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use Spatie\Permission\Models\Permission;

function actingAsWhatsAppTemplateManager(): User
{
    Permission::firstOrCreate(['name' => 'whatsapp-template.edit', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['whatsapp-template.edit']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    return $manager;
}

it('lists the seeded whatsapp templates for a manager', function () {
    WhatsAppTemplate::create(['kode' => 'consent_diminta', 'isi_template' => 'Template A']);
    WhatsAppTemplate::create(['kode' => 'reminder_sesi_h1', 'isi_template' => 'Template B']);
    $manager = actingAsWhatsAppTemplateManager();

    $this->actingAs($manager)->get(route('admin.whatsapp-template.index'))
        ->assertOk()->assertSee('Template A')->assertSee('Template B');
});

it('lets a manager update isi_template and deskripsi, but not kode', function () {
    $template = WhatsAppTemplate::create(['kode' => 'consent_diminta', 'isi_template' => 'Lama']);
    $manager = actingAsWhatsAppTemplateManager();

    $this->actingAs($manager)->put(route('admin.whatsapp-template.update', $template), [
        'kode' => 'kode_diubah_paksa',
        'isi_template' => 'Baru, {nama_siswa}.',
        'deskripsi' => 'Deskripsi baru.',
    ])->assertRedirect();

    $template->refresh();
    expect($template->kode)->toBe('consent_diminta');
    expect($template->isi_template)->toBe('Baru, {nama_siswa}.');
    expect($template->deskripsi)->toBe('Deskripsi baru.');
});

it('denies access to a user without whatsapp-template.edit permission', function () {
    $template = WhatsAppTemplate::create(['kode' => 'consent_diminta', 'isi_template' => 'x']);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.whatsapp-template.index'))->assertForbidden();
    $this->actingAs($user)->put(route('admin.whatsapp-template.update', $template), ['isi_template' => 'y'])->assertForbidden();
});

it('has no create or delete route for whatsapp templates', function () {
    expect(\Illuminate\Support\Facades\Route::has('admin.whatsapp-template.store'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Route::has('admin.whatsapp-template.destroy'))->toBeFalse();
});

it('uses an admin-edited reminder_sesi_h1 template body for the next reminder sent, not the original seeded text', function () {
    // Regression test for the final whole-branch review's Finding 4: the spec requires
    // "Admin mengedit isi_template untuk kode = reminder_sesi_h1 -> notifikasi berikutnya
    // memakai isi template yang baru." Previously this was only proven as two disconnected
    // facts (the edit endpoint persists correctly; template rendering works when seeded
    // directly) -- nothing proved the actual join between an admin's edit and what a real
    // scheduled reminder sends.
    \Illuminate\Support\Facades\Http::fake(['api.fonnte.com/*' => \Illuminate\Support\Facades\Http::response(['status' => true], 200)]);

    $template = \App\Models\WhatsAppTemplate::firstOrCreate(
        ['kode' => 'reminder_sesi_h1'],
        ['isi_template' => 'Template asli lama untuk {nama_siswa}.']
    );
    $template->update(['isi_template' => 'Template asli lama untuk {nama_siswa}.']);

    $manager = actingAsWhatsAppTemplateManager();

    $distinctiveText = 'FRASA-KHAS-EDIT-ADMIN-99887 untuk {nama_siswa} di {lokasi_sesi} jam {tanggal_sesi}.';

    $this->actingAs($manager)->put(route('admin.whatsapp-template.update', $template), [
        'isi_template' => $distinctiveText,
        'deskripsi' => $template->deskripsi,
    ])->assertRedirect();

    $template->refresh();
    expect($template->isi_template)->toBe($distinctiveText);

    // Now trigger a real due sesi reminder and prove the Fonnte payload uses the edited text.
    $yayasan = \App\Models\Yayasan::factory()->create();
    $lembaga = \App\Models\Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = \App\Models\Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Siswa Template Edit']);

    $orangTuaUser = \App\Models\User::factory()->create(['lembaga_id' => null]);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = \App\Models\OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Template Edit',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200009911',
        'email' => 'ortu.template.edit@example.test',
    ]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $kasus = \App\Models\Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
    ]);
    \App\Models\KasusSesi::factory()->create([
        'kasus_id' => $kasus->id, 'peserta' => 'orang_tua',
        'dijadwalkan_pada' => now()->addDay()->setTime(9, 0), 'status' => 'terjadwal',
        'lokasi_mode' => 'Ruang BK',
    ]);

    // The scheduled command runs outside any web request, with no authenticated user -- log
    // out here so TenantScope (which no-ops only when auth()->id() is falsy) behaves the same
    // way it does for the real `schedule:run` invocation, rather than staying scoped to the
    // admin's own tenant from the actingAs() call above.
    \Illuminate\Support\Facades\Auth::logout();

    $this->artisan('kasus:kirim-reminder-sesi');

    \Illuminate\Support\Facades\Http::assertSent(function ($request) {
        return $request->url() === 'https://api.fonnte.com/send'
            && $request['target'] === '081200009911'
            && str_contains($request['message'], 'FRASA-KHAS-EDIT-ADMIN-99887')
            && str_contains($request['message'], 'Siswa Template Edit')
            && ! str_contains($request['message'], 'Template asli lama');
    });
});
