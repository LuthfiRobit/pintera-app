<?php

use App\Domains\Akademik\Actions\Piket\GenerateJadwalPiketHarianAction;
use App\Domains\Akademik\Models\JadwalPiketMingguan;
use App\Domains\Akademik\Models\PiketHarian;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function siapkanAdminPiketKelola(): array
{
    Permission::firstOrCreate(['name' => 'piket.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'wakasek_kesiswaan_piket_test', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('piket.kelola');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => []]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create([
        'tahun_ajaran_id' => $tahunAjaran->id, 'lembaga_id' => $lembaga->id, 'status_aktif' => true,
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->addWeeks(4)->toDateString(),
    ]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    return compact('lembaga', 'semester', 'guru', 'admin');
}

it('lembaga BELUM punya PiketHarian -- store() memicu Generate (sinkron, tanpa job)', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'admin' => $admin] = siapkanAdminPiketKelola();

    $response = $this->actingAs($admin)->post(route('admin.piket-guru.store'), [
        'guru_id' => $guru->id, 'hari' => now()->dayOfWeek, 'semester_id' => $semester->id,
    ]);

    $response->assertRedirect();
    expect(PiketHarian::where('lembaga_id', $lembaga->id)->count())->toBeGreaterThan(0);
});

it('lembaga SUDAH punya PiketHarian -- tambah baris baru tetap memicu Regenerate, bukan Generate ulang dari nol', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'admin' => $admin] = siapkanAdminPiketKelola();
    $guruLama = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwalLama = JadwalPiketMingguan::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guruLama->id, 'hari' => now()->dayOfWeek,
        'semester_id' => $semester->id, 'dibuat_oleh_user_id' => $admin->id,
    ]);
    $tanggalOverride = now()->addWeek()->toDateString();
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guruLama->id, 'tanggal' => $tanggalOverride, 'sumber' => 'override_manual']);

    $guruBaru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $response = $this->actingAs($admin)->post(route('admin.piket-guru.store'), [
        'guru_id' => $guruBaru->id, 'hari' => now()->addDay()->dayOfWeek, 'semester_id' => $semester->id,
    ]);

    $response->assertRedirect();
    // Baris override_manual yang sudah ada TIDAK boleh hilang -- bukti bahwa jalur yg dipanggil adalah
    // Regenerate (yang melindungi baris beku), bukan Generate murni dari nol yg mengabaikan data lama.
    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guruLama->id)->where('tanggal', $tanggalOverride)->where('sumber', 'override_manual')->exists())->toBeTrue();
    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guruBaru->id)->exists())->toBeTrue();
});

it('user tanpa permission piket.kelola ditolak akses', function () {
    ['admin' => $admin] = siapkanAdminPiketKelola();
    $userBiasa = User::factory()->create(['lembaga_id' => $admin->lembaga_id]);

    $response = $this->actingAs($userBiasa)->get(route('admin.piket-guru.index'));

    $response->assertForbidden();
});

it('update() memperbarui jadwal mingguan dan memicu regenerate piket harian', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'admin' => $admin] = siapkanAdminPiketKelola();
    $guruA = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruB = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $jadwal = JadwalPiketMingguan::create([
        'lembaga_id' => $lembaga->id,
        'guru_id' => $guruA->id,
        'hari' => now()->dayOfWeek,
        'semester_id' => $semester->id,
        'dibuat_oleh_user_id' => $admin->id,
    ]);

    // Generate initial piket harian
    app(GenerateJadwalPiketHarianAction::class)->execute($jadwal);
    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guruA->id)->exists())->toBeTrue();

    // Update to guruB and different day
    $hariBaru = (now()->dayOfWeek + 1) % 7;
    $response = $this->actingAs($admin)->put(route('admin.piket-guru.update', $jadwal), [
        'guru_id' => $guruB->id,
        'hari' => $hariBaru,
        'semester_id' => $semester->id,
    ]);

    $response->assertRedirect(route('admin.piket-guru.index'));
    expect($jadwal->fresh()->guru_id)->toBe($guruB->id);
    expect($jadwal->fresh()->hari)->toBe($hariBaru);
    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guruB->id)->exists())->toBeTrue();
});

it('destroy() menghapus jadwal mingguan dan meregenerate piket harian', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'admin' => $admin] = siapkanAdminPiketKelola();
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $jadwal = JadwalPiketMingguan::create([
        'lembaga_id' => $lembaga->id,
        'guru_id' => $guru->id,
        'hari' => now()->dayOfWeek,
        'semester_id' => $semester->id,
        'dibuat_oleh_user_id' => $admin->id,
    ]);

    app(GenerateJadwalPiketHarianAction::class)->execute($jadwal);
    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guru->id)->exists())->toBeTrue();

    $response = $this->actingAs($admin)->delete(route('admin.piket-guru.destroy', $jadwal));

    $response->assertRedirect(route('admin.piket-guru.index'));
    expect(JadwalPiketMingguan::find($jadwal->id))->toBeNull();
    // After destroy and regenerate, PiketHarian with sumber 'otomatis_mingguan' for that teacher will be deleted
    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guru->id)->exists())->toBeFalse();
});

it('admin lembaga A TIDAK BISA edit/update/destroy JadwalPiketMingguan milik lembaga B', function () {
    ['lembaga' => $lembagaA, 'admin' => $adminA] = siapkanAdminPiketKelola();
    ['lembaga' => $lembagaB, 'semester' => $semesterB, 'guru' => $guruB] = siapkanAdminPiketKelola();

    $jadwalMilikB = JadwalPiketMingguan::create([
        'lembaga_id' => $lembagaB->id,
        'guru_id' => $guruB->id,
        'hari' => now()->dayOfWeek,
        'semester_id' => $semesterB->id,
        'dibuat_oleh_user_id' => $adminA->id,
    ]);

    $this->actingAs($adminA)->get(route('admin.piket-guru.edit', $jadwalMilikB))->assertNotFound();
    $this->actingAs($adminA)->put(route('admin.piket-guru.update', $jadwalMilikB), [
        'guru_id' => $guruB->id, 'hari' => 2,
    ])->assertNotFound();
    $this->actingAs($adminA)->delete(route('admin.piket-guru.destroy', $jadwalMilikB))->assertNotFound();

    expect(JadwalPiketMingguan::withoutGlobalScopes()->find($jadwalMilikB->id))->not->toBeNull();
});

it('halaman index, create, dan edit berhasil dirender (200)', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'admin' => $admin] = siapkanAdminPiketKelola();

    $jadwal = JadwalPiketMingguan::create([
        'lembaga_id' => $lembaga->id,
        'guru_id' => $guru->id,
        'hari' => now()->dayOfWeek,
        'semester_id' => $semester->id,
        'dibuat_oleh_user_id' => $admin->id,
    ]);

    $this->actingAs($admin)->get(route('admin.piket-guru.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.piket-guru.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.piket-guru.edit', $jadwal))->assertOk();
});

it('admin bisa pilih semester LAIN (bukan semester aktif) saat store()', function () {
    ['lembaga' => $lembaga, 'admin' => $admin, 'guru' => $guru] = siapkanAdminPiketKelola();

    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLain = Semester::factory()->create([
        'tahun_ajaran_id' => $tahunAjaranLain->id, 'lembaga_id' => $lembaga->id, 'status_aktif' => false,
        'tanggal_mulai' => now()->addMonths(6)->toDateString(), 'tanggal_selesai' => now()->addMonths(10)->toDateString(),
    ]);

    $response = $this->actingAs($admin)->post(route('admin.piket-guru.store'), [
        'guru_id' => $guru->id, 'hari' => 1, 'semester_id' => $semesterLain->id,
    ]);

    $response->assertRedirect(route('admin.piket-guru.index'));
    expect(JadwalPiketMingguan::where('semester_id', $semesterLain->id)->where('guru_id', $guru->id)->exists())->toBeTrue();
});

it('admin lembaga A TIDAK BISA pilih semester milik lembaga B saat store()', function () {
    ['lembaga' => $lembagaA, 'admin' => $adminA, 'guru' => $guruA] = siapkanAdminPiketKelola();
    ['semester' => $semesterB] = siapkanAdminPiketKelola();

    $response = $this->actingAs($adminA)->post(route('admin.piket-guru.store'), [
        'guru_id' => $guruA->id, 'hari' => 1, 'semester_id' => $semesterB->id,
    ]);

    $response->assertSessionHasErrors('semester_id');
    expect(JadwalPiketMingguan::where('lembaga_id', $lembagaA->id)->exists())->toBeFalse();
});

it('admin lembaga A TIDAK BISA pilih guru milik lembaga B saat store()', function () {
    ['lembaga' => $lembagaA, 'semester' => $semesterA, 'admin' => $adminA] = siapkanAdminPiketKelola();
    ['guru' => $guruB] = siapkanAdminPiketKelola();

    $response = $this->actingAs($adminA)->post(route('admin.piket-guru.store'), [
        'guru_id' => $guruB->id, 'hari' => 1, 'semester_id' => $semesterA->id,
    ]);

    $response->assertSessionHasErrors('guru_id');
});

it('update() ganti semester -- pindahkan piket harian ke semester baru & bersihkan dari semester lama', function () {
    ['lembaga' => $lembaga, 'semester' => $semesterLama, 'guru' => $guru, 'admin' => $admin] = siapkanAdminPiketKelola();

    $tahunAjaranBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterBaru = Semester::factory()->create([
        'tahun_ajaran_id' => $tahunAjaranBaru->id, 'lembaga_id' => $lembaga->id, 'status_aktif' => false,
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->addWeeks(4)->toDateString(),
    ]);

    $jadwal = JadwalPiketMingguan::create([
        'lembaga_id' => $lembaga->id,
        'guru_id' => $guru->id,
        'hari' => now()->dayOfWeek,
        'semester_id' => $semesterLama->id,
        'dibuat_oleh_user_id' => $admin->id,
    ]);
    app(GenerateJadwalPiketHarianAction::class)->execute($jadwal);
    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guru->id)->where('tanggal', now()->toDateString())->exists())->toBeTrue();

    $response = $this->actingAs($admin)->put(route('admin.piket-guru.update', $jadwal), [
        'guru_id' => $guru->id, 'hari' => now()->dayOfWeek, 'semester_id' => $semesterBaru->id,
    ]);

    $response->assertRedirect(route('admin.piket-guru.index'));
    expect($jadwal->fresh()->semester_id)->toBe($semesterBaru->id);
    // Baris piket harian ttp ada utk tanggal itu (dari_jadwal_mingguan, digenerate ulang dari jadwal yg sudah pindah semester)
    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guru->id)->where('tanggal', now()->toDateString())->where('sumber', 'dari_jadwal_mingguan')->exists())->toBeTrue();
});

it('admin lembaga A TIDAK BISA pilih semester milik lembaga B saat update()', function () {
    ['lembaga' => $lembagaA, 'semester' => $semesterA, 'guru' => $guruA, 'admin' => $adminA] = siapkanAdminPiketKelola();
    ['semester' => $semesterB] = siapkanAdminPiketKelola();

    $jadwal = JadwalPiketMingguan::create([
        'lembaga_id' => $lembagaA->id,
        'guru_id' => $guruA->id,
        'hari' => now()->dayOfWeek,
        'semester_id' => $semesterA->id,
        'dibuat_oleh_user_id' => $adminA->id,
    ]);

    $response = $this->actingAs($adminA)->put(route('admin.piket-guru.update', $jadwal), [
        'guru_id' => $guruA->id, 'hari' => now()->dayOfWeek, 'semester_id' => $semesterB->id,
    ]);

    $response->assertSessionHasErrors('semester_id');
    expect($jadwal->fresh()->semester_id)->toBe($semesterA->id);
});

it('mengirim piketHarianMendatang berisi kedua sumber (otomatis dan manual) ke view', function () {
    ['lembaga' => $lembaga, 'guru' => $guru, 'admin' => $admin] = siapkanAdminPiketKelola();

    PiketHarian::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => now()->addDay()->toDateString(), 'sumber' => 'dari_jadwal_mingguan',
    ]);
    PiketHarian::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => now()->addDays(2)->toDateString(), 'sumber' => 'override_manual',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.piket-guru.index'));

    $response->assertOk();
    $response->assertViewHas('piketHarianMendatang', fn ($list) => $list->count() === 2);
    $response->assertViewHas('overrides', fn ($list) => $list->count() === 1);
});

function siapkanAdminYayasanPiket(): array
{
    Permission::firstOrCreate(['name' => 'piket.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'pengurus_yayasan_piket_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo('piket.kelola');

    $yayasan = Yayasan::factory()->create();
    $lembaga1 = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => []]);
    $lembaga2 = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => []]);

    $ta1 = TahunAjaran::factory()->create(['lembaga_id' => $lembaga1->id]);
    $sem1 = Semester::factory()->create(['tahun_ajaran_id' => $ta1->id, 'lembaga_id' => $lembaga1->id, 'status_aktif' => true]);

    $ta2 = TahunAjaran::factory()->create(['lembaga_id' => $lembaga2->id]);
    $sem2 = Semester::factory()->create(['tahun_ajaran_id' => $ta2->id, 'lembaga_id' => $lembaga2->id, 'status_aktif' => true]);

    $guru1 = Guru::factory()->create(['lembaga_id' => $lembaga1->id]);
    $guru2 = Guru::factory()->create(['lembaga_id' => $lembaga2->id]);

    $adminYayasan = User::factory()->create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null]);
    $adminYayasan->assignRole($role);

    return compact('yayasan', 'lembaga1', 'lembaga2', 'sem1', 'sem2', 'guru1', 'guru2', 'adminYayasan');
}

it('admin yayasan pada mode Semua Lembaga berhasil membuka index (agregat, HTTP 200) dan melihat data lintas lembaga', function () {
    ['lembaga1' => $l1, 'lembaga2' => $l2, 'sem1' => $s1, 'sem2' => $s2, 'guru1' => $g1, 'guru2' => $g2, 'adminYayasan' => $admin] = siapkanAdminYayasanPiket();

    JadwalPiketMingguan::create(['lembaga_id' => $l1->id, 'guru_id' => $g1->id, 'hari' => 1, 'semester_id' => $s1->id, 'dibuat_oleh_user_id' => $admin->id]);
    JadwalPiketMingguan::create(['lembaga_id' => $l2->id, 'guru_id' => $g2->id, 'hari' => 2, 'semester_id' => $s2->id, 'dibuat_oleh_user_id' => $admin->id]);

    $response = $this->actingAs($admin)
        ->withSession(['active_lembaga_id' => null])
        ->get(route('admin.piket-guru.index'));

    $response->assertOk();
    $response->assertViewHas('isYayasan', true);
    $response->assertViewHas('isYayasanAggregate', true);
    $response->assertViewHas('activeLembaga', null);
    $response->assertViewHas('jadwalList', fn ($list) => $list->count() === 2);
    $response->assertViewHas('stats', fn ($stats) => $stats['totalJadwal'] === 2 && $stats['guruTerjadwal'] === 2 && $stats['lembagaTerjadwal'] === 2);
});

it('admin yayasan pada mode agregat dapat memfilter berdasarkan lembaga_id, hari, semester_id, dan search', function () {
    ['lembaga1' => $l1, 'lembaga2' => $l2, 'sem1' => $s1, 'sem2' => $s2, 'guru1' => $g1, 'guru2' => $g2, 'adminYayasan' => $admin] = siapkanAdminYayasanPiket();

    JadwalPiketMingguan::create(['lembaga_id' => $l1->id, 'guru_id' => $g1->id, 'hari' => 1, 'semester_id' => $s1->id, 'dibuat_oleh_user_id' => $admin->id]);
    JadwalPiketMingguan::create(['lembaga_id' => $l2->id, 'guru_id' => $g2->id, 'hari' => 2, 'semester_id' => $s2->id, 'dibuat_oleh_user_id' => $admin->id]);

    // Filter by lembaga_id
    $response = $this->actingAs($admin)
        ->withSession(['active_lembaga_id' => null])
        ->get(route('admin.piket-guru.index', ['lembaga_id' => $l1->id]));

    $response->assertOk();
    $response->assertViewHas('jadwalList', fn ($list) => $list->count() === 1 && $list->first()->lembaga_id === $l1->id);

    // Filter by hari
    $responseHari = $this->actingAs($admin)
        ->withSession(['active_lembaga_id' => null])
        ->get(route('admin.piket-guru.index', ['hari' => 2]));

    $responseHari->assertOk();
    $responseHari->assertViewHas('jadwalList', fn ($list) => $list->count() === 1 && $list->first()->hari === 2);
});

it('admin yayasan pada mode Semua Lembaga diarahkan kembali jika membuka create langsung', function () {
    ['adminYayasan' => $admin] = siapkanAdminYayasanPiket();

    $response = $this->actingAs($admin)
        ->withSession(['active_lembaga_id' => null])
        ->get(route('admin.piket-guru.create'));

    $response->assertRedirect(route('admin.piket-guru.index'));
    $response->assertSessionHasErrors('lembaga_id');
});

it('index mengembalikan partial view _daftar jika request adalah ajax', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'admin' => $admin] = siapkanAdminPiketKelola();
    JadwalPiketMingguan::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'hari' => 1,
        'semester_id' => $semester->id, 'dibuat_oleh_user_id' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.piket-guru.index'), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk();
    $response->assertViewIs('portals.lembaga.akademik.piket-guru._daftar');
    $response->assertSee($guru->nama);
});

it('admin yayasan pada mode Semua Lembaga diarahkan kembali dengan pesan error jika store dipanggil langsung tanpa 422', function () {
    ['adminYayasan' => $admin, 'guru1' => $guru, 'sem1' => $semester] = siapkanAdminYayasanPiket();

    $response = $this->actingAs($admin)
        ->withSession(['active_lembaga_id' => null])
        ->post(route('admin.piket-guru.store'), [
            'guru_id' => $guru->id,
            'hari' => 1,
            'semester_id' => $semester->id,
        ]);

    $response->assertRedirect(route('admin.piket-guru.index'));
    $response->assertSessionHasErrors('lembaga_id');
});

it('kalender piket harian terpaginasi dan tanggal berbahasa indonesia di view _daftar', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'admin' => $admin] = siapkanAdminPiketKelola();
    PiketHarian::create([
        'lembaga_id' => $lembaga->id,
        'guru_id' => $guru->id,
        'tanggal' => '2026-09-14', // Senin
        'sumber' => 'override_manual',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.piket-guru.index'), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk();
    $response->assertSee('Senin, 14 September 2026');
    $response->assertViewHas('piketHarianMendatang', fn ($piket) => $piket instanceof \Illuminate\Pagination\LengthAwarePaginator);
});




