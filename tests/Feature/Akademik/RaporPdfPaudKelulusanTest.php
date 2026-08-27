<?php

use App\Domains\Akademik\Actions\Rapor\SimpanCatatanWaliKelasAction;
use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Domains\Akademik\Services\RaporPdfDataBuilder;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function siapkanSiswaPaud(string $bentukPendidikan, string $tingkat, int $urutanSemester): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => $bentukPendidikan]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'urutan' => $urutanSemester]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'tingkat' => $tingkat]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'status' => 'aktif']);

    return compact('lembaga', 'kelas', 'semester', 'siswa');
}

it('renders Keterangan Kelulusan with the correct keterangan_kenaikan content for TK tingkat B on Genap semester', function () {
    ['siswa' => $siswa, 'semester' => $semester] = siapkanSiswaPaud('TK', 'B', 2);
    (new SimpanCatatanWaliKelasAction)->execute(CatatanWaliKelasData::fromArray([
        'siswa_id' => $siswa->id,
        'semester_id' => $semester->id,
        'keterangan_kenaikan' => 'Siap melanjutkan ke SD',
    ]));

    $data = app(RaporPdfDataBuilder::class)->build($siswa->fresh(), $semester);
    $html = view('pdf.rapor.paud', $data)->render();

    expect($html)->toContain('Keterangan Kelulusan');
    expect($html)->toContain('Siap melanjutkan ke SD');
});

it('does not render the kenaikan/kelulusan section at all on Ganjil semester', function () {
    ['siswa' => $siswa, 'semester' => $semester] = siapkanSiswaPaud('TK', 'B', 1);
    (new SimpanCatatanWaliKelasAction)->execute(CatatanWaliKelasData::fromArray([
        'siswa_id' => $siswa->id,
        'semester_id' => $semester->id,
        'keterangan_kenaikan' => 'Tidak seharusnya muncul',
    ]));

    $data = app(RaporPdfDataBuilder::class)->build($siswa->fresh(), $semester);
    $html = view('pdf.rapor.paud', $data)->render();

    expect($html)->not->toContain('Keterangan Kelulusan');
    expect($html)->not->toContain('Keterangan Kenaikan Kelas');
    expect($html)->not->toContain('Tidak seharusnya muncul');
});

it('renders Keterangan Kenaikan Kelas (not Kelulusan) for TK tingkat A on Genap semester', function () {
    ['siswa' => $siswa, 'semester' => $semester] = siapkanSiswaPaud('TK', 'A', 2);

    $data = app(RaporPdfDataBuilder::class)->build($siswa->fresh(), $semester);
    $html = view('pdf.rapor.paud', $data)->render();

    expect($html)->toContain('Keterangan Kenaikan Kelas');
    expect($html)->not->toContain('Keterangan Kelulusan');
});

it('never renders Keterangan Kelulusan for KB/TPA/SPS at tingkat B, even on Genap semester', function (string $bentukPendidikan) {
    ['siswa' => $siswa, 'semester' => $semester] = siapkanSiswaPaud($bentukPendidikan, 'B', 2);

    $data = app(RaporPdfDataBuilder::class)->build($siswa->fresh(), $semester);
    $html = view('pdf.rapor.paud', $data)->render();

    expect($html)->toContain('Keterangan Kenaikan Kelas');
    expect($html)->not->toContain('Keterangan Kelulusan');
})->with(['KB', 'TPA', 'SPS']);
