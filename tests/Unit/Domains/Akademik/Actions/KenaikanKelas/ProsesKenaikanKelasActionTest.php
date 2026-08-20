<?php

use App\Domains\Akademik\Actions\Jadwal\CreateJadwalPelajaranAction;
use App\Domains\Akademik\Actions\KenaikanKelas\ProsesKenaikanKelasAction;
use App\Domains\Akademik\DataTransferObjects\KenaikanKelasData;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Sarpras\Actions\ValidateRoomClashAction;
use App\Enums\StatusSiswa;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatKenaikanAction(): ProsesKenaikanKelasAction
{
    return new ProsesKenaikanKelasAction(new CreateJadwalPelajaranAction(new ValidateRoomClashAction));
}

it('promotes siswa to the destination kelas and marks lulus siswa accordingly', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id]);
    $kelasLulus = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $siswaNaik = Siswa::factory()->create(['kelas_id' => $kelasLama->id]);
    $siswaLulus = Siswa::factory()->create(['kelas_id' => $kelasLulus->id]);

    $result = buatKenaikanAction()->execute(new KenaikanKelasData(mapping: [
        $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasBaru->id, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
        $kelasLulus->id => ['tindakan' => 'lulus', 'kelas_baru_id' => null, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
    ]));

    expect($result['jadwalGagal'])->toBe([])
        ->and($siswaNaik->fresh()->kelas_id)->toBe($kelasBaru->id)
        ->and($siswaLulus->fresh()->status)->toBe(StatusSiswa::Lulus)
        ->and($siswaLulus->fresh()->kelas_id)->toBeNull();
});

it('throws a DomainException when kelas tujuan is in the same tahun ajaran as kelas lama', function () {
    $lembaga = Lembaga::factory()->create();
    $tahun = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahun->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahun->id]);

    expect(fn () => buatKenaikanAction()->execute(new KenaikanKelasData(mapping: [
        $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasBaru->id, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
    ])))->toThrow(\DomainException::class);
});

it('skips a jadwal row that clashes on guru at the destination and still promotes the siswa', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterTujuan = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id]);
    $siswa = Siswa::factory()->create(['kelas_id' => $kelasLama->id]);

    $jamPelajaran = JamPelajaran::factory()->create(['label' => 'Jam ke-1']);
    $guru = Guru::factory()->create();

    // Guru sudah mengajar kelas LAIN pada slot yang sama di semester tujuan — akan bentrok.
    JadwalPelajaran::factory()->create([
        'kelas_id' => Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id])->id,
        'guru_id' => $guru->id,
        'jam_pelajaran_id' => $jamPelajaran->id,
        'semester_id' => $semesterTujuan->id,
    ]);

    // Jadwal lama yang akan disalin, pakai guru yang sama di jam yang sama.
    JadwalPelajaran::factory()->create([
        'kelas_id' => $kelasLama->id,
        'guru_id' => $guru->id,
        'jam_pelajaran_id' => $jamPelajaran->id,
        'semester_id' => Semester::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id])->id,
    ]);

    $result = buatKenaikanAction()->execute(new KenaikanKelasData(mapping: [
        $kelasLama->id => [
            'tindakan' => 'naik',
            'kelas_baru_id' => $kelasBaru->id,
            'salin_jadwal' => true,
            'semester_tujuan_id' => $semesterTujuan->id,
        ],
    ]));

    expect($result['jadwalGagal'])->toHaveCount(1)
        ->and($siswa->fresh()->kelas_id)->toBe($kelasBaru->id)
        ->and(JadwalPelajaran::where('kelas_id', $kelasBaru->id)->where('semester_id', $semesterTujuan->id)->count())->toBe(0);
});
