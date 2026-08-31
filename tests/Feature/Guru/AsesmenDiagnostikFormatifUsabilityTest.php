<?php

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Domains\Akademik\Services\RaporCalculationService;
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
use Spatie\Permission\Models\Permission;

it('lets a guru create, fill, and read back a Formatif asesmen through the existing flow, while it stays excluded from rapor', function () {
    Permission::firstOrCreate(['name' => 'asesmen.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['asesmen.kelola']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'assessment_type' => 'numeric']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);
    $guru->person->update(['user_id' => $user->id]);

    JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ]);

    // 1. Guru membuat Asesmen Formatif.
    $storeResponse = $this->actingAs($user)->post(route('guru.asesmen.store'), [
        'kelas_id' => $kelas->id,
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'jenis' => JenisAsesmen::Formatif->value,
        'judul' => 'Latihan Formatif Usability',
        'tanggal' => now()->toDateString(),
        'komponen_id' => [$komponen->id],
    ]);
    $storeResponse->assertRedirect();
    $asesmen = Asesmen::where('judul', 'Latihan Formatif Usability')->firstOrFail();

    // 2. Guru buka halaman show -- harus tampil normal (reuse halaman existing).
    $this->actingAs($user)->get(route('guru.asesmen.show', $asesmen))->assertOk();

    // 3. Guru input nilai siswa.
    $updateResponse = $this->actingAs($user)->put(route('guru.asesmen.update-nilai', $asesmen), [
        'nilai' => [
            $siswa->id => [
                $komponen->id => ['nilai_angka' => 100],
            ],
        ],
    ]);
    $updateResponse->assertRedirect(route('guru.asesmen.show', $asesmen));

    // 4. Guru buka kembali halaman show -- nilai yang tadi diisi harus TERLIHAT.
    $showAgain = $this->actingAs($user)->get(route('guru.asesmen.show', $asesmen));
    $showAgain->assertOk();
    $showAgain->assertSee('100');

    // 5. Nilai Formatif ini TIDAK PERNAH masuk rekap rapor.
    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($kelas, $semester);
    expect($rekap['mapelList'])->toBeEmpty();
    expect($rekap['rekapNilai'][$siswa->id]['mata_pelajaran:'.$mapel->id] ?? null)->toBeNull();
    expect($rekap['classAvg'])->toBeNull();
});
