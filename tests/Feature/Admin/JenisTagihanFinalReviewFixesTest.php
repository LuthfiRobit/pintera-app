<?php
// tests/Feature/Admin/JenisTagihanFinalReviewFixesTest.php
//
// Regression tests for the final-review fixes on Sub-project 2b-1 (Form Jenis Tagihan):
// C1 (is_active checkbox uncheck), C2 (event-ordering vs sasaran sync),
// I1 (persen keringanan > 100 server-side cap), I2 (nominal()/simpanNominal() ppdb guard).

use App\Enums\StatusSiswa;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\KategoriKeringanan;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function buatUserKeuanganFinalReview(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    return [$user, $lembaga];
}

// C1
it('turns is_active off when the checkbox is left unchecked on update', function () {
    [$user, $lembaga] = buatUserKeuanganFinalReview();
    $jenisTagihan = JenisTagihan::create([
        'lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'spp',
        'bisa_dicicil' => false, 'mode' => 'manual', 'default_amount' => 500000, 'is_active' => true,
    ]);

    $response = $this->actingAs($user)->put(route('admin.jenis-tagihan.update', $jenisTagihan), [
        'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false,
        'mode' => 'manual', 'default_amount' => 500000,
        // 'is_active' intentionally omitted, as an unchecked checkbox would be
    ]);

    $response->assertRedirect(route('admin.jenis-tagihan.index'));
    expect($jenisTagihan->fresh()->is_active)->toBeFalse();
});

// C2
it('activating a jenis tagihan while also changing sasaran generates bills against the new sasaran, not the stale one', function () {
    [$user, $lembaga] = buatUserKeuanganFinalReview();

    $siswaAktif = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => StatusSiswa::Aktif->value]);
    $siswaLulus = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => StatusSiswa::Lulus->value]);

    $jenisTagihan = JenisTagihan::create([
        'lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'spp',
        'bisa_dicicil' => false, 'mode' => 'manual', 'default_amount' => 500000, 'is_active' => false,
    ]);
    $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'sasaran']);
    $grup->kriteria()->create(['field' => 'status_siswa', 'operator' => 'in', 'value' => ['lulus']]);

    // Flip is_active to true (fires BillTypeActivated) WHILE replacing sasaran to target 'aktif' instead of 'lulus'.
    $response = $this->actingAs($user)->put(route('admin.jenis-tagihan.update', $jenisTagihan), [
        'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false,
        'mode' => 'manual', 'default_amount' => 500000, 'is_active' => '1',
        'sasaran' => [
            ['kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]],
        ],
    ]);

    $response->assertRedirect(route('admin.jenis-tagihan.index'));
    expect($jenisTagihan->fresh()->is_active)->toBeTrue();

    $tagihanSiswaIds = Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)
        ->where('tagihable_type', Siswa::class)
        ->pluck('tagihable_id')
        ->all();

    expect($tagihanSiswaIds)->toContain($siswaAktif->id);
    expect($tagihanSiswaIds)->not->toContain($siswaLulus->id);
});

// I1
it('rejects a persen keringanan nilai above 100 with a 422', function () {
    [$user, $lembaga] = buatUserKeuanganFinalReview();
    $kategoriKeringanan = KategoriKeringanan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Yatim Piatu']);

    $response = $this->actingAs($user)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false,
        'keringanan' => [
            ['kategori_keringanan_id' => $kategoriKeringanan->id, 'tipe_potongan' => 'persen', 'nilai' => 150],
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['keringanan.0.nilai']);
    expect(JenisTagihan::where('nama', 'SPP Bulanan')->exists())->toBeFalse();
});

it('allows a fixed keringanan nilai above 100', function () {
    [$user, $lembaga] = buatUserKeuanganFinalReview();
    $kategoriKeringanan = KategoriKeringanan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Yatim Piatu']);

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [
        'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false,
        'keringanan' => [
            ['kategori_keringanan_id' => $kategoriKeringanan->id, 'tipe_potongan' => 'fixed', 'nilai' => 150000],
        ],
    ]);

    $response->assertRedirect(route('admin.jenis-tagihan.index'));
    expect(JenisTagihan::where('nama', 'SPP Bulanan')->exists())->toBeTrue();
});

// I2
it('rejects nominal() and simpanNominal() access for a non-ppdb kategori like spp', function () {
    [$user, $lembaga] = buatUserKeuanganFinalReview();
    $jenisTagihan = JenisTagihan::create([
        'lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'spp',
        'bisa_dicicil' => false, 'mode' => 'manual', 'default_amount' => 500000,
    ]);

    $this->actingAs($user)->get(route('admin.jenis-tagihan.nominal', $jenisTagihan))
        ->assertRedirect(route('admin.jenis-tagihan.index'))
        ->assertSessionHasErrors('kategori');

    $this->actingAs($user)->post(route('admin.jenis-tagihan.nominal.store', $jenisTagihan), ['nominal' => []])
        ->assertRedirect(route('admin.jenis-tagihan.index'))
        ->assertSessionHasErrors('kategori');
});
