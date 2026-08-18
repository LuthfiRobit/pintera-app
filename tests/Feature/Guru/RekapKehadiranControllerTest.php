<?php

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanWaliKelasDenganSiswa(): array
{
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_wali', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $semester = Semester::factory()->create([
        'tahun_ajaran_id' => $tahunAjaran->id,
        'status_aktif' => true,
        'tanggal_mulai' => now()->subMonth()->toDateString(),
        'tanggal_selesai' => now()->addMonth()->toDateString(),
    ]);

    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruUser->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $guruUser->id]);

    $kelas = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'wali_kelas_guru_id' => $guru->id,
    ]);
    $siswa = Siswa::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => $kelas->id,
        'status' => 'aktif',
        'nis' => '123456',
    ]);
    $sesi = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => now()->toDateString()]);
    Presensi::create(['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id, 'status' => 'hadir']);

    return compact('guruUser', 'guru', 'kelas', 'siswa', 'lembaga', 'yayasan', 'tahunAjaran', 'semester');
}

it('denies access without presensi.isi permission', function () {
    $this->actingAs(User::factory()->create())->get(route('guru.jurnal-kbm.rekap'))->assertForbidden();
});

it('shows attendance recap with NIS and filter options for the kelas the guru is wali kelas of', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'siswa' => $siswa, 'tahunAjaran' => $tahunAjaran, 'semester' => $semester] = siapkanWaliKelasDenganSiswa();

    $response = $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.rekap', [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'semester_id' => $semester->id,
        'kelas_id' => $kelas->id,
    ]));

    $response->assertOk();
    $response->assertViewHas('tahunAjaranList');
    $response->assertViewHas('semesterList');
    $response->assertViewHas('kelasList');
    $response->assertSee('123456'); // NIS siswa
    $response->assertSee($siswa->nama_lengkap);
    $response->assertViewHas('rekap', function ($rekap) use ($siswa) {
        $baris = $rekap->firstWhere('siswa_id', $siswa->id);

        return $baris !== null && $baris['hadir'] === 1 && $baris['nis'] === '123456';
    });
});

it('does not show a kelas the guru is not wali kelas of, even from their own lembaga', function () {
    ['guruUser' => $guruUser, 'lembaga' => $lembaga] = siapkanWaliKelasDenganSiswa();
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasBukanWali = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $response = $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.rekap', ['kelas_id' => $kelasBukanWali->id]));

    $response->assertOk();
    $response->assertViewHas('kelas', fn ($kelas) => $kelas === null);
});

it('does not show a kelas from another lembaga even if the guru id happens to match a wali_kelas_guru_id there', function () {
    ['guruUser' => $guruUser, 'guru' => $guru, 'yayasan' => $yayasan] = siapkanWaliKelasDenganSiswa();

    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $kelasLain = Kelas::withoutGlobalScopes()->create([
        'lembaga_id' => $lembagaLain->id,
        'tahun_ajaran_id' => $tahunAjaranLain->id,
        'nama' => 'Kelas Lembaga Lain',
        'wali_kelas_guru_id' => $guru->id,
    ]);

    $response = $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.rekap', ['kelas_id' => $kelasLain->id]));

    $response->assertOk();
    $response->assertViewHas('kelas', fn ($kelas) => $kelas === null);
});
