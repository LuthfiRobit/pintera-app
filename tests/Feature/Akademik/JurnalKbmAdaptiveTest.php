<?php

use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Enums\Hari;
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
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;

function siapkanGuruKelasTematik(): array
{
    Carbon::setTestNow(Carbon::parse('2026-08-19')); // a Wednesday

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'TK', 'hari_libur_mingguan' => [0]]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 1, 'jam_mulai' => '08:00:00', 'jam_selesai' => '08:30:00', 'is_pelajaran' => true]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 2, 'jam_mulai' => '08:30:00', 'jam_selesai' => '09:00:00', 'is_pelajaran' => true]);

    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_kelas_tematik', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);
    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruUser->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $guruUser->id]);

    $kelas = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'pola_jam_id' => $pola->id,
        'wali_kelas_guru_id' => $guru->id,
    ]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return compact('guruUser', 'guru', 'kelas', 'semester', 'siswa');
}

it('auto-generates exactly one tematik sesi for the wali kelas guru today, with no mata pelajaran', function () {
    ['guruUser' => $guruUser, 'guru' => $guru, 'kelas' => $kelas] = siapkanGuruKelasTematik();

    $response = $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index'));

    $response->assertOk();
    $response->assertViewHas('sesiList', fn ($list) => $list->count() === 1);

    $sesi = SesiPembelajaran::where('kelas_id', $kelas->id)->firstOrFail();
    expect($sesi->jadwal_pelajaran_id)->toBeNull();
    expect($sesi->mata_pelajaran_id)->toBeNull();
    expect($sesi->guru_id)->toBe($guru->id);
    expect($sesi->isTematik())->toBeTrue();
});

it('lets the wali kelas guru view and fill jurnal plus presensi for the tematik sesi through the same show/update routes as sesi mapel', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa] = siapkanGuruKelasTematik();
    $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index')); // triggers generation
    $sesi = SesiPembelajaran::firstOrFail();

    $showResponse = $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.show', $sesi));
    $showResponse->assertOk();
    $showResponse->assertViewHas('sesi', fn ($viewSesi) => $viewSesi->is($sesi));

    $this->actingAs($guruUser)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Mengenal warna dan bentuk',
        'presensi' => [
            $siswa->id => 'sakit',
        ],
    ])->assertRedirect(route('guru.jurnal-kbm.index'));

    expect($sesi->fresh()->materi)->toBe('Mengenal warna dan bentuk');
    expect($sesi->fresh()->presensi()->where('siswa_id', $siswa->id)->first()->status->value)->toBe('sakit');
});

it('shows scheduled mata pelajaran names as an informational badge on the tematik sesi, without linking it to mata_pelajaran_id', function () {
    ['guruUser' => $guruUser, 'guru' => $guru, 'kelas' => $kelas] = siapkanGuruKelasTematik();
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'nama' => 'Bahasa (BHS)']);
    JadwalPelajaran::create([
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => JamPelajaran::where('pola_jam_id', $kelas->pola_jam_id)->where('hari', Hari::Rabu->value)->where('urutan', 1)->firstOrFail()->id,
        'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id,
        'semester_id' => Semester::where('tahun_ajaran_id', $kelas->tahun_ajaran_id)->where('status_aktif', true)->firstOrFail()->id,
    ]);

    $response = $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index'));

    $response->assertOk();
    $response->assertSee('Bahasa (BHS)');

    $sesi = SesiPembelajaran::where('kelas_id', $kelas->id)->firstOrFail();
    expect($sesi->mata_pelajaran_id)->toBeNull(); // display-only, data sesi tidak berubah

    $showResponse = $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.show', $sesi));
    $showResponse->assertOk();
    $showResponse->assertSee('Bahasa (BHS)');
});

it('does not generate a tematik sesi when the wali kelas guru is on a libur day', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas] = siapkanGuruKelasTematik();
    Carbon::setTestNow(Carbon::parse('2026-08-16')); // a Sunday, weekly off-day per hari_libur_mingguan => [0]

    $response = $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index'));

    $response->assertOk();
    $response->assertViewHas('sesiList', fn ($list) => $list->count() === 0);
    expect(SesiPembelajaran::where('kelas_id', $kelas->id)->count())->toBe(0);
});
