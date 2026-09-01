<?php
// tests/Feature/Keuangan/BillTypeActivatedEventTest.php
//
// BillTypeActivated is dispatched by UpdateJenisTagihanAction (not a model
// booted() hook — models here hold zero business logic per
// laravel-feature-standard/SKILL.md), so these exercise the real HTTP
// update route rather than calling ->update() directly on the model.

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function buatUserKeuanganBillTypeActivated(int $lembagaId): User
{
    $user = User::factory()->create(['lembaga_id' => $lembagaId]);
    $user->assignRole('bendahara_lembaga');

    return $user;
}

it('generates tagihan for matching siswa when is_active flips from false to true', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 300000, 'mode' => 'manual', 'is_active' => false]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    $user = buatUserKeuanganBillTypeActivated($jenisTagihan->lembaga_id);

    expect(Tagihan::where('tagihable_id', $siswa->id)->exists())->toBeFalse();

    $this->actingAs($user)->put(route('admin.jenis-tagihan.update', $jenisTagihan), [
        'nama' => $jenisTagihan->nama, 'kategori' => $jenisTagihan->kategori->value, 'bisa_dicicil' => false,
        'mode' => 'manual', 'tipe' => 'bulanan', 'default_amount' => 300000, 'is_active' => '1',
    ]);

    expect(Tagihan::where('tagihable_id', $siswa->id)->where('jenis_tagihan_id', $jenisTagihan->id)->exists())->toBeTrue();
});

it('does not fire again when is_active is saved as true a second time without changing', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 300000, 'mode' => 'manual', 'is_active' => true]);
    Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    Tagihan::query()->delete();
    $user = buatUserKeuanganBillTypeActivated($jenisTagihan->lembaga_id);

    $this->actingAs($user)->put(route('admin.jenis-tagihan.update', $jenisTagihan), [
        'nama' => 'Nama Diperbarui', 'kategori' => $jenisTagihan->kategori->value, 'bisa_dicicil' => false,
        'mode' => 'manual', 'tipe' => 'bulanan', 'default_amount' => 300000, 'is_active' => '1', // is_active tidak berubah
    ]);

    expect(Tagihan::count())->toBe(0);
});

it('does not throw and does not generate anything when a ppdb-kategori jenis_tagihan is reactivated', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'pendaftaran', 'is_active' => false]);
    Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    $user = buatUserKeuanganBillTypeActivated($jenisTagihan->lembaga_id);

    $this->actingAs($user)->put(route('admin.jenis-tagihan.update', $jenisTagihan), [
        'nama' => $jenisTagihan->nama, 'kategori' => 'pendaftaran', 'bisa_dicicil' => false,
        'mode' => 'manual', 'is_active' => '1',
    ]);

    expect(Tagihan::count())->toBe(0);
    expect(\App\Domains\Keuangan\Models\BillingJobLog::count())->toBe(0);
});
