<?php

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatAdminJalur(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['jalur-ppdb.view', 'jalur-ppdb.create', 'jalur-ppdb.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['jalur-ppdb.view', 'jalur-ppdb.create', 'jalur-ppdb.edit']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);

    return [$lembaga, $user, $tahunAjaran];
}

it('creates a jalur scoped to the active tahun ajaran', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    $response = $this->actingAs($user)->post(route('admin.jalur-ppdb.store'), [
        'nama' => 'Prestasi',
        'deskripsi' => 'Jalur berdasarkan nilai rapor',
    ]);

    $jalur = JalurPpdb::first();
    $response->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));
    expect($jalur->tahun_ajaran_id)->toBe($tahunAjaran->id);
    expect($jalur->status_aktif)->toBeTrue();
});

it('shows the kelengkapan indicator as empty when a jalur has no children yet', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();
    $this->actingAs($user);

    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);

    $response = $this->get(route('admin.jalur-ppdb.edit', $jalur));

    $response->assertOk();
    $response->assertSee('Formulir (0)');
    $response->assertSee('Dokumen (0)');
    $response->assertSee('Seleksi (0)');
});

it('404s when a lembaga-scoped admin opens the edit page for a jalur in another lembaga', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    $otherTahun = TahunAjaran::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $otherJalur = JalurPpdb::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'tahun_ajaran_id' => $otherTahun->id, 'nama' => 'Reguler',
    ]);

    $this->actingAs($user)->get(route('admin.jalur-ppdb.edit', $otherJalur))->assertNotFound();
});

it('denies access without the manage-ppdb permission', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();
    $noRoleUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noRoleUser)->get(route('admin.jalur-ppdb.index'))->assertForbidden();
});

it('rejects a duplicate jalur nama within the same tahun ajaran instead of crashing', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi']);

    $this->actingAs($user)->post(route('admin.jalur-ppdb.store'), [
        'nama' => 'Prestasi',
        'deskripsi' => 'Jalur berdasarkan nilai rapor',
    ])->assertSessionHasErrors('nama');

    expect(JalurPpdb::count())->toBe(1);
});

it('lets an update keep its own unchanged nama without a false-positive uniqueness error', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi']);

    $this->actingAs($user)->put(route('admin.jalur-ppdb.update', $jalur), [
        'nama' => 'Prestasi',
        'deskripsi' => 'Jalur berdasarkan nilai rapor, diperbarui',
        'status_aktif' => true,
    ])->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));

    expect($jalur->fresh()->deskripsi)->toBe('Jalur berdasarkan nilai rapor, diperbarui');
});

it('does not show the "Salin dari" callout when the only prior tahun ajaran has no gelombang or jalur data', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => false,
    ]);

    $this->actingAs($user)->get(route('admin.jalur-ppdb.index'))
        ->assertOk()
        ->assertDontSee('Salin dari');
});

it('rejects deactivating a jalur that is still used by a gelombang', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler', 'status_aktif' => true]);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 10,
    ]);
    $gelombang->jalur()->attach($jalur->id);

    // This is exactly the scenario that previously wiped the gelombang's
    // pivot silently: the only jalur it uses gets deactivated, then any
    // save on the gelombang cleared the restriction with no warning.
    $this->actingAs($user)->put(route('admin.jalur-ppdb.update', $jalur), [
        'nama' => 'Reguler',
        'deskripsi' => null,
        'status_aktif' => 0,
    ])->assertSessionHasErrors('status_aktif');

    expect($jalur->fresh()->status_aktif)->toBeTrue();
    expect($gelombang->jalur()->count())->toBe(1);
});

it('names the affected gelombang in the deactivation error message', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler', 'status_aktif' => true]);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 10,
    ]);
    $gelombang->jalur()->attach($jalur->id);

    $this->actingAs($user)->put(route('admin.jalur-ppdb.update', $jalur), [
        'nama' => 'Reguler',
        'deskripsi' => null,
        'status_aktif' => 0,
    ]);

    expect(session('errors')->get('status_aktif')[0])->toContain('Gelombang 1');
});

it('allows deactivating a jalur that is not used by any gelombang', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler', 'status_aktif' => true]);

    $this->actingAs($user)->put(route('admin.jalur-ppdb.update', $jalur), [
        'nama' => 'Reguler',
        'deskripsi' => null,
        'status_aktif' => 0,
    ])->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));

    expect($jalur->fresh()->status_aktif)->toBeFalse();
});

it('allows reactivating a jalur without any restriction check', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler', 'status_aktif' => false]);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 10,
    ]);
    // A pivot row referencing an already-inactive jalur can only exist from
    // data created before this safeguard — reactivating must never be
    // blocked regardless, only the true -> false transition is guarded.
    $gelombang->jalur()->attach($jalur->id);

    $this->actingAs($user)->put(route('admin.jalur-ppdb.update', $jalur), [
        'nama' => 'Reguler',
        'deskripsi' => null,
        'status_aktif' => 1,
    ])->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));

    expect($jalur->fresh()->status_aktif)->toBeTrue();
});

it('lets the tahun_ajaran filter browse a past year instead of only the active one', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();
    JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Jalur Baru']);

    $tahunLama = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => false,
    ]);
    JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id, 'nama' => 'Jalur Lama']);

    $this->actingAs($user)->get(route('admin.jalur-ppdb.index', ['tahun_ajaran' => $tahunLama->id]))
        ->assertOk()
        ->assertSee('Jalur Lama')
        ->assertDontSee('Jalur Baru');
});

it('filters the index by nama when cari is given', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();
    JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi']);

    $this->actingAs($user)->get(route('admin.jalur-ppdb.index', ['cari' => 'Reg']))
        ->assertOk()
        ->assertSee('Reguler')
        ->assertDontSee('Prestasi');
});

it('shows a "Dipakai di N Gelombang" badge on the index for a jalur in use', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 10,
    ]);
    $gelombang->jalur()->attach($jalur->id);

    $this->actingAs($user)->get(route('admin.jalur-ppdb.index'))
        ->assertOk()
        ->assertSee('Dipakai di 1 Gelombang');
});

it('shows a "Tidak Dipakai" badge on the index for a jalur not used by any gelombang', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);

    $this->actingAs($user)->get(route('admin.jalur-ppdb.index'))
        ->assertOk()
        ->assertSee('Tidak Dipakai');
});

it('shows the Gelombang kelengkapan badge and gelombang names on the edit page', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 10,
    ]);
    $gelombang->jalur()->attach($jalur->id);

    $this->actingAs($user)->get(route('admin.jalur-ppdb.edit', $jalur))
        ->assertOk()
        ->assertSee('Gelombang (1)')
        ->assertSee('Dipakai di gelombang: Gelombang 1');
});

it('shows a "tidak dipakai" message near the status toggle when no gelombang uses the jalur', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);

    $this->actingAs($user)->get(route('admin.jalur-ppdb.edit', $jalur))
        ->assertOk()
        ->assertSee('Gelombang (0)')
        ->assertSee('Tidak dipakai di gelombang manapun saat ini.');
});
