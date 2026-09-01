<?php

use App\Domains\Keuangan\Actions\Tagihan\RecalculateTagihanNominalAction;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanKeringanan;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\SiswaKeringanan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Notifications\Finance\TagihanDirevisiNotification;
use Illuminate\Support\Facades\Notification;

it('sends TagihanDirevisiNotification to the kontak utama orang tua when net_amount actually changes', function () {
    Notification::fake();

    $siswa = Siswa::factory()->create();
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'default_amount' => 300000]);
    $tagihan = Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'net_amount' => 300000, 'status' => 'belum_bayar']);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 50000]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    app(RecalculateTagihanNominalAction::class)->execute($tagihan->id);

    Notification::assertSentTo($orangTua, TagihanDirevisiNotification::class);
});

it('does not send any notification when recalc results in the exact same net_amount', function () {
    Notification::fake();

    $siswa = Siswa::factory()->create();
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'default_amount' => 300000]);
    $tagihan = Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'total_tagihan' => 300000, 'net_amount' => 300000, 'status' => 'belum_bayar']);

    app(RecalculateTagihanNominalAction::class)->execute($tagihan->id); // tidak ada keringanan sama sekali -> hasil resolve sama

    Notification::assertNothingSentTo($orangTua);
});
