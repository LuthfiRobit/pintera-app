<?php
// tests/Feature/Keuangan/TagihanPolymorphicTest.php

use App\Models\Pendaftaran;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;

it('lets a tagihan target a siswa via the tagihable polymorphic relation', function () {
    $siswa = Siswa::factory()->create();

    $tagihan = Tagihan::create([
        'tagihable_type' => Siswa::class,
        'tagihable_id' => $siswa->id,
        'kategori' => 'spp',
        'billing_period' => '2026-08',
        'source_trigger' => 'cron',
        'total_tagihan' => 300000,
        'net_amount' => 300000,
        'status' => 'belum_bayar',
    ]);

    expect($tagihan->tagihable)->toBeInstanceOf(Siswa::class);
    expect($tagihan->tagihable->id)->toBe($siswa->id);
    expect($tagihan->pendaftaran_id)->toBeNull();
    expect($siswa->tagihan)->toHaveCount(1);
});

it('still resolves the pendaftaran relation for PPDB tagihan rows created without tagihable columns set', function () {
    $pendaftaran = Pendaftaran::factory()->create();

    $tagihan = Tagihan::create([
        'pendaftaran_id' => $pendaftaran->id,
        'kategori' => 'pendaftaran',
        'total_tagihan' => 150000,
        'net_amount' => 150000,
        'status' => 'belum_bayar',
    ]);

    expect($tagihan->pendaftaran->id)->toBe($pendaftaran->id);
});

it('allows the dibatalkan status with a cancellation audit trail', function () {
    $pendaftaran = Pendaftaran::factory()->create();
    $admin = User::factory()->create();
    $tagihan = Tagihan::factory()->create(['pendaftaran_id' => $pendaftaran->id]);

    $tagihan->update([
        'status' => 'dibatalkan',
        'cancelled_by' => $admin->id,
        'cancelled_at' => now(),
        'cancel_reason' => 'Salah generate',
    ]);

    expect($tagihan->fresh()->status)->toBe('dibatalkan');
    expect($tagihan->fresh()->cancelled_by)->toBe($admin->id);
});
