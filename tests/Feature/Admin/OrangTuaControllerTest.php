<?php

use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\Yayasan;

it('scopes siswa_count on the index to the acting lembaga, not the orang tua total across the whole yayasan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaA = Siswa::factory()->create(['lembaga_id' => $lembagaA->id]);
    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembagaB->id]);
    $orangTua = OrangTua::factory()->create(['yayasan_id' => $yayasan->id]);
    $orangTua->siswa()->attach($siswaA->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $orangTua->siswa()->attach($siswaB->id, ['hubungan' => 'ayah', 'is_kontak_utama' => false]);

    $managerLembagaA = actingAsOrangTuaManager();
    $managerLembagaA->update(['lembaga_id' => $lembagaA->id]);
    $responseLembaga = $this->actingAs($managerLembagaA)->get(route('admin.orang-tua.index'));
    $responseLembaga->assertOk();
    $itemLembaga = collect($responseLembaga->viewData('orangTuaList'))->firstWhere('id', $orangTua->id);
    expect($itemLembaga->siswa_count)->toBe(1);

    $managerYayasan = actingAsSiswaOrangTuaManager();
    $managerYayasan->update(['yayasan_id' => $yayasan->id]);
    $responseYayasan = $this->actingAs($managerYayasan)->get(route('admin.orang-tua.index'));
    $responseYayasan->assertOk();
    $itemYayasan = collect($responseYayasan->viewData('orangTuaList'))->firstWhere('id', $orangTua->id);
    expect($itemYayasan->siswa_count)->toBe(2);
});
