<?php

use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsGuruKomponenPenilaian(Guru $guru): User
{
    Permission::firstOrCreate(['name' => 'komponen-penilaian.kelola-sendiri', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['komponen-penilaian.kelola-sendiri']);

    $user = User::factory()->create([
        'lembaga_id' => $guru->lembaga_id,
        'email' => $guru->email ?: 'guru'.random_int(1000, 9999).'@test.com',
    ]);
    $guru->update(['user_id' => $user->id]);
    $user->assignRole($role);

    return $user;
}

function mengajar(Lembaga $lembaga, Guru $guru, MataPelajaran $mapel, Semester $semester, Kelas $kelas): JadwalPelajaran
{
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);

    return JadwalPelajaran::create([
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => $jam->id,
        'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ]);
}

it('denies access without komponen-penilaian.kelola-sendiri permission', function () {
    $this->actingAs(User::factory()->create())->get(route('guru.komponen-penilaian.index'))->assertForbidden();
});

it('only lists komponen penilaian for mata pelajaran the guru actually teaches', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapelDiajar = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Matematika']);
    $mapelLain = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'IPA']);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    mengajar($lembaga, $guru, $mapelDiajar, $semester, $kelas);
    $user = actingAsGuruKomponenPenilaian($guru);

    $tpDiajar = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelDiajar->id, 'semester_id' => $semester->id]);
    $tpLain = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelLain->id, 'semester_id' => $semester->id]);

    $response = $this->actingAs($user)->get(route('guru.komponen-penilaian.index'));

    $response->assertOk();
    $response->assertViewHas('komponenList', fn ($list) => $list->contains('id', $tpDiajar->id) && ! $list->contains('id', $tpLain->id));
});

it('allows guru to create a komponen penilaian for a mata pelajaran and semester they teach', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    mengajar($lembaga, $guru, $mapel, $semester, $kelas);
    $user = actingAsGuruKomponenPenilaian($guru);

    $this->actingAs($user)->post(route('guru.komponen-penilaian.store'), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'kode' => 'TP 1.1',
        'deskripsi' => 'Peserta didik dapat menjumlahkan pecahan.',
        'bobot' => 100,
    ])->assertRedirect(route('guru.komponen-penilaian.index'));

    expect(KomponenPenilaian::where('subjek_type', 'mata_pelajaran')->where('subjek_id', $mapel->id)->where('semester_id', $semester->id)->where('kode', 'TP 1.1')->exists())->toBeTrue();
});

it('rejects creating a komponen penilaian for a mata pelajaran the guru does not teach', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapelTidakDiajar = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = actingAsGuruKomponenPenilaian($guru);

    $this->actingAs($user)->post(route('guru.komponen-penilaian.store'), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapelTidakDiajar->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Coba TP untuk mapel yang bukan miliknya.',
        'bobot' => 100,
    ])->assertForbidden();

    expect(KomponenPenilaian::where('subjek_type', 'mata_pelajaran')->where('subjek_id', $mapelTidakDiajar->id)->exists())->toBeFalse();
});

it('allows guru to edit a komponen penilaian for a mata pelajaran they teach, even one created by someone else', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    mengajar($lembaga, $guru, $mapel, $semester, $kelas);
    $user = actingAsGuruKomponenPenilaian($guru);

    $tp = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'deskripsi' => 'Deskripsi lama']);

    $this->actingAs($user)->get(route('guru.komponen-penilaian.edit', $tp))->assertOk();

    $this->actingAs($user)->put(route('guru.komponen-penilaian.update', $tp), [
        'deskripsi' => 'Deskripsi baru yang diperbarui guru.',
    ])->assertRedirect(route('guru.komponen-penilaian.index'));

    expect($tp->fresh()->deskripsi)->toBe('Deskripsi baru yang diperbarui guru.');
});

it('rejects editing or deleting a komponen penilaian for a mata pelajaran the guru does not teach', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapelTidakDiajar = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = actingAsGuruKomponenPenilaian($guru);

    $tp = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelTidakDiajar->id, 'semester_id' => $semester->id]);

    $this->actingAs($user)->get(route('guru.komponen-penilaian.edit', $tp))->assertForbidden();
    $this->actingAs($user)->put(route('guru.komponen-penilaian.update', $tp), ['deskripsi' => 'Coba ubah'])->assertForbidden();
    $this->actingAs($user)->delete(route('guru.komponen-penilaian.destroy', $tp))->assertForbidden();

    expect($tp->fresh()->deskripsi)->not->toBe('Coba ubah');
});

it('blocks deleting a komponen penilaian already used by nilai siswa, same guard as the admin side', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    mengajar($lembaga, $guru, $mapel, $semester, $kelas);
    $user = actingAsGuruKomponenPenilaian($guru);

    $tp = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    NilaiSiswa::factory()->create(['komponen_penilaian_id' => $tp->id, 'siswa_id' => $siswa->id]);

    $this->actingAs($user)->delete(route('guru.komponen-penilaian.destroy', $tp))->assertRedirect();

    expect(KomponenPenilaian::find($tp->id))->not->toBeNull();
});

it('rejects storing a new assessment component when total bobot exceeds 100 percent in Guru portal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    mengajar($lembaga, $guru, $mapel, $semester, $kelas);
    $user = actingAsGuruKomponenPenilaian($guru);

    KomponenPenilaian::create([
        'lembaga_id' => $lembaga->id,
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'kode' => 'G-1',
        'deskripsi' => 'Existing Guru',
        'bobot' => 90,
    ]);

    $response = $this->actingAs($user)->postJson(route('guru.komponen-penilaian.store'), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'kode' => 'G-2',
        'deskripsi' => 'Overload Guru',
        'bobot' => 20,
    ]);

    $response->assertStatus(422)->assertJson(['status' => 'error']);
    expect(KomponenPenilaian::where('kode', 'G-2')->exists())->toBeFalse();
});

it('shows the subjek_type radio options on the guru create form', function () {
    $yayasan = Yayasan::factory()->create();

    $lembagaPaud = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'TK']);
    $guruPaud = Guru::factory()->create(['lembaga_id' => $lembagaPaud->id]);
    $userPaud = actingAsGuruKomponenPenilaian($guruPaud);

    $responsePaud = $this->actingAs($userPaud)->get(route('guru.komponen-penilaian.create'));
    $responsePaud->assertOk();
    $responsePaud->assertSee('name="subjek_type"', false);
    $responsePaud->assertSee('name="kktp_minimal"', false);
});
