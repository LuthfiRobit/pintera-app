<?php

use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanResyncControllerUser(): array
{
    foreach (['kurikulum-assignment.view', 'kurikulum-assignment.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'operator_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kurikulum-assignment.view', 'kurikulum-assignment.edit']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return [$manager, $lembaga, $ta];
}

it('shows the diff table for kelas whose kurikulum drifted from the live assignment', function () {
    [$manager, $lembaga, $ta] = siapkanResyncControllerUser();

    KurikulumAssignment::create([
        'lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13',
    ]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13']);
    KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()->update(['kurikulum' => 'merdeka']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.resync', [
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id,
    ]));

    $response->assertOk();
    $response->assertSee($kelas->nama);
});

it('applies resync via POST and redirects with success status', function () {
    [$manager, $lembaga, $ta] = siapkanResyncControllerUser();

    KurikulumAssignment::create([
        'lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13',
    ]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13']);
    KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()->update(['kurikulum' => 'merdeka']);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.resync.apply'), [
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $ta->id,
        'kelas_ids' => [$kelas->id],
    ])->assertRedirect(route('admin.kurikulum-assignment.resync', ['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id]));

    expect($kelas->fresh()->kurikulum->value)->toBe('merdeka');
});

it('rejects resync for a kelas belonging to a different lembaga (cross-tenant guard)', function () {
    [$manager, $lembaga, $ta] = siapkanResyncControllerUser();
    $lembagaLain = Lembaga::factory()->create();
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id])->id, 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.resync.apply'), [
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $ta->id,
        'kelas_ids' => [$kelasLain->id],
    ])->assertForbidden();

    expect($kelasLain->fresh()->kurikulum->value)->toBe('k13');
});

it('halaman resync membungkus submit sinkronisasi dengan confirmDialog (bukan submit langsung)', function () {
    [$manager, $lembaga, $ta] = siapkanResyncControllerUser();
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13']);
    KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()->update(['kurikulum' => 'merdeka']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.resync', [
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id,
    ]));

    $response->assertOk();
    $response->assertSee('confirmDialog', false);
    $response->assertSee('x-model="terpilih"', false);
});

it('menampilkan empty state instruksional sebelum lembaga/tahun ajaran dipilih', function () {
    [$manager, $lembaga, $ta] = siapkanResyncControllerUser();

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.resync'));

    $response->assertOk();
    $response->assertSee('Pilih Lembaga & Tahun Ajaran untuk Memindai');
});

it('menampilkan zero-drift success state kalau tidak ada perbedaan', function () {
    [$manager, $lembaga, $ta] = siapkanResyncControllerUser();
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'merdeka']);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'merdeka']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.resync', [
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id,
    ]));

    $response->assertOk();
    $response->assertSee('Semua Kelas Sudah Selaras');
});

it('menampilkan nama fase lama (bukan id mentah) dan floating bulk bar saat ada drift', function () {
    [$manager, $lembaga, $ta] = siapkanResyncControllerUser();
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13', 'fase_id' => null]);
    KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()->update(['kurikulum' => 'merdeka']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.resync', [
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id,
    ]));

    $response->assertOk();
    $response->assertSee('Tanpa Fase');
    $response->assertSee('terpilih.length', false);
});

it('tidak ada sisa wording lama "Cek Drift"/"Sinkronkan yang Dicentang" di halaman resync', function () {
    [$manager, $lembaga, $ta] = siapkanResyncControllerUser();

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.resync'));

    $response->assertOk();
    $response->assertDontSee('Cek Drift');
    $response->assertSee('Pindai Keselarasan');
});

it('mode selected lembaga di yayasan switcher mengunci scan ke lembaga aktif dan tidak menampilkan opsi lembaga lain', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'Unit SD Pintera']);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'Unit SMP Pintera']);

    foreach (['kurikulum-assignment.view', 'kurikulum-assignment.edit'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_admin_resync', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kurikulum-assignment.view', 'kurikulum-assignment.edit']);
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null]);
    $manager->assignRole($role);

    $response = $this->actingAs($manager)
        ->withSession(['active_lembaga_id' => $lembagaA->id])
        ->get(route('admin.kurikulum-assignment.resync'));

    $response->assertOk();
    $response->assertSee($lembagaA->nama);
    // Tidak boleh ada option untuk lembaga B
    $response->assertDontSee('<option value="'.$lembagaB->id.'"', false);
    // Harus ada hidden input lembaga_id mengunci ke lembaga A
    $response->assertSee('name="lembaga_id" value="'.$lembagaA->id.'"', false);
});

it('mode selected lembaga menolak apply resync untuk lembaga selain lembaga aktif di sesi', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $taB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $taB->id]);

    foreach (['kurikulum-assignment.view', 'kurikulum-assignment.edit'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_admin_resync_apply', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kurikulum-assignment.view', 'kurikulum-assignment.edit']);
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null]);
    $manager->assignRole($role);

    $this->actingAs($manager)
        ->withSession(['active_lembaga_id' => $lembagaA->id])
        ->post(route('admin.kurikulum-assignment.resync.apply'), [
            'lembaga_id' => $lembagaB->id,
            'tahun_ajaran_id' => $taB->id,
            'kelas_ids' => [$kelasB->id],
        ])->assertForbidden();
});

it('mode agregat yayasan tanpa active_lembaga_id hanya menampilkan lembaga milik yayasan sendiri', function () {
    $yayasan1 = Yayasan::factory()->create();
    $yayasan2 = Yayasan::factory()->create();
    $lembaga1 = Lembaga::factory()->create(['yayasan_id' => $yayasan1->id, 'nama' => 'Lembaga Milik Yayasan 1']);
    $lembaga2 = Lembaga::factory()->create(['yayasan_id' => $yayasan2->id, 'nama' => 'Lembaga Asing Yayasan 2']);

    foreach (['kurikulum-assignment.view', 'kurikulum-assignment.edit'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_admin_aggregate', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kurikulum-assignment.view', 'kurikulum-assignment.edit']);
    $manager = User::factory()->create(['yayasan_id' => $yayasan1->id, 'lembaga_id' => null]);
    $manager->assignRole($role);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.resync'));

    $response->assertOk();
    $response->assertSee($lembaga1->nama);
    $response->assertDontSee($lembaga2->nama);
});

it('checkbox di halaman resync memiliki class styling standar Pintera', function () {
    [$manager, $lembaga, $ta] = siapkanResyncControllerUser();
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13']);
    KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()->update(['kurikulum' => 'merdeka']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.resync', [
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id,
    ]));

    $response->assertOk();
    $response->assertSee('class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 transition cursor-pointer"', false);
});
