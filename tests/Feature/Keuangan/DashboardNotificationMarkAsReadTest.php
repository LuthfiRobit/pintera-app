<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use App\Notifications\Finance\FinanceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Js;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

class DashboardMarkAsReadTestNotification extends FinanceNotification
{
    public function isUrgent(): bool
    {
        return false;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['message' => 'dashboard-test'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->line('test');
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return null;
    }
}

class DashboardTagihanLinkTestNotification extends FinanceNotification
{
    public function __construct(private readonly int $tagihanId) {}

    public function isUrgent(): bool
    {
        return false;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['tagihan_id' => $this->tagihanId, 'message' => 'dashboard-test-with-tagihan'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->line('test');
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return null;
    }
}

it('shows a Lihat Detail link in the dashboard notification panel for a tagihan_id notification', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
    ]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Dashboard Notif Link',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200007778',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $orangTua->notify(new DashboardTagihanLinkTestNotification($tagihan->id));

    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertOk();
    $response->assertSee('Lihat Detail', false);
    $wrapped = (string) Js::from(route('keuangan.tagihan.show', $tagihan));
    $response->assertSee($wrapped, false);
});

it('shows a clickable mark-as-read control on each notification row in the dashboard panel', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Dashboard Notif',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200007777',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $orangTua->notify(new DashboardMarkAsReadTestNotification);

    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertOk();
    $response->assertSee('tandaiSatu', false);
});
