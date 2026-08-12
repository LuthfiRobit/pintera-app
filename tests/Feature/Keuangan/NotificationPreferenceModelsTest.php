<?php

use App\Models\NotificationLog;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a user_notification_preferences row with the correct defaults', function () {
    $user = User::factory()->create();

    $pref = UserNotificationPreference::create([
        'user_id' => $user->id,
        'module' => 'finance',
    ]);

    expect($pref->channel_push)->toBeFalse();
    expect($pref->channel_wa)->toBeTrue();
    expect($pref->channel_email)->toBeTrue();
});

it('enforces unique user_id + module on user_notification_preferences', function () {
    $user = User::factory()->create();
    UserNotificationPreference::create(['user_id' => $user->id, 'module' => 'finance']);

    expect(fn () => UserNotificationPreference::create(['user_id' => $user->id, 'module' => 'finance']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('creates a notification_logs row and casts payload to array', function () {
    $user = User::factory()->create();

    $log = NotificationLog::create([
        'user_id' => $user->id,
        'event_key' => 'App\\Notifications\\Finance\\TagihanDiterbitkanNotification',
        'channel' => 'wa',
        'payload' => ['message' => 'test'],
        'status' => 'sent',
    ]);

    expect($log->payload)->toBe(['message' => 'test']);
    expect($log->status)->toBe('sent');
});
