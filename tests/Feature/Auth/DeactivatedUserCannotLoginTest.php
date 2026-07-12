<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('rejects login for a deactivated user', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
        'is_active' => false,
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});
