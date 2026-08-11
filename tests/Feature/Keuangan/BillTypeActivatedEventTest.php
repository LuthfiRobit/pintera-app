<?php
// tests/Feature/Keuangan/BillTypeActivatedEventTest.php

use App\Models\JenisTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;

it('generates tagihan for matching siswa when is_active flips from false to true', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 300000, 'mode' => 'otomatis', 'is_active' => false]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    expect(Tagihan::where('tagihable_id', $siswa->id)->exists())->toBeFalse();

    $jenisTagihan->update(['is_active' => true]);

    expect(Tagihan::where('tagihable_id', $siswa->id)->where('jenis_tagihan_id', $jenisTagihan->id)->exists())->toBeTrue();
});

it('does not fire again when is_active is saved as true a second time without changing', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 300000, 'mode' => 'otomatis', 'is_active' => true]);
    Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    Tagihan::query()->delete();

    $jenisTagihan->update(['nama' => 'Nama Diperbarui']); // is_active tidak berubah

    expect(Tagihan::count())->toBe(0);
});
