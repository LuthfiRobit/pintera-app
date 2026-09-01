<?php

use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\SiswaKeringanan;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('lists siswa matching a draft sasaran with their current keringanan assignments', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $siswaAktif = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'aktif']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'lulus']); // tidak match kriteria
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id]);
    $assignment = SiswaKeringanan::create([
        'siswa_id' => $siswaAktif->id,
        'kategori_keringanan_id' => $kategori->id,
        'berlaku_dari' => now()->subDay()->toDateString(),
    ]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.preview-siswa-keringanan'), [
        'sasaran' => [['kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]]],
    ]);

    $response->assertOk();
    $response->assertJsonCount(1, 'siswa');
    $response->assertJsonPath('siswa.0.id', $siswaAktif->id);
    $response->assertJsonPath('siswa.0.assignments.'.$kategori->id, $assignment->id);
});

it('excludes an expired keringanan assignment from the assignments map', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kategori = KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id]);
    SiswaKeringanan::create([
        'siswa_id' => $siswa->id,
        'kategori_keringanan_id' => $kategori->id,
        'berlaku_dari' => now()->subMonths(2)->toDateString(),
        'berlaku_sampai' => now()->subMonth()->toDateString(),
    ]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.preview-siswa-keringanan'), [
        'sasaran' => [],
    ]);

    $response->assertOk();
    $response->assertJsonPath('siswa.0.assignments', []);
});

it('includes the kelas name for each siswa row', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '7A']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.preview-siswa-keringanan'), [
        'sasaran' => [],
    ]);

    $response->assertOk();
    $response->assertJsonPath('siswa.0.kelas', '7A');
});

it('filters the siswa list by search term (nama/nis)', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $target = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Budi Santoso']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Citra Dewi']);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.preview-siswa-keringanan'), [
        'sasaran' => [], 'search' => 'Budi',
    ]);

    $response->assertOk();
    $response->assertJsonCount(1, 'siswa');
    $response->assertJsonPath('siswa.0.id', $target->id);
});

it('filters the siswa list by kelas_id', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $target = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasA->id]);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasB->id]);

    $response = $this->actingAs($admin)->postJson(route('admin.jenis-tagihan.preview-siswa-keringanan'), [
        'sasaran' => [], 'kelas_id' => $kelasA->id,
    ]);

    $response->assertOk();
    $response->assertJsonCount(1, 'siswa');
    $response->assertJsonPath('siswa.0.id', $target->id);
});
