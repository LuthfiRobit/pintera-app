<?php

declare(strict_types=1);

use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['jadwal-pelajaran.kelola']);

    $this->yayasan = Yayasan::factory()->create();
    $this->lembaga = Lembaga::factory()->create(['yayasan_id' => $this->yayasan->id]);
    $this->tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $this->lembaga->id, 'status_aktif' => true]);
    $this->semester = Semester::factory()->create(['tahun_ajaran_id' => $this->tahunAjaran->id, 'status_aktif' => true]);
    $this->pola = PolaJam::factory()->create(['lembaga_id' => $this->lembaga->id]);

    $this->gedung = Gedung::create([
        'yayasan_id' => $this->yayasan->id,
        'lembaga_id' => $this->lembaga->id,
        'nama_gedung' => 'Gedung Utama',
        'kode_gedung' => 'G01',
    ]);

    $this->ruangan1 = Ruangan::create([
        'yayasan_id' => $this->yayasan->id,
        'lembaga_id' => $this->lembaga->id,
        'gedung_id' => $this->gedung->id,
        'nama_ruangan' => 'Lab Komputer 1',
        'kode_ruangan' => 'LAB-01',
        'kapasitas' => 30,
        'status_kondisi' => 'baik',
    ]);

    $this->ruangan2 = Ruangan::create([
        'yayasan_id' => $this->yayasan->id,
        'lembaga_id' => $this->lembaga->id,
        'gedung_id' => $this->gedung->id,
        'nama_ruangan' => 'Lab Bahasa',
        'kode_ruangan' => 'LAB-02',
        'kapasitas' => 30,
        'status_kondisi' => 'baik',
    ]);

    $this->kelasA = Kelas::factory()->create([
        'lembaga_id' => $this->lembaga->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'pola_jam_id' => $this->pola->id,
        'nama' => 'X-A',
        'ruangan_id' => $this->ruangan1->id,
    ]);

    $this->kelasB = Kelas::factory()->create([
        'lembaga_id' => $this->lembaga->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'pola_jam_id' => $this->pola->id,
        'nama' => 'X-B',
        'ruangan_id' => $this->ruangan2->id,
    ]);

    $this->jam1 = JamPelajaran::factory()->create(['pola_jam_id' => $this->pola->id, 'is_pelajaran' => true, 'urutan' => 1]);
    $this->jam2 = JamPelajaran::factory()->create(['pola_jam_id' => $this->pola->id, 'is_pelajaran' => true, 'urutan' => 2]);

    $this->mapel = MataPelajaran::factory()->create(['lembaga_id' => $this->lembaga->id]);
    $this->guru1 = Guru::factory()->create(['lembaga_id' => $this->lembaga->id, 'nama' => 'Ustadz Ahmad']);
    $this->guru2 = Guru::factory()->create(['lembaga_id' => $this->lembaga->id, 'nama' => 'Ustadzah Fatimah']);

    $this->manager = User::factory()->create(['lembaga_id' => $this->lembaga->id]);
    $this->manager->assignRole($role);
});

it('berhasil menyimpan jadwal pelajaran dengan ruangan sarpras terpilih', function () {
    $this->actingAs($this->manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $this->kelasA->id,
        'jam_pelajaran_id' => [$this->jam1->id],
        'mata_pelajaran_id' => $this->mapel->id,
        'guru_id' => $this->guru1->id,
        'semester_id' => $this->semester->id,
        'ruangan_id' => $this->ruangan1->id,
    ])->assertRedirect();

    $jadwal = JadwalPelajaran::where('kelas_id', $this->kelasA->id)
        ->where('jam_pelajaran_id', $this->jam1->id)
        ->first();

    expect($jadwal)->not->toBeNull()
        ->and($jadwal->ruangan_id)->toBe($this->ruangan1->id);
});

it('mencegah bentrok ruangan jika ruangan yang sama digunakan kelas lain pada jam yang sama', function () {
    // Kelas A menempati Ruangan 1 pada Jam 1
    JadwalPelajaran::create([
        'lembaga_id' => $this->lembaga->id,
        'kelas_id' => $this->kelasA->id,
        'jam_pelajaran_id' => $this->jam1->id,
        'mata_pelajaran_id' => $this->mapel->id,
        'guru_id' => $this->guru1->id,
        'semester_id' => $this->semester->id,
        'ruangan_id' => $this->ruangan1->id,
    ]);

    // Kelas B mencoba memakai Ruangan 1 pada Jam 1 dengan guru berbeda
    $response = $this->actingAs($this->manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $this->kelasB->id,
        'jam_pelajaran_id' => [$this->jam1->id],
        'mata_pelajaran_id' => $this->mapel->id,
        'guru_id' => $this->guru2->id,
        'semester_id' => $this->semester->id,
        'ruangan_id' => $this->ruangan1->id,
    ]);

    $response->assertSessionHasErrors('jam_pelajaran_id');

    // Pastikan jadwal kelas B tidak tersimpan
    expect(JadwalPelajaran::where('kelas_id', $this->kelasB->id)->count())->toBe(0);
});

it('mencegah bentrok guru jika guru yang sama mengajar di kelas lain pada jam yang sama', function () {
    // Guru 1 mengajar Kelas A di Ruangan 1 pada Jam 1
    JadwalPelajaran::create([
        'lembaga_id' => $this->lembaga->id,
        'kelas_id' => $this->kelasA->id,
        'jam_pelajaran_id' => $this->jam1->id,
        'mata_pelajaran_id' => $this->mapel->id,
        'guru_id' => $this->guru1->id,
        'semester_id' => $this->semester->id,
        'ruangan_id' => $this->ruangan1->id,
    ]);

    // Kelas B mencoba menugaskan Guru 1 pada Jam 1 di Ruangan 2
    $response = $this->actingAs($this->manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $this->kelasB->id,
        'jam_pelajaran_id' => [$this->jam1->id],
        'mata_pelajaran_id' => $this->mapel->id,
        'guru_id' => $this->guru1->id,
        'semester_id' => $this->semester->id,
        'ruangan_id' => $this->ruangan2->id,
    ]);

    $response->assertSessionHasErrors('jam_pelajaran_id');
    expect(JadwalPelajaran::where('kelas_id', $this->kelasB->id)->count())->toBe(0);
});

it('memungkinkan update jadwal ke ruangan lain yang bebas', function () {
    $jadwal = JadwalPelajaran::create([
        'lembaga_id' => $this->lembaga->id,
        'kelas_id' => $this->kelasA->id,
        'jam_pelajaran_id' => $this->jam1->id,
        'mata_pelajaran_id' => $this->mapel->id,
        'guru_id' => $this->guru1->id,
        'semester_id' => $this->semester->id,
        'ruangan_id' => $this->ruangan1->id,
    ]);

    $response = $this->actingAs($this->manager)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'kelas_id' => $this->kelasA->id,
        'jam_pelajaran_id' => $this->jam1->id,
        'mata_pelajaran_id' => $this->mapel->id,
        'guru_id' => $this->guru1->id,
        'semester_id' => $this->semester->id,
        'ruangan_id' => $this->ruangan2->id,
    ]);

    $response->assertRedirect();
    expect($jadwal->fresh()->ruangan_id)->toBe($this->ruangan2->id);
});

it('hanya menampilkan ruangan milik lembaga terkait atau ruangan bersama (is_shared)', function () {
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $this->yayasan->id]);
    $gedungLain = Gedung::create([
        'yayasan_id' => $this->yayasan->id,
        'lembaga_id' => $lembagaLain->id,
        'nama_gedung' => 'Gedung Lembaga Lain',
        'kode_gedung' => 'G-LAIN',
    ]);

    $ruanganLembagaLain = Ruangan::create([
        'yayasan_id' => $this->yayasan->id,
        'lembaga_id' => $lembagaLain->id,
        'gedung_id' => $gedungLain->id,
        'nama_ruangan' => 'Lab Komputer Lembaga Lain',
        'kode_ruangan' => 'LAB-LAIN',
        'is_shared' => false,
        'is_aktif' => true,
    ]);

    $ruanganShared = Ruangan::create([
        'yayasan_id' => $this->yayasan->id,
        'lembaga_id' => $lembagaLain->id,
        'gedung_id' => $gedungLain->id,
        'nama_ruangan' => 'Aula Bersama Yayasan',
        'kode_ruangan' => 'AULA-SHARED',
        'is_shared' => true,
        'is_aktif' => true,
    ]);

    // Akses halaman create jadwal untuk kelasA di Lembaga 1
    $response = $this->actingAs($this->manager)->get(route('admin.jadwal-pelajaran.create', [
        'kelas_id' => $this->kelasA->id,
        'semester_id' => $this->semester->id,
    ]));

    $response->assertOk();
    $ruanganList = $response->viewData('ruanganList');

    // Harus berisi ruangan lembaga 1 dan ruangan shared
    expect($ruanganList->pluck('id'))->toContain($this->ruangan1->id)
        ->and($ruanganList->pluck('id'))->toContain($this->ruangan2->id)
        ->and($ruanganList->pluck('id'))->toContain($ruanganShared->id)
        ->and($ruanganList->pluck('id'))->not->toContain($ruanganLembagaLain->id);
});

