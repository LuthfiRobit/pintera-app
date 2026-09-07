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

it('filters the orang tua index by anak query param without relying on a stale client snapshot', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTuaDenganAnak = OrangTua::factory()->create(['yayasan_id' => $yayasan->id]);
    $orangTuaDenganAnak->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $orangTuaTanpaAnak = OrangTua::factory()->create(['yayasan_id' => $yayasan->id]);

    $manager = actingAsOrangTuaManager();
    $manager->update(['lembaga_id' => $lembaga->id]);

    $responseAda = $this->actingAs($manager)->get(route('admin.orang-tua.index', ['anak' => 'ada']));
    $responseAda->assertOk();
    $idsAda = collect($responseAda->viewData('orangTuaList'))->pluck('id');
    expect($idsAda)->toContain($orangTuaDenganAnak->id)->not->toContain($orangTuaTanpaAnak->id);

    $responseBelum = $this->actingAs($manager)->get(route('admin.orang-tua.index', ['anak' => 'belum']));
    $responseBelum->assertOk();
    $idsBelum = collect($responseBelum->viewData('orangTuaList'))->pluck('id');
    expect($idsBelum)->toContain($orangTuaTanpaAnak->id)->not->toContain($orangTuaDenganAnak->id);
});

it('renders server-side filter links and badge counts on the orang tua index view', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTuaDenganAnak = OrangTua::factory()->create(['yayasan_id' => $yayasan->id]);
    $orangTuaDenganAnak->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $orangTuaTanpaAnak = OrangTua::factory()->create(['yayasan_id' => $yayasan->id]);

    $manager = actingAsOrangTuaManager();
    $manager->update(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->get(route('admin.orang-tua.index', ['anak' => 'ada']));
    $response->assertOk();
    $response->assertViewHas('totalAda', 1);
    $response->assertViewHas('totalBelum', 1);
    $response->assertViewHas('anakFilter', 'ada');
    $response->assertSee("activeFilter: 'tertaut'", false);
    $response->assertSee(route('admin.orang-tua.index', ['anak' => 'ada']));
    $response->assertSee(route('admin.orang-tua.index', ['anak' => 'belum']));
});
