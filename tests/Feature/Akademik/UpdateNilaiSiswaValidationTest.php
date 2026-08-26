<?php

// tests/Feature/Akademik/UpdateNilaiSiswaValidationTest.php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
});

function buatAsesmenDuaKomponenTipeBeda(): array
{
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $user = User::factory()->create(['lembaga_id' => $lembaga]);
    $user->assignRole('guru');
    $guru = Guru::factory()->create(['user_id' => $user->id, 'lembaga_id' => $lembaga]);

    $siswaSatu = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);
    $siswaDua = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    $komponenNumeric = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'numeric',
    ]);
    $komponenNarrative = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'narrative',
    ]);

    $asesmen = Asesmen::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
    ]);
    $asesmen->komponenPenilaian()->attach([$komponenNumeric->id, $komponenNarrative->id]);

    return compact('user', 'asesmen', 'siswaSatu', 'siswaDua', 'komponenNumeric', 'komponenNarrative');
}

it('validates each siswa x komponen cell against ITS OWN assessment_type, not mixed up across siswa or komponen', function () {
    ['user' => $user, 'asesmen' => $asesmen, 'siswaSatu' => $siswaSatu, 'siswaDua' => $siswaDua, 'komponenNumeric' => $komponenNumeric, 'komponenNarrative' => $komponenNarrative] = buatAsesmenDuaKomponenTipeBeda();

    // Siswa 1: numeric diisi benar, narrative diisi benar.
    // Siswa 2: numeric diisi benar, narrative DIKOSONGKAN (harus ditolak).
    $response = $this->actingAs($user)->put(route('guru.asesmen.update-nilai', $asesmen), [
        'nilai' => [
            $siswaSatu->id => [
                $komponenNumeric->id => ['nilai_angka' => 80],
                $komponenNarrative->id => ['catatan' => 'Berkembang baik'],
            ],
            $siswaDua->id => [
                $komponenNumeric->id => ['nilai_angka' => 90],
                $komponenNarrative->id => ['catatan' => ''],
            ],
        ],
    ]);

    $response->assertSessionHasErrors("nilai.{$siswaDua->id}.{$komponenNarrative->id}.catatan");
    $response->assertSessionDoesntHaveErrors("nilai.{$siswaSatu->id}.{$komponenNumeric->id}.nilai_angka");
    $response->assertSessionDoesntHaveErrors("nilai.{$siswaSatu->id}.{$komponenNarrative->id}.catatan");
    $response->assertSessionDoesntHaveErrors("nilai.{$siswaDua->id}.{$komponenNumeric->id}.nilai_angka");
});

it('ignores an assessment_type field forcibly injected into the nilai payload, and does not let it affect validation', function () {
    ['user' => $user, 'asesmen' => $asesmen, 'siswaSatu' => $siswaSatu, 'komponenNumeric' => $komponenNumeric] = buatAsesmenDuaKomponenTipeBeda();

    // Guru/klien coba menyamarkan komponen numeric seolah predikat lewat field liar --
    // request TIDAK PERNAH membaca field ini dari payload, jadi harus tetap divalidasi
    // sbg numeric (assessment_type asli komponen dari DB), bukan ikut nilai yang dikirim.
    $response = $this->actingAs($user)->put(route('guru.asesmen.update-nilai', $asesmen), [
        'nilai' => [
            $siswaSatu->id => [
                $komponenNumeric->id => ['nilai_angka' => 80, 'assessment_type' => 'predicate'],
            ],
        ],
    ]);

    $response->assertSessionDoesntHaveErrors("nilai.{$siswaSatu->id}.{$komponenNumeric->id}.nilai_angka");
});
