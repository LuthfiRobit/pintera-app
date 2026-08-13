<?php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use App\Notifications\Finance\FinanceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

class MarkAsReadTestNotification extends FinanceNotification
{
    public function isUrgent(): bool { return false; }

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array { return ['message' => 'test']; }

    public function toMail(object $notifiable): MailMessage { return (new MailMessage())->line('test'); }

    public function toWhatsApp(object $notifiable): ?string { return null; }
}

it('marks a single notification as read', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Notif',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200006666',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $orangTua->notify(new MarkAsReadTestNotification());
    $notification = $orangTua->notifications()->first();

    $response = $this->actingAs($user)->postJson(route('keuangan.notifikasi.baca', $notification->id));

    $response->assertOk();
    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications for the active user feed as read', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Notif Multi',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200007777',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $orangTua->notify(new MarkAsReadTestNotification());
    $orangTua->notify(new MarkAsReadTestNotification());
    $user->notify(new MarkAsReadTestNotification());

    expect($orangTua->unreadNotifications()->count())->toBe(2);
    expect($user->unreadNotifications()->count())->toBe(1);

    $response = $this->actingAs($user)->postJson(route('keuangan.notifikasi.baca-semua'));

    $response->assertOk();
    expect($orangTua->unreadNotifications()->count())->toBe(0);
    expect($user->unreadNotifications()->count())->toBe(0);
});

it('returns 403 if the single notification does not belong to the user or their OrangTua profile', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $otherUser = User::factory()->create();
    $otherUser->notify(new MarkAsReadTestNotification());
    $notification = $otherUser->notifications()->first();

    $user = User::factory()->create();
    $user->assignRole('orang_tua');

    $response = $this->actingAs($user)->postJson(route('keuangan.notifikasi.baca', $notification->id));
    $response->assertForbidden();
});

it('shows a clickable mark-as-read control on each notification in the topbar', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Topbar',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200008888',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $orangTua->notify(new MarkAsReadTestNotification());

    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertOk();
    $response->assertSee('tandaiSatu', false);
});
