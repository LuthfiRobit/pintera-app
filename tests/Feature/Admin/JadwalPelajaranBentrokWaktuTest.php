<?php

declare(strict_types=1);

use App\Domains\Akademik\Actions\Jadwal\CreateJadwalPelajaranAction;
use App\Domains\Akademik\DataTransferObjects\JadwalPelajaranData;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Domains\Sarpras\Actions\ValidateRoomClashAction;
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

it('menolak guru mengajar 2 kelas pada jam yang sama walau kelas-kelas itu pakai Pola Jam berbeda', function () {
    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $lembaga = Lembaga::factory()->create();
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->givePermissionTo('jadwal-pelajaran.kelola');

    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    // Pola Jam A: dipakai kelas 7A. Jam ke-1 = 07:00-07:40.
    $polaJamA = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jamA = JamPelajaran::factory()->create([
        'pola_jam_id' => $polaJamA->id,
        'hari' => 'senin',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:40',
        'is_pelajaran' => true,
    ]);
    $kelasA = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'pola_jam_id' => $polaJamA->id,
    ]);

    // Pola Jam B: dipakai kelas 8B, BEDA ID tapi jam wall-clock SAMA PERSIS (07:00-07:40 hari senin).
    $polaJamB = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jamB = JamPelajaran::factory()->create([
        'pola_jam_id' => $polaJamB->id,
        'hari' => 'senin',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:40',
        'is_pelajaran' => true,
    ]);
    $kelasB = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'pola_jam_id' => $polaJamB->id,
    ]);

    // Guru sudah dijadwalkan di kelas A pada jam A.
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::create([
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => $jamA->id,
        'guru_id' => $guru->id,
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $lembaga->id,
    ]);

    // Coba jadwalkan guru yang SAMA di kelas B pada jam B (ID beda, waktu wall-clock sama) -- HARUS ditolak.
    $response = $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasB->id,
        'jam_pelajaran_id' => [$jamB->id],
        'guru_id' => $guru->id,
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
    ]);

    $response->assertSessionHasErrors('jam_pelajaran_id');
    expect(JadwalPelajaran::where('kelas_id', $kelasB->id)->exists())->toBeFalse();
});

it('menolak update jadwal jika guru bentrok waktu dengan kelas lain', function () {
    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $lembaga = Lembaga::factory()->create();
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->givePermissionTo('jadwal-pelajaran.kelola');

    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruLain = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $polaJamA = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jamA = JamPelajaran::factory()->create([
        'pola_jam_id' => $polaJamA->id,
        'hari' => 'senin',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:40',
        'is_pelajaran' => true,
    ]);
    $kelasA = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'pola_jam_id' => $polaJamA->id,
    ]);

    $polaJamB = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jamB = JamPelajaran::factory()->create([
        'pola_jam_id' => $polaJamB->id,
        'hari' => 'senin',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:40',
        'is_pelajaran' => true,
    ]);
    $kelasB = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'pola_jam_id' => $polaJamB->id,
    ]);

    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    // Guru mengajar kelas A di jam A
    JadwalPelajaran::create([
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => $jamA->id,
        'guru_id' => $guru->id,
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $lembaga->id,
    ]);

    // Kelas B memiliki jadwal dengan guruLain di jam B
    $jadwalB = JadwalPelajaran::create([
        'kelas_id' => $kelasB->id,
        'jam_pelajaran_id' => $jamB->id,
        'guru_id' => $guruLain->id,
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $lembaga->id,
    ]);

    // Update jadwal B untuk menugaskan guru yang sama -> harus ditolak karena bentrok waktu dengan jam A
    $response = $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwalB), [
        'guru_id' => $guru->id,
        'jam_pelajaran_id' => $jamB->id,
        'mata_pelajaran_id' => $mapel->id,
    ]);

    $response->assertSessionHasErrors('guru_id');
    expect($jadwalB->fresh()->guru_id)->toBe($guruLain->id);
});

it('menolak jadwal jika ruangan bentrok waktu walau pola jam berbeda', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $gedung = Gedung::create([
        'yayasan_id' => $yayasan->id,
        'lembaga_id' => $lembaga->id,
        'nama_gedung' => 'Gedung Utama',
        'kode_gedung' => 'G01',
    ]);
    $ruangan = Ruangan::create([
        'yayasan_id' => $yayasan->id,
        'lembaga_id' => $lembaga->id,
        'gedung_id' => $gedung->id,
        'nama_ruangan' => 'Lab Komputer 1',
        'kode_ruangan' => 'LAB-01',
    ]);

    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jamA = JamPelajaran::factory()->create([
        'pola_jam_id' => $polaA->id,
        'hari' => 'selasa',
        'jam_mulai' => '08:00',
        'jam_selesai' => '08:45',
        'is_pelajaran' => true,
    ]);

    $polaB = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jamB = JamPelajaran::factory()->create([
        'pola_jam_id' => $polaB->id,
        'hari' => 'selasa',
        'jam_mulai' => '08:30', // Overlap 08:30 - 08:45
        'jam_selesai' => '09:15',
        'is_pelajaran' => true,
    ]);

    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'pola_jam_id' => $polaA->id]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'pola_jam_id' => $polaB->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    JadwalPelajaran::create([
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => $jamA->id,
        'guru_id' => $guru->id,
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'ruangan_id' => $ruangan->id,
        'lembaga_id' => $lembaga->id,
    ]);

    $validator = app(ValidateRoomClashAction::class);
    $isClash = $validator->execute(
        ruanganId: $ruangan->id,
        semesterId: $semester->id,
        jamPelajaranId: $jamB->id
    );

    expect($isClash)->toBeTrue();
});

it('tetap konsisten menolak bentrok guru setelah dibungkus lock (regresi, bukan tes concurrency asli)', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $polaJam = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create([
        'pola_jam_id' => $polaJam->id,
        'hari' => 'senin',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:40',
        'is_pelajaran' => true,
    ]);
    $kelasA = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'pola_jam_id' => $polaJam->id,
    ]);
    $kelasB = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'pola_jam_id' => $polaJam->id,
    ]);

    $action = app(CreateJadwalPelajaranAction::class);

    $dtoA = new JadwalPelajaranData(
        lembagaId: $lembaga->id,
        kelasId: $kelasA->id,
        guruId: $guru->id,
        jamPelajaranId: $jam->id,
        semesterId: $semester->id,
    );

    $action->execute($dtoA);

    $dtoB = new JadwalPelajaranData(
        lembagaId: $lembaga->id,
        kelasId: $kelasB->id,
        guruId: $guru->id,
        jamPelajaranId: $jam->id,
        semesterId: $semester->id,
    );

    expect(fn () => $action->execute($dtoB))
        ->toThrow(ValidationException::class);
});
