<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanKeringanan;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Pendaftaran;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('recalculates unpaid siswa tagihan synchronously when a keringanan is assigned', function () {
    $siswa = Siswa::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $siswa->lembaga_id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'default_amount' => 300000]);
    $tagihan = Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'net_amount' => 300000, 'status' => 'belum_bayar']);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 40000]);

    $this->actingAs($admin)->post(route('admin.siswa.keringanan.store', $siswa), [
        'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->toDateString(),
    ]);

    expect((float) $tagihan->fresh()->net_amount)->toBe(260000.0);
});

it('does NOT touch a PPDB (Pendaftaran-tagihable) tagihan belonging to the same person', function () {
    $siswa = Siswa::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $siswa->lembaga_id]);
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    $tagihanPpdb = Tagihan::factory()->create([
        'tagihable_type' => Pendaftaran::class, 'tagihable_id' => $pendaftaran->id, 'pendaftaran_id' => $pendaftaran->id,
        'person_id' => $siswa->person_id, 'net_amount' => 500000, 'status' => 'belum_bayar', 'kategori' => 'pendaftaran',
    ]);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);

    $this->actingAs($admin)->post(route('admin.siswa.keringanan.store', $siswa), [
        'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->toDateString(),
    ]);

    expect((float) $tagihanPpdb->fresh()->net_amount)->toBe(500000.0); // sama sekali tidak tersentuh
});
