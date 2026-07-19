<?php
// tests/Feature/Admin/JenisTagihanTest.php

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\TagihanItem;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function buatLembagaDenganJalurUntukTagihan(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);

    return [$lembaga, $tahunAjaran, $jalur];
}

it('denies jenis tagihan management without permission', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->get(route('admin.jenis-tagihan.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [])->assertForbidden();
});

it('lets admin_keuangan create a jenis tagihan scoped to their own lembaga', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [
        'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false,
    ]);

    $response->assertRedirect();
    $jenisTagihan = JenisTagihan::where('nama', 'Biaya Pendaftaran')->first();
    expect($jenisTagihan)->not->toBeNull();
    expect($jenisTagihan->lembaga_id)->toBe($lembaga->id);
});

it('lets admin_keuangan set nominal per jalur, rejecting a duplicate pair at the db level', function () {
    [$lembaga, , $jalur] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.nominal.store', $jenisTagihan), [
        'nominal' => [$jalur->id => 150000],
    ]);

    $response->assertRedirect();
    expect(NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->where('jalur_ppdb_id', $jalur->id)->first()->nominal)->toEqual(150000);

    // Re-saving with a different value updates in place (updateOrCreate), never duplicates the pair.
    $this->actingAs($user)->post(route('admin.jenis-tagihan.nominal.store', $jenisTagihan), ['nominal' => [$jalur->id => 200000]]);
    expect(NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->where('jalur_ppdb_id', $jalur->id)->count())->toBe(1);
    expect(NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->where('jalur_ppdb_id', $jalur->id)->first()->nominal)->toEqual(200000);
});

it('only lists jenis tagihan belonging to the acting lembaga-scoped user own lembaga', function () {
    [$lembagaA] = buatLembagaDenganJalurUntukTagihan();
    [$lembagaB] = buatLembagaDenganJalurUntukTagihan();
    JenisTagihan::create(['lembaga_id' => $lembagaA->id, 'nama' => 'Punya A', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    JenisTagihan::create(['lembaga_id' => $lembagaB->id, 'nama' => 'Punya B', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    $user = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.index'));

    $response->assertOk()->assertSee('Punya A')->assertDontSee('Punya B');
});

it('does not send kategori lainnya through the jalur-based nominal flow after create', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [
        'nama' => 'SPP Bulanan', 'kategori' => 'lainnya', 'bisa_dicicil' => false,
    ]);

    $jenisTagihan = JenisTagihan::where('nama', 'SPP Bulanan')->firstOrFail();
    $response->assertRedirect(route('admin.jenis-tagihan.edit', $jenisTagihan));
});

it('redirects away from the nominal page for a kategori lainnya jenis tagihan instead of showing a jalur list', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'lainnya', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.nominal', $jenisTagihan));

    $response->assertRedirect(route('admin.jenis-tagihan.edit', $jenisTagihan));
    $response->assertSessionHasErrors('kategori');
});

it('rejects a direct post to simpan nominal for a kategori lainnya jenis tagihan without creating rows', function () {
    [$lembaga, , $jalur] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'lainnya', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.nominal.store', $jenisTagihan), [
        'nominal' => [$jalur->id => 100000],
    ]);

    $response->assertRedirect(route('admin.jenis-tagihan.edit', $jenisTagihan));
    $response->assertSessionHasErrors('kategori');
    expect(NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->exists())->toBeFalse();
});

it('denies kepala_sekolah from creating a jenis tagihan (view-only role for this module)', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [
        'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false,
    ])->assertForbidden();
});

it('blocks deleting a jenis tagihan already billed to a registrant, naming the number of tagihan', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    TagihanItem::factory()->create(['jenis_tagihan_id' => $jenisTagihan->id]);

    $response = $this->actingAs($user)->delete(route('admin.jenis-tagihan.destroy', $jenisTagihan));

    $response->assertRedirect()->assertSessionHasErrors([
        'jenis_tagihan' => 'Tidak bisa dihapus, sudah dipakai di 1 tagihan milik calon murid.',
    ]);
    expect(JenisTagihan::find($jenisTagihan->id))->not->toBeNull();
});

it('blocks deleting a jenis tagihan with configured nominal but no real billing yet', function () {
    [$lembaga, , $jalur] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);

    $response = $this->actingAs($user)->delete(route('admin.jenis-tagihan.destroy', $jenisTagihan));

    $response->assertRedirect()->assertSessionHasErrors([
        'jenis_tagihan' => 'Tidak bisa dihapus, sudah ada 1 nominal jalur yang dikonfigurasi. Hapus dulu di halaman Kelola Nominal.',
    ]);
    expect(JenisTagihan::find($jenisTagihan->id))->not->toBeNull();
});

it('allows deleting a jenis tagihan with no related tagihan or nominal rows', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->delete(route('admin.jenis-tagihan.destroy', $jenisTagihan));

    $response->assertRedirect(route('admin.jenis-tagihan.index'));
    expect(JenisTagihan::find($jenisTagihan->id))->toBeNull();
});

it('responds with json when deleting a jenis tagihan blocked by real billing rows', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    TagihanItem::factory()->create(['jenis_tagihan_id' => $jenisTagihan->id]);

    $response = $this->actingAs($user)->deleteJson(route('admin.jenis-tagihan.destroy', $jenisTagihan));

    $response->assertStatus(422)->assertJson(['message' => 'Tidak bisa dihapus, sudah dipakai di 1 tagihan milik calon murid.']);
});

it('responds with json on a successful jenis tagihan delete', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->deleteJson(route('admin.jenis-tagihan.destroy', $jenisTagihan));

    $response->assertOk()->assertJson(['message' => 'Jenis tagihan berhasil dihapus.']);
});
