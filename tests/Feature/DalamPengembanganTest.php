<?php

use App\Models\User;

it('renders the dalam-pengembangan page with a human-readable title from the fitur query param', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dalam-pengembangan', ['fitur' => 'nilai-anak']));

    $response->assertOk();
    $response->assertSee('Nilai Anak');
    $response->assertSee('dalam pengembangan');
});

it('falls back to a generic title when fitur query param is missing', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dalam-pengembangan'));

    $response->assertOk();
    $response->assertSee('Fitur Ini');
});

it('redirects a guest to login', function () {
    $this->get(route('dalam-pengembangan'))->assertRedirect(route('login'));
});
