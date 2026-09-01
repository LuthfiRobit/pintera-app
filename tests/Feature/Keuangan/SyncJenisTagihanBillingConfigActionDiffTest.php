<?php

use App\Domains\Keuangan\Actions\JenisTagihan\SyncJenisTagihanBillingConfigAction;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\KategoriKeringanan;

it('reports tarifBerubah=false and keringananBerubah=false when the billing config is unchanged', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $action = app(SyncJenisTagihanBillingConfigAction::class);

    $billing = ['tarif' => [['nominal' => 100000, 'kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]]], 'keringanan' => []];

    $action->execute($jenisTagihan, $billing); // panggilan pertama: dari kosong -> ada isi, JELAS berubah
    $result = $action->execute($jenisTagihan, $billing); // panggilan kedua: payload IDENTIK

    expect($result->tarifBerubah)->toBeFalse();
    expect($result->keringananBerubah)->toBeFalse();
});

it('reports tarifBerubah=true when a tarif grup nominal changes', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $action = app(SyncJenisTagihanBillingConfigAction::class);

    $action->execute($jenisTagihan, ['tarif' => [['nominal' => 100000, 'kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]]], 'keringanan' => []]);
    $result = $action->execute($jenisTagihan, ['tarif' => [['nominal' => 150000, 'kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]]], 'keringanan' => []]);

    expect($result->tarifBerubah)->toBeTrue();
});

it('reports tarifBerubah=true when a tarif grup is removed entirely', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $action = app(SyncJenisTagihanBillingConfigAction::class);

    $action->execute($jenisTagihan, ['tarif' => [['nominal' => 100000, 'kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]]], 'keringanan' => []]);
    $result = $action->execute($jenisTagihan, ['tarif' => [], 'keringanan' => []]);

    expect($result->tarifBerubah)->toBeTrue();
});

it('reports keringananBerubah=true when a keringanan rule nilai changes, unrelated to tarif', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $lembaga = $jenisTagihan->lembaga;
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id]);
    $action = app(SyncJenisTagihanBillingConfigAction::class);

    $action->execute($jenisTagihan, ['tarif' => [], 'keringanan' => [['kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 50000]]]);
    $result = $action->execute($jenisTagihan, ['tarif' => [], 'keringanan' => [['kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 75000]]]);

    expect($result->keringananBerubah)->toBeTrue();
    expect($result->tarifBerubah)->toBeFalse();
});

it('does not blow up and reports both false when billing is null both times', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $action = app(SyncJenisTagihanBillingConfigAction::class);

    $action->execute($jenisTagihan, null);
    $result = $action->execute($jenisTagihan, null);

    expect($result->tarifBerubah)->toBeFalse();
    expect($result->keringananBerubah)->toBeFalse();
});
