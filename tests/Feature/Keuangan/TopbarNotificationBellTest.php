<?php

// tests/Feature/Keuangan/TopbarNotificationBellTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Notifications\Finance\FinanceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Js;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

class BellTestNotification extends FinanceNotification
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
        return ['message' => 'Tagihan SPP Agustus terbit.'];
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

class BellTestNotificationWithTagihan extends FinanceNotification
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
        return ['tagihan_id' => $this->tagihanId, 'message' => 'Tagihan SPP direvisi.'];
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

it('shows real notifications in the topbar bell instead of the static placeholder', function () {
    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->notify(new BellTestNotification);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Tagihan SPP Agustus terbit.');
    $response->assertDontSee('Belum ada notifikasi.');
});

it('still shows the empty state when there are zero notifications', function () {
    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Belum ada notifikasi.');
});

it('shows a Lihat Detail link for a tagihan_id notification when the user is an orang tua', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create(['tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create(['user_id' => $user->id, 'nik' => fake()->unique()->numerify('################')]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $user->notify(new BellTestNotificationWithTagihan($tagihan->id));

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Lihat Detail', false);
    // @js() wraps the url in JSON.parse('...'), escaping slashes (\/) -- assert the
    // same way Illuminate\Support\Js::from() would actually render it, not the raw url.
    $wrapped = (string) Js::from(route('keuangan.tagihan.show', $tagihan));
    $response->assertSee($wrapped, false);
});

it('does not show a Lihat Detail link for a tagihan_id notification when the user is not an orang tua', function () {
    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create(['tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id]);

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->notify(new BellTestNotificationWithTagihan($tagihan->id));

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Lihat Detail', false);
});
