<?php

use App\Domains\Akademik\Actions\Jadwal\CreateJadwalPelajaranAction;
use App\Domains\Akademik\Actions\KenaikanKelas\ProsesKenaikanKelasAction;
use App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction;
use App\Domains\Akademik\DataTransferObjects\KenaikanKelasData;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Sarpras\Actions\ValidateRoomClashAction;
use App\Enums\StatusSiswa;
use App\Events\StudentUpdatedClass;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatKenaikanAction(): ProsesKenaikanKelasAction
{
    return new ProsesKenaikanKelasAction(
        new CreateJadwalPelajaranAction(new ValidateRoomClashAction),
        app(UpdateStatusSiswaAction::class),
    );
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
    ])))->toThrow(DomainException::class);
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

it('throws a DomainException when kelas tujuan is in a tahun ajaran with an earlier tanggal_mulai than kelas lama', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'tanggal_mulai' => '2026-07-01']);
    $tahunMundur = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'tanggal_mulai' => '2025-07-01']);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $kelasMundur = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunMundur->id]);
    $siswa = Siswa::factory()->create(['kelas_id' => $kelasLama->id]);

    expect(fn () => buatKenaikanAction()->execute(new KenaikanKelasData(mapping: [
        $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasMundur->id, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
    ])))->toThrow(DomainException::class);

    expect($siswa->fresh()->kelas_id)->toBe($kelasLama->id);
});

it('promotes siswa when tahun ajaran tujuan has a later tanggal_mulai than kelas lama', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'tanggal_mulai' => '2025-07-01']);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'tanggal_mulai' => '2026-07-01']);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id]);
    $siswa = Siswa::factory()->create(['kelas_id' => $kelasLama->id]);

    buatKenaikanAction()->execute(new KenaikanKelasData(mapping: [
        $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasBaru->id, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
    ]));

    expect($siswa->fresh()->kelas_id)->toBe($kelasBaru->id);
});

it('deactivates the siswa user account when marking a kelas as lulus', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLulus = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $user = User::factory()->create(['is_active' => true]);
    $siswaLulus = Siswa::factory()->create(['kelas_id' => $kelasLulus->id, 'user_id' => $user->id]);

    buatKenaikanAction()->execute(new KenaikanKelasData(mapping: [
        $kelasLulus->id => ['tindakan' => 'lulus', 'kelas_baru_id' => null, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
    ]));

    expect($user->fresh()->is_active)->toBeFalse();
});

it('returns siswaNaik, siswaLulus, and kelasDilewati counts alongside jadwalGagal', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasNaik = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id]);
    $kelasLulus = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $kelasKosong = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    Siswa::factory()->count(2)->create(['kelas_id' => $kelasNaik->id]);
    Siswa::factory()->count(3)->create(['kelas_id' => $kelasLulus->id]);

    $result = buatKenaikanAction()->execute(new KenaikanKelasData(mapping: [
        $kelasNaik->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasBaru->id, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
        $kelasLulus->id => ['tindakan' => 'lulus', 'kelas_baru_id' => null, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
        $kelasKosong->id => ['tindakan' => 'lewati', 'kelas_baru_id' => null, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
    ]));

    expect($result['siswaNaik'])->toBe(2)
        ->and($result['siswaLulus'])->toBe(3)
        ->and($result['kelasDilewati'])->toBe(1)
        ->and($result['jadwalGagal'])->toBe([]);
});

it('dispatches StudentUpdatedClass event when promoting siswa to a new kelas', function () {
    Event::fake([StudentUpdatedClass::class]);

    $lembaga = Lembaga::factory()->create();
    $tahunLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id]);
    $siswa = Siswa::factory()->create(['kelas_id' => $kelasLama->id]);

    buatKenaikanAction()->execute(new KenaikanKelasData(mapping: [
        $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasBaru->id, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
    ]));

    Event::assertDispatched(
        StudentUpdatedClass::class,
        fn ($event) => $event->siswa->id === $siswa->id
    );
});
