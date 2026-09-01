<?php

// tests/Feature/Admin/TagihanIndexTest.php

use App\Domains\Identity\Models\Person;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\CalonMurid;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

// tests/Pest.php's buatPendaftaranUntukAdmin() always creates a brand-new Yayasan for the
// CalonMurid it builds, even when an existing $lembaga is passed in — so a "second peserta in
// the same lembaga" ends up with a Person under an unrelated Yayasan. That mismatch was
// harmless before identity-v1 (CalonMurid carried no yayasan scope of its own), but now
// Person's YayasanScope hides that CalonMurid's name from anyone correctly scoped to the real
// lembaga. Realign it here (in this assigned test file) rather than editing the shared fixture.
function buatPendaftaranSatuLembagaTagihan(Lembaga $lembaga, string $namaCalon): Pendaftaran
{
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga, $namaCalon);
    $calonMurid = CalonMurid::withoutGlobalScopes()->find($pendaftaran->calon_murid_id);
    Person::withoutGlobalScopes()->whereKey($calonMurid->person_id)->update(['yayasan_id' => $lembaga->yayasan_id]);

    return $pendaftaran;
}

it('denies access to the tagihan list without the tagihan.view permission', function () {
    [$lembaga] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->get(route('admin.tagihan.index'))->assertForbidden();
    $this->actingAs($user)->getJson(route('admin.tagihan.data'))->assertForbidden();
});

it('shows the index page with the view permission', function () {
    [$lembaga] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $this->actingAs($user)->get(route('admin.tagihan.index'))->assertOk();
});

it('returns only tagihan belonging to the acting user own lembaga, via the linked pendaftaran', function () {
    [$lembagaA, $jalurA, , $pendaftaranA] = buatPendaftaranUntukAdmin(namaCalon: 'Milik A');
    [$lembagaB, $jalurB, , $pendaftaranB] = buatPendaftaranUntukAdmin(namaCalon: 'Milik B');
    $jenisTagihanA = JenisTagihan::create(['lembaga_id' => $lembagaA->id, 'nama' => 'Biaya A', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    $jenisTagihanB = JenisTagihan::create(['lembaga_id' => $lembagaB->id, 'nama' => 'Biaya B', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    Tagihan::factory()->create(['pendaftaran_id' => $pendaftaranA->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 100000, 'status' => 'belum_bayar']);
    Tagihan::factory()->create(['pendaftaran_id' => $pendaftaranB->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 200000, 'status' => 'belum_bayar']);
    $user = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $user->assignRole('bendahara_lembaga');

    $response = $this->actingAs($user)->getJson(route('admin.tagihan.data'));

    $names = collect($response->json('data'))->pluck('nama_calon_murid');
    expect($names)->toContain('Milik A');
    expect($names)->not->toContain('Milik B');
});

it('filters by search on candidate name or kode pendaftaran', function () {
    [$lembaga, , , $pendaftaranAhmad] = buatPendaftaranUntukAdmin(namaCalon: 'Ahmad Fauzan');
    $pendaftaranBudi = buatPendaftaranSatuLembagaTagihan($lembaga, 'Budi Santoso');
    Tagihan::factory()->create(['pendaftaran_id' => $pendaftaranAhmad->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 100000, 'status' => 'belum_bayar']);
    Tagihan::factory()->create(['pendaftaran_id' => $pendaftaranBudi->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 100000, 'status' => 'belum_bayar']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $responseNama = $this->actingAs($user)->getJson(route('admin.tagihan.data', ['search' => 'Ahmad']));
    expect(collect($responseNama->json('data'))->pluck('nama_calon_murid'))->toContain('Ahmad Fauzan')
        ->not->toContain('Budi Santoso');

    $kode = $pendaftaranBudi->fresh()->kode_pendaftaran;
    $responseKode = $this->actingAs($user)->getJson(route('admin.tagihan.data', ['search' => $kode]));
    expect(collect($responseKode->json('data'))->pluck('nama_calon_murid'))->toContain('Budi Santoso')
        ->not->toContain('Ahmad Fauzan');

    $responseTidakAda = $this->actingAs($user)->getJson(route('admin.tagihan.data', ['search' => 'Tidak Ada Sama Sekali']));
    expect($responseTidakAda->json('data'))->toBeEmpty();
});

it('filters by status', function () {
    [$lembaga, , , $pendaftaranLunas] = buatPendaftaranUntukAdmin(namaCalon: 'Sudah Lunas');
    [, , , $pendaftaranBelumBayar] = buatPendaftaranUntukAdmin($lembaga, 'Belum Bayar');
    Tagihan::factory()->create(['pendaftaran_id' => $pendaftaranLunas->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 0, 'status' => 'lunas']);
    Tagihan::factory()->create(['pendaftaran_id' => $pendaftaranBelumBayar->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 100000, 'status' => 'belum_bayar']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $response = $this->actingAs($user)->getJson(route('admin.tagihan.data', ['status' => 'lunas']));

    $names = collect($response->json('data'))->pluck('nama_calon_murid');
    expect($names)->toContain('Sudah Lunas');
    expect($names)->not->toContain('Belum Bayar');
});

it('404s on catatManualTagihan when tagihan belongs to a different lembaga', function () {
    [$lembagaA, , , $pendaftaranA] = buatPendaftaranUntukAdmin(namaCalon: 'Milik A');
    [$lembagaB, , , $pendaftaranB] = buatPendaftaranUntukAdmin(namaCalon: 'Milik B');
    $tagihanB = Tagihan::factory()->create(['pendaftaran_id' => $pendaftaranB->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 200000, 'status' => 'belum_bayar']);
    $userA = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $userA->assignRole('bendahara_lembaga');

    $response = $this->actingAs($userA)->post(route('admin.tagihan.catat-manual', $tagihanB));

    $response->assertNotFound();
});
