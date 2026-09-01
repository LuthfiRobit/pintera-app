<?php

use App\Domains\Keuangan\Actions\Tagihan\RecalculateTagihanNominalAction;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanKeringanan;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\SiswaKeringanan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\PembayaranService;
use App\Models\Pendaftaran;
use App\Models\Siswa;

it('is a no-op for a PPDB tagihan (tagihable_type = Pendaftaran), not an error', function () {
    $pendaftaran = Pendaftaran::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Pendaftaran::class,
        'tagihable_id' => $pendaftaran->id,
        'pendaftaran_id' => $pendaftaran->id,
        'net_amount' => 500000,
        'status' => 'belum_bayar',
    ]);

    app(RecalculateTagihanNominalAction::class)->execute($tagihan->id);

    $fresh = $tagihan->fresh();
    expect((float) $fresh->net_amount)->toBe(500000.0);
    expect($fresh->status)->toBe('belum_bayar');
    expect($fresh->perlu_ditinjau_ulang)->toBeFalse();
});

it('recalculates net_amount when a keringanan is added after the tagihan was created', function () {
    $siswa = Siswa::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'default_amount' => 300000]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'total_tagihan' => 300000, 'net_amount' => 300000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 50000]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    app(RecalculateTagihanNominalAction::class)->execute($tagihan->id);

    $fresh = $tagihan->fresh();
    expect((float) $fresh->discount_amount)->toBe(50000.0);
    expect((float) $fresh->net_amount)->toBe(250000.0);
    expect($fresh->perlu_ditinjau_ulang)->toBeFalse();
});

it('flags perlu_ditinjau_ulang instead of applying when the new net_amount would be below paid_amount', function () {
    $siswa = Siswa::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'default_amount' => 300000]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'total_tagihan' => 300000, 'net_amount' => 300000, 'paid_amount' => 280000, 'status' => 'sebagian',
    ]);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 100000]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    app(RecalculateTagihanNominalAction::class)->execute($tagihan->id);

    $fresh = $tagihan->fresh();
    expect((float) $fresh->net_amount)->toBe(300000.0); // unchanged
    expect($fresh->perlu_ditinjau_ulang)->toBeTrue();
    expect($fresh->alasan_perlu_ditinjau)->toContain('lebih kecil dari yang sudah dibayar');
});

it('flags perlu_ditinjau_ulang instead of applying when the tagihan already has a skema cicilan', function () {
    $siswa = Siswa::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'default_amount' => 300000]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'total_tagihan' => 300000, 'net_amount' => 300000, 'paid_amount' => 0, 'status' => 'dicicil',
    ]);
    app(PembayaranService::class)->buatSkemaCicilan($tagihan, 3, 'admin');
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $siswa->lembaga_id]);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 50000]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    app(RecalculateTagihanNominalAction::class)->execute($tagihan->id);

    $fresh = $tagihan->fresh();
    expect((float) $fresh->net_amount)->toBe(300000.0);
    expect($fresh->perlu_ditinjau_ulang)->toBeTrue();
    expect($fresh->alasan_perlu_ditinjau)->toContain('cicilan');
});

it('does not recalculate a lunas or dibatalkan tagihan', function () {
    $siswa = Siswa::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'default_amount' => 300000]);
    $tagihanLunas = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'total_tagihan' => 300000, 'net_amount' => 300000, 'paid_amount' => 300000, 'status' => 'lunas',
    ]);

    app(RecalculateTagihanNominalAction::class)->execute($tagihanLunas->id);

    expect($tagihanLunas->fresh()->perlu_ditinjau_ulang)->toBeFalse();
});

it('re-evaluates a previously flagged tagihan and auto-clears the flag once the guard passes', function () {
    $siswa = Siswa::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'default_amount' => 300000]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'total_tagihan' => 300000, 'net_amount' => 300000, 'paid_amount' => 280000, 'status' => 'sebagian',
        'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'alasan basi sebelumnya',
    ]);

    // Situasi membaik: paid_amount turun secara hipotetis tidak realistis, jadi simulasikan
    // dengan menaikkan net_amount kembali (mis. keringanan yang tadinya bikin net_amount < paid_amount
    // dicabut lagi) -- tidak ada JenisTagihanKeringanan/SiswaKeringanan sama sekali, jadi resolve()
    // menghasilkan net_amount = default_amount = 300000, yang >= paid_amount 280000.
    app(RecalculateTagihanNominalAction::class)->execute($tagihan->id);

    $fresh = $tagihan->fresh();
    expect($fresh->perlu_ditinjau_ulang)->toBeFalse();
    expect($fresh->alasan_perlu_ditinjau)->toBeNull();
    expect($fresh->status)->toBe('sebagian');
});
