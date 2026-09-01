<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Jobs\RecalculateTagihanNominalJob;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('dispatches one job per affected unpaid tagihan when a keringanan rule nilai changes', function () {
    Queue::fake();

    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $siswaSatu = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaDua = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 300000]);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id]);

    $tagihanSatu = Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswaSatu->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'status' => 'belum_bayar']);
    $tagihanDua = Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswaDua->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'status' => 'lunas']); // TIDAK boleh kena job

    $this->actingAs($admin)->putJson(route('admin.jenis-tagihan.update', $jenisTagihan), [
        'nama' => $jenisTagihan->nama, 'kategori' => 'spp', 'mode' => 'manual', 'tipe' => 'sekali',
        'keringanan' => [['kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 50000]],
    ]);

    Queue::assertPushed(RecalculateTagihanNominalJob::class, 1);
    Queue::assertPushed(fn (RecalculateTagihanNominalJob $job) => $job->tagihanId === $tagihanSatu->id);
});

it('does NOT dispatch any job when the form is saved without touching tarif/keringanan at all', function () {
    Queue::fake();

    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'status' => 'belum_bayar']);

    $this->actingAs($admin)->putJson(route('admin.jenis-tagihan.update', $jenisTagihan), [
        'nama' => 'Nama Baru Saja', 'kategori' => 'spp', 'mode' => 'manual', 'tipe' => 'sekali',
    ]);

    Queue::assertNotPushed(RecalculateTagihanNominalJob::class);
});
