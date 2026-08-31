<?php

namespace Tests\Unit\Domains\Sarpras;

use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Domains\Sarpras\Actions\CreateGedungAction;
use App\Domains\Sarpras\Actions\CreateRuanganAction;
use App\Domains\Sarpras\Actions\UpdateGedungAction;
use App\Domains\Sarpras\Actions\UpdateRuanganAction;
use App\Domains\Sarpras\Actions\ValidateRoomClashAction;
use App\Domains\Sarpras\DataTransferObjects\GedungData;
use App\Domains\Sarpras\DataTransferObjects\RuanganData;
use App\Domains\Sarpras\Enums\JenisRuangan;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GedungRuanganActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_update_gedung_action(): void
    {
        $yayasan = Yayasan::create(['nama' => 'Yayasan']);
        $lembaga = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'SDIT', 'jenjang' => 'SD', 'npsn' => '123', 'status_aktif' => true]);

        $dto = new GedungData(
            yayasanId: $yayasan->id,
            lembagaId: $lembaga->id,
            kodeGedung: 'GD-1',
            namaGedung: 'Gedung Timur',
            jumlahLantai: 2,
        );

        $action = new CreateGedungAction;
        $gedung = $action->execute($dto);

        $this->assertInstanceOf(Gedung::class, $gedung);
        $this->assertEquals('GD-1', $gedung->kode_gedung);

        $updateDto = new GedungData(
            yayasanId: $yayasan->id,
            lembagaId: $lembaga->id,
            kodeGedung: 'GD-1-REV',
            namaGedung: 'Gedung Timur Baru',
            jumlahLantai: 3,
        );

        $updateAction = new UpdateGedungAction;
        $updated = $updateAction->execute($gedung, $updateDto);

        $this->assertEquals('Gedung Timur Baru', $updated->nama_gedung);
        $this->assertEquals(3, $updated->jumlah_lantai);
    }

    public function test_create_and_update_ruangan_action(): void
    {
        $yayasan = Yayasan::create(['nama' => 'Yayasan']);
        $lembaga = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'SDIT', 'jenjang' => 'SD', 'npsn' => '123', 'status_aktif' => true]);
        $gedung = Gedung::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_gedung' => 'GD-1', 'nama_gedung' => 'Gedung 1']);

        $dto = new RuanganData(
            yayasanId: $yayasan->id,
            lembagaId: $lembaga->id,
            gedungId: $gedung->id,
            kodeRuangan: 'LAB-1',
            namaRuangan: 'Laboratorium Komputer',
            lantai: 2,
            jenisRuangan: JenisRuangan::Laboratorium,
            kapasitasSiswa: 30,
            luasM2: 60.50,
            isShared: true,
        );

        $createAction = new CreateRuanganAction;
        $ruangan = $createAction->execute($dto);

        $this->assertEquals('LAB-1', $ruangan->kode_ruangan);
        $this->assertTrue($ruangan->is_shared);

        $updateDto = new RuanganData(
            yayasanId: $yayasan->id,
            lembagaId: $lembaga->id,
            gedungId: $gedung->id,
            kodeRuangan: 'LAB-1-RENOV',
            namaRuangan: 'Laboratorium Komputer & Multimedia',
            lantai: 2,
            jenisRuangan: JenisRuangan::Laboratorium,
            kapasitasSiswa: 35,
            luasM2: 70.00,
            isShared: true,
        );

        $updateAction = new UpdateRuanganAction;
        $updated = $updateAction->execute($ruangan, $updateDto);

        $this->assertEquals('Laboratorium Komputer & Multimedia', $updated->nama_ruangan);
        $this->assertEquals(35, $updated->kapasitas_siswa);
    }

    public function test_validate_room_clash_in_jadwal_pelajaran(): void
    {
        $yayasan = Yayasan::create(['nama' => 'Yayasan']);
        $lembaga = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'SDIT', 'jenjang' => 'SD', 'npsn' => '123', 'status_aktif' => true]);
        $ta = TahunAjaran::create([
            'lembaga_id' => $lembaga->id,
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'status_aktif' => true,
        ]);
        $sem = Semester::create([
            'lembaga_id' => $lembaga->id,
            'tahun_ajaran_id' => $ta->id,
            'nama' => 'Ganjil',
            'urutan' => 1,
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2026-12-31',
            'status_aktif' => true,
        ]);
        $gedung = Gedung::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_gedung' => 'GD-1', 'nama_gedung' => 'Gedung 1']);
        $ruangan = Ruangan::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'gedung_id' => $gedung->id, 'kode_ruangan' => 'LAB', 'nama_ruangan' => 'Lab']);
        $kelas = Kelas::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'nama' => '7A', 'tingkat' => 7]);
        $mapel = MataPelajaran::create(['lembaga_id' => $lembaga->id, 'nama' => 'Informatika', 'kode' => 'INF']);
        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $guru = Guru::factory()->create([
            'lembaga_id' => $lembaga->id,
            'user_id' => $user->id,
            'nama' => 'Budi M.Kom',
            'nip' => '12345',
            'nik' => '3201234567890001',
        ]);

        $pola = PolaJam::create(['lembaga_id' => $lembaga->id, 'nama' => 'Reguler']);
        $jam = JamPelajaran::create([
            'pola_jam_id' => $pola->id,
            'label' => 'Jam 1',
            'jam_mulai' => '07:30',
            'jam_selesai' => '08:15',
            'urutan' => 1,
        ]);

        $jadwalExisting = JadwalPelajaran::create([
            'lembaga_id' => $lembaga->id,
            'kelas_id' => $kelas->id,
            'semester_id' => $sem->id,
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'jam_pelajaran_id' => $jam->id,
            'ruangan_id' => $ruangan->id,
        ]);

        $validator = new ValidateRoomClashAction;

        // Should detect clash on the same semester and jam
        $isClash = $validator->execute(
            ruanganId: $ruangan->id,
            semesterId: $sem->id,
            jamPelajaranId: $jam->id
        );
        $this->assertTrue($isClash);

        // When ignoring the existing jadwal ID, it should be false (e.g. during update of the same record)
        $isSelfClash = $validator->execute(
            ruanganId: $ruangan->id,
            semesterId: $sem->id,
            jamPelajaranId: $jam->id,
            ignoreJadwalId: $jadwalExisting->id
        );
        $this->assertFalse($isSelfClash);
    }
}
