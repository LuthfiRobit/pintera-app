<?php

// tests/Unit/Actions/SimpanNilaiSiswaActionInvariantTest.php

use App\Domains\Akademik\Actions\Penilaian\SimpanNilaiSiswaAction;
use App\Domains\Akademik\DataTransferObjects\NilaiSiswaBatchData;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('forces nilai_angka/predikat/catatan to the correct combination regardless of what the payload contains, bypassing HTTP validation entirely', function (string $tipe, array $payloadKotor, array $expectedTersimpan) {
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    $komponen = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => $tipe,
    ]);
    $asesmen = Asesmen::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
    ]);
    $asesmen->komponenPenilaian()->attach($komponen->id);

    app(SimpanNilaiSiswaAction::class)->execute($asesmen, NilaiSiswaBatchData::fromArray([
        'nilai' => [$siswa->id => [$komponen->id => $payloadKotor]],
    ]));

    $nilai = NilaiSiswa::where('asesmen_id', $asesmen->id)->where('siswa_id', $siswa->id)->where('komponen_penilaian_id', $komponen->id)->first();

    expect($nilai->nilai_angka)->toBe($expectedTersimpan['nilai_angka']);
    expect($nilai->predikat?->value)->toBe($expectedTersimpan['predikat']);
    expect($nilai->catatan)->toBe($expectedTersimpan['catatan']);
})->with([
    'numeric komponen dgn predikat dipaksa ikut' => ['numeric', ['nilai_angka' => 85, 'predikat' => 'BSH', 'catatan' => 'x'], ['nilai_angka' => 85, 'predikat' => null, 'catatan' => 'x']],
    'narrative komponen dgn nilai_angka & predikat dipaksa ikut' => ['narrative', ['nilai_angka' => 85, 'predikat' => 'BSH', 'catatan' => 'y'], ['nilai_angka' => null, 'predikat' => null, 'catatan' => 'y']],
    'predicate komponen dgn nilai_angka dipaksa ikut' => ['predicate', ['nilai_angka' => 85, 'predikat' => 'BSH', 'catatan' => 'z'], ['nilai_angka' => null, 'predikat' => 'BSH', 'catatan' => 'z']],
]);
