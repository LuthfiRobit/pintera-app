<?php

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('SPMB pendaftaran detail page shows the actual pendaftaran tagihan, not always Belum ada tagihan', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin();
    $tagihan = Tagihan::factory()->create([
        'pendaftaran_id' => $pendaftaran->id,
        'kategori' => 'pendaftaran',
        'total_tagihan' => 500000,
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $response = $this->actingAs($user)->get(route('admin.spmb-pendaftaran.show', $pendaftaran));

    // The "Tagihan Pendaftaran" card must show the real tagihan (amount + status),
    // not "Belum ada tagihan". The "Tagihan Daftar Ulang" card legitimately has no
    // tagihan here, so it still shows "Belum ada tagihan" — that assertion belongs
    // to that card only, not the page as a whole.
    $response->assertOk();
    $response->assertSeeInOrder([
        'Tagihan Pendaftaran',
        'Rp '.number_format($tagihan->total_tagihan, 0, ',', '.'),
        'Belum bayar',
    ], escape: false);
});
