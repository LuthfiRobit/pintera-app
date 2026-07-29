<?php

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\PolaJam;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsJadwalManager(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['jadwal-pelajaran.kelola']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access without jadwal-pelajaran.kelola permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.jadwal-pelajaran.index'))->assertForbidden();
});

it('creates a jadwal pelajaran entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsJadwalManager($lembaga);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => [$jam->id],
        'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ])->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->where('jam_pelajaran_id', $jam->id)->exists())->toBeTrue();
});

it('creates jadwal pelajaran entries for multiple selected slots in one submit', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamSatu = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 1, 'is_pelajaran' => true, 'label' => 'Jam ke-1']);
    $jamDua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 2, 'is_pelajaran' => true, 'label' => 'Jam ke-2']);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsJadwalManager($lembaga);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => [$jamSatu->id, $jamDua->id],
        'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ])->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->where('jam_pelajaran_id', $jamSatu->id)->exists())->toBeTrue();
    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->where('jam_pelajaran_id', $jamDua->id)->exists())->toBeTrue();
    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->count())->toBe(2);
});

it('creates the non-colliding slots and reports the skipped one when one of several selected slots already has a jadwal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamSatu = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 1, 'is_pelajaran' => true, 'hari' => 'senin', 'label' => 'Jam ke-1']);
    $jamDua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 2, 'is_pelajaran' => true, 'hari' => 'senin', 'label' => 'Jam ke-2']);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamSatu->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => [$jamSatu->id, $jamDua->id],
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ]);

    $response->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));
    $response->assertSessionHas('status', fn ($status) => str_contains($status, 'Senin Jam ke-2') && str_contains($status, 'Dilewati'));

    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->where('jam_pelajaran_id', $jamDua->id)->exists())->toBeTrue();
    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->count())->toBe(2);
});

it('creates the non-colliding slots and reports the skipped one when one of several selected slots has a guru conflict', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasSatu = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $kelasDua = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamSatu = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 1, 'is_pelajaran' => true, 'hari' => 'senin', 'label' => 'Jam ke-1']);
    $jamDua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 2, 'is_pelajaran' => true, 'hari' => 'senin', 'label' => 'Jam ke-2']);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelasSatu->id, 'jam_pelajaran_id' => $jamSatu->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasDua->id,
        'jam_pelajaran_id' => [$jamSatu->id, $jamDua->id],
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ]);

    $response->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelasDua->id, 'semester_id' => $semester->id]));
    $response->assertSessionHas('status', fn ($status) => str_contains($status, 'Senin Jam ke-2') && str_contains($status, 'Dilewati'));

    expect(JadwalPelajaran::where('kelas_id', $kelasDua->id)->where('jam_pelajaran_id', $jamDua->id)->exists())->toBeTrue();
    expect(JadwalPelajaran::where('kelas_id', $kelasDua->id)->count())->toBe(1);
});

it('rejects the whole batch when every selected slot already has a jadwal for this kelas and semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamSatu = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 1, 'is_pelajaran' => true, 'hari' => 'senin']);
    $jamDua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 2, 'is_pelajaran' => true, 'hari' => 'senin']);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamSatu->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamDua->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    $manager = actingAsJadwalManager($lembaga);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => [$jamSatu->id, $jamDua->id],
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ])->assertSessionHasErrors('jam_pelajaran_id');

    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->count())->toBe(2);
});

it('rejects the entire batch when one of several selected jam_pelajaran_id belongs to a different pola jam', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id, 'pola_jam_id' => $polaA->id]);
    $jamValid = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true]);
    $jamLain = JamPelajaran::factory()->create(['pola_jam_id' => $polaLain->id, 'is_pelajaran' => true]);
    $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    $manager = actingAsJadwalManager($lembagaA);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => [$jamValid->id, $jamLain->id],
        'guru_id' => $guruA->id,
        'semester_id' => $semesterA->id,
    ])->assertNotFound();

    expect(JadwalPelajaran::where('kelas_id', $kelasA->id)->exists())->toBeFalse();
});

it('only offers is_pelajaran slots when creating a jadwal entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamBelajar = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 1, 'is_pelajaran' => true, 'label' => 'Jam ke-1']);
    $jamIstirahat = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 2, 'is_pelajaran' => false, 'label' => 'Istirahat']);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertViewHas('jamPelajaranPerHari', function ($groups) use ($jamBelajar, $jamIstirahat) {
        $ids = $groups->flatMap(fn ($grup) => $grup['items']->pluck('id'));

        return $ids->contains($jamBelajar->id) && ! $ids->contains($jamIstirahat->id);
    });
});

it('groups the jam pelajaran options by hari in hari-aktif order', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0, 6]]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'rabu', 'urutan' => 1, 'is_pelajaran' => true]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'senin', 'urutan' => 1, 'is_pelajaran' => true]);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertViewHas('jamPelajaranPerHari', function ($groups) {
        return $groups->pluck('hari')->map(fn ($h) => $h->value)->all() === ['senin', 'rabu'];
    });
});

it('rejects a kelas_id belonging to another lembaga', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $jamA = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true]);
    $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    $manager = actingAsJadwalManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $tahunAjaranB->id]);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasB->id,
        'jam_pelajaran_id' => [$jamA->id],
        'guru_id' => $guruA->id,
        'semester_id' => $semesterA->id,
    ])->assertNotFound();

    expect(JadwalPelajaran::where('kelas_id', $kelasB->id)->exists())->toBeFalse();
});

it('rejects a guru_id belonging to another lembaga even when kelas_id is own', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id, 'pola_jam_id' => $polaA->id]);
    $jamA = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true]);
    $manager = actingAsJadwalManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $guruB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => [$jamA->id],
        'guru_id' => $guruB->id,
        'semester_id' => $semesterA->id,
    ])->assertNotFound();

    expect(JadwalPelajaran::where('kelas_id', $kelasA->id)->exists())->toBeFalse();
});

it('only lists semester and kelas belonging to the selected tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $taLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => false]);
    $taBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $taLama->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $taBaru->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taBaru->id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', ['tahun_ajaran_id' => $taBaru->id]));

    $response->assertViewHas('semesterList', fn ($list) => $list->contains('id', $semesterBaru->id) && ! $list->contains('id', $semesterLama->id));
    $response->assertViewHas('kelasList', fn ($list) => $list->contains('id', $kelasBaru->id) && ! $list->contains('id', $kelasLama->id));
});

it('defaults to the active tahun ajaran when none is selected', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $taAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $kelasAktif = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taAktif->id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index'));

    $response->assertViewHas('tahunAjaranId', $taAktif->id);
    $response->assertViewHas('kelasList', fn ($list) => $list->contains('id', $kelasAktif->id));
});

it('rejects a jadwal that mixes entities from different lembaga even for a yayasan-scoped user', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $jamA = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true]);
    $kelasA->update(['pola_jam_id' => $polaA->id]);
    $guruB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]); // different lembaga than everything else

    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_jadwal_mix_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['jadwal-pelajaran.kelola']);
    $manager = User::factory()->create(['lembaga_id' => null]);
    $manager->assignRole($role);
    // No active_lembaga_id in session — this yayasan-scoped user can see both lembaga A and B.

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => [$jamA->id],
        'guru_id' => $guruB->id, // mismatched lembaga
        'semester_id' => $semesterA->id,
    ])->assertSessionHasErrors();

    expect(JadwalPelajaran::where('kelas_id', $kelasA->id)->exists())->toBeFalse();
});

it('rejects a jam_pelajaran_id belonging to a different pola jam than the kelas uses', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id, 'pola_jam_id' => $polaA->id]);
    $jamLain = JamPelajaran::factory()->create(['pola_jam_id' => $polaLain->id, 'is_pelajaran' => true]);
    $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    $manager = actingAsJadwalManager($lembagaA);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => [$jamLain->id],
        'guru_id' => $guruA->id,
        'semester_id' => $semesterA->id,
    ])->assertNotFound();

    expect(JadwalPelajaran::where('kelas_id', $kelasA->id)->exists())->toBeFalse();
});

it('rejects a jam_pelajaran_id that is not an is_pelajaran slot even if it belongs to the right pola jam', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamIstirahat = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => false, 'label' => 'Istirahat']);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsJadwalManager($lembaga);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => [$jamIstirahat->id],
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ])->assertNotFound();

    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->exists())->toBeFalse();
});

it('shows a friendly error instead of a raw SQL error on a duplicate kelas/jam/semester entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => [$jam->id], 'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ])->assertSessionHasErrors()->assertStatus(302); // never a 500

    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->count())->toBe(1);
});

it('rejects double-booking a guru into two classes at the same jam and semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $kelasSatu = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $kelasDua = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelasSatu->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasDua->id, 'jam_pelajaran_id' => [$jam->id], 'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ])->assertSessionHasErrors('jam_pelajaran_id');

    expect(JadwalPelajaran::where('kelas_id', $kelasDua->id)->exists())->toBeFalse();
});

it('returns only the schedule fragment for an AJAX request, not the full page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk();
    $response->assertSee('Daftar Jadwal Pelajaran');
    $response->assertDontSee('Filter Jadwal Pelajaran');
});

it('shows an explanatory message instead of a silent empty state when the filter is incomplete', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);

    $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index'))
        ->assertSee('Lengkapi Filter Terlebih Dahulu');
});

it('only computes hari aktif from the selected kelas\' lembaga, excluding weekly libur days', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0, 6]]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]));

    $response->assertViewHas('hariAktif', function ($hariAktif) {
        $values = collect($hariAktif)->map(fn ($h) => $h->value);

        return ! $values->contains('sabtu') && ! $values->contains('minggu') && $values->contains('senin');
    });
});

it('returns kelas and semester options scoped to the given tahun ajaran via the opsi endpoint', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil']);

    $response = $this->actingAs($manager)->getJson(route('admin.jadwal-pelajaran.opsi', ['tahun_ajaran_id' => $tahunAjaran->id]));

    $response->assertOk();
    $response->assertJsonFragment(['id' => $kelas->id, 'nama' => '6A']);
    $response->assertJsonFragment(['id' => $semester->id, 'nama' => 'Ganjil']);
});

it('rejects a tahun_ajaran_id belonging to another lembaga on the opsi endpoint', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsJadwalManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);

    $this->actingAs($manager)->getJson(route('admin.jadwal-pelajaran.opsi', ['tahun_ajaran_id' => $tahunAjaranB->id]))
        ->assertNotFound();
});

it('renders the filter fields in tahun ajaran, semester, kelas order with no submit button', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index'));
    $html = $response->getContent();

    $posisiTahunAjaran = strpos($html, 'name="tahun_ajaran_id"') ?: strpos($html, 'x-ref="tahunAjaranSelect"');
    $posisiSemester = strpos($html, 'x-ref="semesterSelect"');
    $posisiKelas = strpos($html, 'x-ref="kelasSelect"');

    expect($posisiTahunAjaran)->not->toBeFalse();
    expect($posisiSemester)->not->toBeFalse();
    expect($posisiKelas)->not->toBeFalse();
    expect($posisiTahunAjaran)->toBeLessThan($posisiSemester);
    expect($posisiSemester)->toBeLessThan($posisiKelas);

    $response->assertDontSee('Tampilkan');
});

it('wires the filter card with jadwalPelajaranFilter and the correct initial values', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]));

    $response->assertSee('jadwalPelajaranFilter(', false);
    $response->assertSee((string) $tahunAjaran->id, false);
    $response->assertSee((string) $kelas->id, false);
    $response->assertSee((string) $semester->id, false);
});

it('renders the jam pelajaran select as a multi-select grouped by hari', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'senin', 'is_pelajaran' => true, 'label' => 'Jam ke-1']);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertSee('name="jam_pelajaran_id[]"', false);
    $response->assertSee('multiple', false);
    $response->assertSee('<optgroup label="Senin"', false);
});

it('displays tahun ajaran and semester context on the create page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027']);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil']);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertSee('2026/2027');
    $response->assertSee('Ganjil');
});

it('shows a warning banner instead of the form when the kelas has no pola jam', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => null]);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertSee('Kelas ini belum punya Pola Jam');
    $response->assertDontSee('name="jam_pelajaran_id[]"', false);
});

it('renders a toast block for validation errors on the create page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->from(route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]))
        ->post(route('admin.jadwal-pelajaran.store'), [
            'kelas_id' => $kelas->id, 'jam_pelajaran_id' => [$jam->id], 'guru_id' => $guru->id, 'semester_id' => $semester->id,
        ])
        ->assertRedirect(route('admin.jadwal-pelajaran.create', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $followUp = $this->actingAs($manager)->get($response->headers->get('Location'));
    $followUp->assertSee('$store.toast.push', false);
});

it('shows edit and hapus actions for each jadwal entry in the daftar', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]));

    $response->assertSee(route('admin.jadwal-pelajaran.edit', $jadwal), false);
    $response->assertSee('Hapus');
    $response->assertSee('confirmDialog', false);
    $response->assertSee(route('admin.jadwal-pelajaran.destroy', $jadwal), false);
});

it('updates a jadwal pelajaran entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamLama = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 1, 'is_pelajaran' => true]);
    $jamBaru = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 2, 'is_pelajaran' => true]);
    $guruLama = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruBaru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelBaru = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamLama->id, 'guru_id' => $guruLama->id, 'semester_id' => $semester->id, 'mata_pelajaran_id' => null]);

    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'jam_pelajaran_id' => $jamBaru->id,
        'mata_pelajaran_id' => $mapelBaru->id,
        'guru_id' => $guruBaru->id,
    ])->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $jadwal->refresh();
    expect($jadwal->jam_pelajaran_id)->toBe($jamBaru->id);
    expect($jadwal->guru_id)->toBe($guruBaru->id);
    expect($jadwal->mata_pelajaran_id)->toBe($mapelBaru->id);
});

it('does not allow changing kelas or semester through the update payload', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'kelas_id' => $kelasLain->id,
        'semester_id' => $semesterLain->id,
        'jam_pelajaran_id' => $jam->id,
        'guru_id' => $guru->id,
    ])->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $jadwal->refresh();
    expect($jadwal->kelas_id)->toBe($kelas->id);
    expect($jadwal->semester_id)->toBe($semester->id);
});

it('rejects updating guru_id to a guru from another lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembagaA);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    $guruLain = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'jam_pelajaran_id' => $jam->id,
        'guru_id' => $guruLain->id,
    ])->assertSessionHasErrors('guru_id');

    expect($jadwal->fresh()->guru_id)->toBe($guru->id);
});

it('rejects updating jam_pelajaran_id to a slot from a different pola jam', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $jamLain = JamPelajaran::factory()->create(['pola_jam_id' => $polaLain->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'jam_pelajaran_id' => $jamLain->id,
        'guru_id' => $guru->id,
    ])->assertNotFound();

    expect($jadwal->fresh()->jam_pelajaran_id)->toBe($jam->id);
});

it('rejects updating jam_pelajaran_id to a slot that is not is_pelajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $jamIstirahat = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => false]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'jam_pelajaran_id' => $jamIstirahat->id,
        'guru_id' => $guru->id,
    ])->assertNotFound();

    expect($jadwal->fresh()->jam_pelajaran_id)->toBe($jam->id);
});

it('allows saving an update that keeps the same jam_pelajaran_id without a false duplicate conflict', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guruLama = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruBaru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guruLama->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'jam_pelajaran_id' => $jam->id,
        'guru_id' => $guruBaru->id,
    ])->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    expect($jadwal->fresh()->guru_id)->toBe($guruBaru->id);
});

it('rejects updating to a jam_pelajaran_id that collides with another existing jadwal in the same kelas and semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamSatu = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 1, 'is_pelajaran' => true]);
    $jamDua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'urutan' => 2, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwalSatu = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamSatu->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    $jadwalDua = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamDua->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwalDua), [
        'jam_pelajaran_id' => $jamSatu->id,
        'guru_id' => $guru->id,
    ])->assertSessionHasErrors('jam_pelajaran_id');

    expect($jadwalDua->fresh()->jam_pelajaran_id)->toBe($jamDua->id);
});

it('rejects updating guru_id to create a double booking with another existing jadwal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $kelasSatu = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $kelasDua = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $guruSatu = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruDua = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create(['kelas_id' => $kelasSatu->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guruSatu->id, 'semester_id' => $semester->id]);
    $jadwalDua = JadwalPelajaran::factory()->create(['kelas_id' => $kelasDua->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guruDua->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwalDua), [
        'jam_pelajaran_id' => $jam->id,
        'guru_id' => $guruSatu->id,
    ])->assertSessionHasErrors('guru_id');

    expect($jadwalDua->fresh()->guru_id)->toBe($guruDua->id);
});

it('rejects editing or updating a jadwal entry belonging to another lembaga', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsJadwalManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $polaB = PolaJam::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $tahunAjaranB->id, 'pola_jam_id' => $polaB->id]);
    $jamB = JamPelajaran::factory()->create(['pola_jam_id' => $polaB->id, 'is_pelajaran' => true]);
    $guruB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);
    $jadwalB = JadwalPelajaran::factory()->create(['kelas_id' => $kelasB->id, 'jam_pelajaran_id' => $jamB->id, 'guru_id' => $guruB->id, 'semester_id' => $semesterB->id]);

    $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.edit', $jadwalB))->assertNotFound();
    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwalB), [
        'jam_pelajaran_id' => $jamB->id,
        'guru_id' => $guruB->id,
    ])->assertNotFound();
});

it('deletes a jadwal pelajaran entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->delete(route('admin.jadwal-pelajaran.destroy', $jadwal))
        ->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]))
        ->assertSessionHas('status');

    expect(JadwalPelajaran::find($jadwal->id))->toBeNull();
});

it('rejects deleting a jadwal entry belonging to another lembaga', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsJadwalManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $polaB = PolaJam::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $tahunAjaranB->id, 'pola_jam_id' => $polaB->id]);
    $jamB = JamPelajaran::factory()->create(['pola_jam_id' => $polaB->id, 'is_pelajaran' => true]);
    $guruB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);
    $jadwalB = JadwalPelajaran::factory()->create(['kelas_id' => $kelasB->id, 'jam_pelajaran_id' => $jamB->id, 'guru_id' => $guruB->id, 'semester_id' => $semesterB->id]);

    $this->actingAs($manager)->delete(route('admin.jadwal-pelajaran.destroy', $jadwalB))->assertNotFound();

    expect(JadwalPelajaran::find($jadwalB->id))->not->toBeNull();
});

it('denies access to edit, update, and destroy without jadwal-pelajaran.kelola permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    $outsider = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($outsider)->get(route('admin.jadwal-pelajaran.edit', $jadwal))->assertForbidden();
    $this->actingAs($outsider)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id,
    ])->assertForbidden();
    $this->actingAs($outsider)->delete(route('admin.jadwal-pelajaran.destroy', $jadwal))->assertForbidden();
});
