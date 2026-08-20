<?php

use App\Enums\StatusSiswa;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsKenaikanKelasManager(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'kenaikan-kelas.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kenaikan-kelas.kelola']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access without kenaikan-kelas.kelola permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.kenaikan-kelas.index'))->assertForbidden();
});

it('lists kelas belonging to the selected source tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2025/2026']);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '5A']);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', ['tahun_ajaran_id' => $tahunLalu->id]));

    $response->assertOk();
    $response->assertViewHas('kelasLamaList', fn ($list) => $list->contains('id', $kelasLama->id));
});

it('only offers kelas and semester belonging to the selected target tahun ajaran, empty otherwise', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2025/2026']);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027']);
    $tahunLain = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2027/2028']);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '5A']);
    $kelasTujuan = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => '6A']);
    $kelasTahunLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLain->id, 'nama' => '6A-Lain']);
    $semesterTujuan = Semester::factory()->create(['tahun_ajaran_id' => $tahunBaru->id]);
    $semesterTahunLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunLain->id]);
    $manager = actingAsKenaikanKelasManager($lembaga);

    // No target tahun ajaran selected yet: both lists must be empty, not "all kelas/semester".
    $withoutTarget = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', ['tahun_ajaran_id' => $tahunLalu->id]));
    $withoutTarget->assertOk();
    $withoutTarget->assertViewHas('kelasTujuanList', fn ($list) => $list->isEmpty());
    $withoutTarget->assertViewHas('semesterList', fn ($list) => $list->isEmpty());

    // Target selected: only that tahun ajaran's kelas/semester appear.
    $withTarget = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', [
        'tahun_ajaran_id' => $tahunLalu->id,
        'tahun_ajaran_tujuan_id' => $tahunBaru->id,
    ]));
    $withTarget->assertOk();
    $withTarget->assertViewHas('kelasTujuanList', fn ($list) => $list->contains('id', $kelasTujuan->id) && ! $list->contains('id', $kelasTahunLain->id));
    $withTarget->assertViewHas('semesterList', fn ($list) => $list->contains('id', $semesterTujuan->id) && ! $list->contains('id', $semesterTahunLain->id));
});

it('moves siswa to the target kelas when mapped to promotion', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '5A']);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => '6A']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLama->id, 'status' => StatusSiswa::Aktif->value]);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasBaru->id],
        ],
    ])->assertRedirect(route('admin.kelas.index'));

    $siswa->refresh();
    expect($siswa->kelas_id)->toBe($kelasBaru->id);
    expect($siswa->status)->toBe(StatusSiswa::Aktif);
});

it('graduates siswa when mapped to lulus, clearing kelas_id', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '6A']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLama->id, 'status' => StatusSiswa::Aktif->value]);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasLama->id => ['tindakan' => 'lulus'],
        ],
    ])->assertRedirect(route('admin.kelas.index'));

    $siswa->refresh();
    expect($siswa->status)->toBe(StatusSiswa::Lulus);
    expect($siswa->kelas_id)->toBeNull();
});

it('optionally copies jadwal pelajaran structure to the target kelas and semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLalu = Semester::factory()->create(['tahun_ajaran_id' => $tahunLalu->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $tahunBaru->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'pola_jam_id' => $pola->id, 'nama' => '5A']);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'pola_jam_id' => $pola->id, 'nama' => '6A']);
    JadwalPelajaran::create([
        'kelas_id' => $kelasLama->id, 'jam_pelajaran_id' => $jam->id, 'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id, 'semester_id' => $semesterLalu->id,
    ]);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasLama->id => [
                'tindakan' => 'naik',
                'kelas_baru_id' => $kelasBaru->id,
                'salin_jadwal' => '1',
                'semester_tujuan_id' => $semesterBaru->id,
            ],
        ],
    ])->assertRedirect(route('admin.kelas.index'));

    $jadwalBaru = JadwalPelajaran::where('kelas_id', $kelasBaru->id)->where('semester_id', $semesterBaru->id)->first();
    expect($jadwalBaru)->not->toBeNull();
    expect($jadwalBaru->jam_pelajaran_id)->toBe($jam->id);
    expect($jadwalBaru->mata_pelajaran_id)->toBe($mapel->id);
    expect($jadwalBaru->guru_id)->toBe($guru->id);
});

it('rejects promoting siswa into a kelas from the same tahun ajaran as the source kelas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '5A']);
    $kelasSamaTahun = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '5B']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLama->id, 'status' => StatusSiswa::Aktif->value]);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasSamaTahun->id],
        ],
    ])->assertRedirect()->assertSessionHasErrors('mapping');

    $siswa->refresh();
    expect($siswa->kelas_id)->toBe($kelasLama->id);
});

it('rejects promoting siswa into a kelas belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembagaSaya->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '5A']);
    $tahunLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunLain->id, 'nama' => '6A']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembagaSaya->id, 'kelas_id' => $kelasLama->id, 'status' => StatusSiswa::Aktif->value]);
    $manager = actingAsKenaikanKelasManager($lembagaSaya);

    $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasLain->id],
        ],
    ])->assertNotFound();

    $siswa->refresh();
    expect($siswa->kelas_id)->toBe($kelasLama->id);
});

it('rejects copying jadwal into a semester belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembagaSaya->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '5A']);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembagaSaya->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => '6A']);
    $tahunLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunLain->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembagaSaya->id, 'kelas_id' => $kelasLama->id, 'status' => StatusSiswa::Aktif->value]);
    $manager = actingAsKenaikanKelasManager($lembagaSaya);

    $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasLama->id => [
                'tindakan' => 'naik',
                'kelas_baru_id' => $kelasBaru->id,
                'salin_jadwal' => '1',
                'semester_tujuan_id' => $semesterLain->id,
            ],
        ],
    ])->assertNotFound();

    $siswa->refresh();
    expect($siswa->kelas_id)->toBe($kelasLama->id);
    expect(JadwalPelajaran::where('kelas_id', $kelasBaru->id)->exists())->toBeFalse();
});
