<?php

use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\KartuSiswa;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Models\PolaJam;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Identity\Models\Person;
use App\Enums\Hari;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;

if (! function_exists('siapkanGuruDenganJadwalHariIni')) {
    function siapkanGuruDenganJadwalHariIni(): array
    {
        Carbon::setTestNow(Carbon::parse('2026-08-19')); // a Wednesday

        $yayasan = Yayasan::factory()->create();
        $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SMP', 'hari_libur_mingguan' => [0]]);
        $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
        $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
        $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
        $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
        $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'is_pelajaran' => true]);
        $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

        Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
        $role->givePermissionTo(['presensi.isi']);
        $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $guruUser->assignRole($role);
        $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $guruUser->id]);

        $jadwal = JadwalPelajaran::create([
            'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id, 'semester_id' => $semester->id,
        ]);
        $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

        return compact('guruUser', 'guru', 'kelas', 'jadwal', 'semester', 'siswa');
    }
}

it('resolve-kartu mengembalikan 200 dan data siswa untuk kode yang valid', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa] = siapkanGuruDenganJadwalHariIni();
    $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index'));
    $sesi = SesiPembelajaran::firstOrFail();
    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-guru-scan', 'is_active' => true]);

    $response = $this->actingAs($guruUser)->postJson(route('guru.jurnal-kbm.resolve-kartu', $sesi), ['kode' => 'kode-guru-scan']);

    $response->assertOk();
    $response->assertJson(['siswa_id' => $siswa->id, 'nama_lengkap' => $siswa->nama_lengkap]);
});

it('resolve-kartu mengembalikan 422 untuk kode yang tidak ditemukan', function () {
    ['guruUser' => $guruUser] = siapkanGuruDenganJadwalHariIni();
    $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index'));
    $sesi = SesiPembelajaran::firstOrFail();

    $response = $this->actingAs($guruUser)->postJson(route('guru.jurnal-kbm.resolve-kartu', $sesi), ['kode' => 'kode-tidak-ada']);

    $response->assertStatus(422);
    $response->assertJson(['message' => 'Kode kartu tidak valid atau sudah tidak aktif.']);
});

it('resolve-kartu mengembalikan 422 untuk siswa beda kelas dari sesi', function () {
    ['guruUser' => $guruUser] = siapkanGuruDenganJadwalHariIni();
    $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index'));
    $sesi = SesiPembelajaran::firstOrFail();
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $sesi->kelas->lembaga_id, 'tahun_ajaran_id' => $sesi->kelas->tahun_ajaran_id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $sesi->kelas->lembaga_id, 'kelas_id' => $kelasLain->id]);
    KartuSiswa::create(['siswa_id' => $siswaLain->id, 'tipe' => 'qr', 'kode' => 'kode-beda-kelas', 'is_active' => true]);

    $response = $this->actingAs($guruUser)->postJson(route('guru.jurnal-kbm.resolve-kartu', $sesi), ['kode' => 'kode-beda-kelas']);

    $response->assertStatus(422);
    $response->assertJson(['message' => 'Siswa ini tidak terdaftar di kelas untuk sesi ini.']);
});

it('resolve-kartu mengembalikan 422 untuk siswa beda lembaga dari guru', function () {
    ['guruUser' => $guruUser] = siapkanGuruDenganJadwalHariIni();
    $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index'));
    $sesi = SesiPembelajaran::firstOrFail();
    $yayasan = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembagaLain->id, 'kelas_id' => $sesi->kelas_id]);
    KartuSiswa::create(['siswa_id' => $siswaLain->id, 'tipe' => 'qr', 'kode' => 'kode-beda-lembaga', 'is_active' => true]);

    $response = $this->actingAs($guruUser)->postJson(route('guru.jurnal-kbm.resolve-kartu', $sesi), ['kode' => 'kode-beda-lembaga']);

    $response->assertStatus(422);
    $response->assertJson(['message' => 'Siswa ini tidak terdaftar di lembaga Anda.']);
});

it('resolve-kartu berfungsi untuk guru piket yang mengisi sesi guru lain, bukan cuma guru pemilik', function () {
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_piket_resolve_kartu_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $guruPemilik = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $sesi = SesiPembelajaran::factory()->create([
        'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'guru_id' => $guruPemilik->id, 'tanggal' => now()->toDateString(),
    ]);

    $guruPiket = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $userPiket = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Person::where('id', $guruPiket->person_id)->update(['user_id' => $userPiket->id]);
    $userPiket->assignRole($role);
    PiketHarian::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guruPiket->id, 'tanggal' => now()->toDateString(), 'sumber' => 'override_manual',
    ]);

    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-guru-piket-scan', 'is_active' => true]);

    $response = $this->actingAs($userPiket)->postJson(route('guru.jurnal-kbm.resolve-kartu', $sesi), ['kode' => 'kode-guru-piket-scan']);

    $response->assertOk();
    $response->assertJson(['siswa_id' => $siswa->id, 'nama_lengkap' => $siswa->nama_lengkap]);
});
