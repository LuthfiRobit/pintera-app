<?php

use Illuminate\Support\Facades\Route;

it('renders the custom 403 page with its icon-badge layout and copy', function () {
    Route::get('/uji-error/403', fn () => abort(403));

    $response = $this->get('/uji-error/403');

    $response->assertStatus(403);
    $response->assertSee('403');
    $response->assertSee('Akses Dibatasi');
    $response->assertSee('khusus untuk peran tertentu', false);
});

it('renders the custom 404 page for a route that genuinely does not exist', function () {
    $response = $this->get('/rute-tidak-pernah-ada-untuk-uji-404');

    $response->assertStatus(404);
    $response->assertSee('404');
    $response->assertSee('Halaman Tidak Ditemukan');
    $response->assertSee('sudah dipindahkan atau tidak tersedia', false);
});

it('renders the custom 419 page', function () {
    Route::get('/uji-error/419', fn () => abort(419));

    $response = $this->get('/uji-error/419');

    $response->assertStatus(419);
    $response->assertSee('419');
    $response->assertSee('Sesi Anda Berakhir');
    $response->assertSee('sesi otomatis berakhir', false);
});

it('renders the custom 422 page', function () {
    Route::get('/uji-error/422', fn () => abort(422));

    $response = $this->get('/uji-error/422');

    $response->assertStatus(422);
    $response->assertSee('422');
    $response->assertSee('Periksa Kembali Data Anda');
    $response->assertSee('data yang dikirim belum sesuai', false);
});

it('renders the custom 429 page', function () {
    Route::get('/uji-error/429', fn () => abort(429));

    $response = $this->get('/uji-error/429');

    $response->assertStatus(429);
    $response->assertSee('429');
    $response->assertSee('Terlalu Banyak Permintaan');
    $response->assertSee('banyak aktivitas dari perangkat Anda', false);
});

it('renders the custom 500 page', function () {
    Route::get('/uji-error/500', fn () => abort(500));

    $response = $this->get('/uji-error/500');

    $response->assertStatus(500);
    $response->assertSee('500');
    $response->assertSee('Ada Gangguan di Sistem');
    $response->assertSee('sedang menangani masalah ini', false);
});

it('renders the custom 503 page', function () {
    Route::get('/uji-error/503', fn () => abort(503));

    $response = $this->get('/uji-error/503');

    $response->assertStatus(503);
    $response->assertSee('503');
    $response->assertSee('Sedang Dalam Perawatan');
    $response->assertSee('melakukan pemeliharaan', false);
});

it('shows the "Kembali ke Dashboard" button for an authenticated user hitting an error page', function () {
    Route::get('/uji-error/403-auth', fn () => abort(403));

    $user = \App\Models\User::factory()->create();

    $response = $this->actingAs($user)->get('/uji-error/403-auth');

    $response->assertStatus(403);
    $response->assertSee('Kembali ke Dashboard');
    $response->assertDontSee('Ke Halaman Login');
});

it('shows the "Ke Halaman Login" button for a guest hitting an error page', function () {
    Route::get('/uji-error/403-guest', fn () => abort(403));

    $response = $this->get('/uji-error/403-guest');

    $response->assertStatus(403);
    $response->assertSee('Ke Halaman Login');
    $response->assertDontSee('Kembali ke Dashboard');
});
