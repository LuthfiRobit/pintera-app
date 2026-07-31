<?php

it('renders the welcome portal landing page with status 200', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee('Selamat Datang di Portal Terpadu Pintera');
});

it('displays all four portal cards with correct titles and descriptions', function () {
    $response = $this->get('/');

    // Portal Yayasan
    $response->assertSee('Portal Yayasan');
    $response->assertSee('Monitoring kinerja, evaluasi KPI, & ringkasan finansial lembaga.');
    $response->assertSee('Masuk Yayasan');

    // Portal Admin
    $response->assertSee('Portal Admin');
    $response->assertSee('Manajemen data sekolah, kepegawaian, tagihan & konfigurasi SPMB.');
    $response->assertSee('Masuk Admin');

    // Portal Guru
    $response->assertSee('Portal Guru');
    $response->assertSee('Pengisian presensi biometrik, penyusunan RPP, & pencatatan nilai.');
    $response->assertSee('Masuk Guru');

    // Portal Siswa
    $response->assertSee('Portal Siswa');
    $response->assertSee('Akses kalender akademik, lihat tagihan sekolah, & e-rapor.');
    $response->assertSee('Masuk Siswa');
});

it('links all portal cards directly to the centralized login route', function () {
    $response = $this->get('/');

    $loginUrl = route('login');
    
    // Assert that the login URL is rendered multiple times for our portal CTA buttons
    $content = $response->getContent();
    expect(substr_count($content, $loginUrl))->toBeGreaterThanOrEqual(4);
});
