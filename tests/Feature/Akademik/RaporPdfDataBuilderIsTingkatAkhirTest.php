<?php

use App\Domains\Akademik\Services\RaporPdfDataBuilder;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function hasilIsTingkatAkhir(string $bentukPendidikan, string $tingkat): bool
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => $bentukPendidikan]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'urutan' => 2]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'tingkat' => $tingkat]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'status' => 'aktif']);

    $data = app(RaporPdfDataBuilder::class)->build($siswa, $semester);

    return $data['isTingkatAkhir'];
}

it('treats TK tingkat B as tingkat akhir (Kelulusan PAUD)', function () {
    expect(hasilIsTingkatAkhir('TK', 'B'))->toBeTrue();
});

it('does not treat TK tingkat A as tingkat akhir', function () {
    expect(hasilIsTingkatAkhir('TK', 'A'))->toBeFalse();
});

it('never treats KB as tingkat akhir, even at tingkat B', function () {
    expect(hasilIsTingkatAkhir('KB', 'B'))->toBeFalse();
});

it('never treats TPA as tingkat akhir, even at tingkat B', function () {
    expect(hasilIsTingkatAkhir('TPA', 'B'))->toBeFalse();
});

it('never treats SPS as tingkat akhir, even at tingkat B', function () {
    expect(hasilIsTingkatAkhir('SPS', 'B'))->toBeFalse();
});

it('still treats SD tingkat 6 as tingkat akhir (regression)', function () {
    expect(hasilIsTingkatAkhir('SD', '6'))->toBeTrue();
});

it('still treats SLB tingkat 6 as tingkat akhir (regression)', function () {
    expect(hasilIsTingkatAkhir('SLB', '6'))->toBeTrue();
});

it('still treats SMP tingkat 9 as tingkat akhir (regression)', function () {
    expect(hasilIsTingkatAkhir('SMP', '9'))->toBeTrue();
});

it('still treats SMA tingkat 12 as tingkat akhir (regression)', function () {
    expect(hasilIsTingkatAkhir('SMA', '12'))->toBeTrue();
});

it('still treats SMK tingkat 12 as tingkat akhir (regression)', function () {
    expect(hasilIsTingkatAkhir('SMK', '12'))->toBeTrue();
});
