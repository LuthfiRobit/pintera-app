<?php

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
    public function __construct(private readonly string $label = 'test')
    {
    }

    public function isUrgent(): bool { return false; }

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array { return ['message' => $this->label]; }

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

    $response = $this->actingAs($user)->postJson(route('notifikasi.baca', $notification->id));

    $response->assertOk();
    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks a single notification sent directly to the user as read', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Langsung',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200005555',
    ]);

    $user->notify(new MarkAsReadTestNotification('langsung-ke-user'));
    $notification = $user->notifications()->first();

    $response = $this->actingAs($user)->postJson(route('notifikasi.baca', $notification->id));

    $response->assertOk();
    $response->assertJson(['unread_count' => 0]);
    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('returns the correct remaining unread count after marking one of several as read', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Dua Notif',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200004444',
    ]);

    $user->notify(new MarkAsReadTestNotification('pertama'));
    $user->notify(new MarkAsReadTestNotification('kedua'));

    $notifications = $user->notifications()->latest()->get();
    $toMark = $notifications->first();
    $stillUnread = $notifications->last();

    $response = $this->actingAs($user)->postJson(route('notifikasi.baca', $toMark->id));

    $response->assertOk();
    $response->assertJson(['unread_count' => 1]);
    expect($toMark->fresh()->read_at)->not->toBeNull();
    expect($stillUnread->fresh()->read_at)->toBeNull();
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

    $response = $this->actingAs($user)->postJson(route('notifikasi.baca-semua'));

    $response->assertOk();
    $response->assertJson(['unread_count' => 0]);
    expect($orangTua->unreadNotifications()->count())->toBe(0);
    expect($user->unreadNotifications()->count())->toBe(0);
});

it('returns 403 if the single notification does not belong to the user or their OrangTua profile', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $userA = User::factory()->create(['lembaga_id' => null]);
    $userA->assignRole('orang_tua');
    OrangTua::create([
        'user_id' => $userA->id, 'nama_lengkap' => 'Ortu A',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200008888',
    ]);

    $userB = User::factory()->create(['lembaga_id' => null]);
    $userB->assignRole('orang_tua');
    $orangTuaB = OrangTua::create([
        'user_id' => $userB->id, 'nama_lengkap' => 'Ortu B',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200009999',
    ]);

    $orangTuaB->notify(new MarkAsReadTestNotification('milik-b'));
    $notificationB = $orangTuaB->notifications()->first();

    $response = $this->actingAs($userA)->postJson(route('notifikasi.baca', $notificationB->id));

    $response->assertForbidden();
    expect($notificationB->fresh()->read_at)->toBeNull();
});

it('returns a 403-safe response (not a crash) when marking a nonexistent notification id', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Nonexistent',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200003333',
    ]);

    $response = $this->actingAs($user)->postJson(route('notifikasi.baca', (string) \Illuminate\Support\Str::uuid()));

    $response->assertForbidden();
});

it('shows a topbar-specific mark-as-read control distinct from the dashboard panel', function () {
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
    // aria-label="Notifikasi" identifies the topbar bell trigger specifically,
    // which the dashboard panel (a plain in-page card) does not render.
    $response->assertSee('aria-label="Notifikasi"', false);
});
