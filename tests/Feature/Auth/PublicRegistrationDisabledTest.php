<?php

it('does not expose a public registration page', function () {
    $this->get('/register')->assertNotFound();
});

it('rejects a direct POST to the registration endpoint', function () {
    $this->post('/register', [
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
});
