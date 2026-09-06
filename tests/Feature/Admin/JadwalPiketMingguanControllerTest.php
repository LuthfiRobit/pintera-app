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
