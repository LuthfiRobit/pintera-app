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
