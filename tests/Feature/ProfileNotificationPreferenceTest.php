<?php

use App\Models\OrangTua;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buatOrangTuaUntukProfilNotifikasi(): array
{
    $user = User::factory()->create(['lembaga_id' => null]);
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Profil Notifikasi',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200005555',
    ]);

    return [$user, $orangTua];
}

it('creates a new preference row on first save', function () {
    [$user] = buatOrangTuaUntukProfilNotifikasi();

    $response = $this->actingAs($user)->patch(route('profile.notification-preference.update'), [
        'channel_wa' => '1',
        'channel_email' => '1',
    ]);

    $response->assertRedirect(route('profile.edit'));
    $preference = UserNotificationPreference::where('user_id', $user->id)->where('module', 'finance')->first();
    expect($preference)->not->toBeNull();
    expect($preference->channel_wa)->toBeTrue();
    expect($preference->channel_email)->toBeTrue();
});

it('stores an unchecked checkbox as false, not as omitted', function () {
    [$user] = buatOrangTuaUntukProfilNotifikasi();

    $response = $this->actingAs($user)->patch(route('profile.notification-preference.update'), [
        'channel_email' => '1',
        // channel_wa intentionally omitted, simulating an unchecked HTML checkbox
    ]);

    $response->assertRedirect();
    $preference = UserNotificationPreference::where('user_id', $user->id)->where('module', 'finance')->first();
    expect($preference->channel_wa)->toBeFalse();
    expect($preference->channel_email)->toBeTrue();
});

it('updates an existing preference row on a second save rather than creating a duplicate', function () {
    [$user] = buatOrangTuaUntukProfilNotifikasi();
    UserNotificationPreference::create(['user_id' => $user->id, 'module' => 'finance', 'channel_wa' => true, 'channel_email' => true]);

    $this->actingAs($user)->patch(route('profile.notification-preference.update'), ['channel_wa' => '0', 'channel_email' => '1']);

    expect(UserNotificationPreference::where('user_id', $user->id)->where('module', 'finance')->count())->toBe(1);
    $preference = UserNotificationPreference::where('user_id', $user->id)->where('module', 'finance')->first();
    expect($preference->channel_wa)->toBeFalse();
});

it('shows the notification preference section on /profile only for a user with a linked OrangTua', function () {
    [$userWithOrangTua] = buatOrangTuaUntukProfilNotifikasi();
    $userWithoutOrangTua = User::factory()->create();

    $this->actingAs($userWithOrangTua)->get(route('profile.edit'))->assertSee('Preferensi Notifikasi');
    $this->actingAs($userWithoutOrangTua)->get(route('profile.edit'))->assertDontSee('Preferensi Notifikasi');
});
