<?php

use App\Domains\Akademik\Actions\Rapor\SubmitPengajuanRaporAction;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WorkflowDefinitionSeeder;
use Illuminate\Validation\ValidationException;

it('menolak submit ulang kalau pengajuan rapor sudah Disetujui', function () {
    $kelas = Kelas::factory()->create();
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $kelas->tahun_ajaran_id]);
    $siswa = Siswa::factory()->create(['kelas_id' => $kelas->id]);
    $user = User::factory()->create();

    CatatanWaliKelas::factory()->create(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]);

    $pengajuanRapor = PengajuanRapor::create([
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $kelas->lembaga_id,
        'status' => StatusPengajuanRapor::Disetujui,
        'diajukan_oleh' => $user->id,
        'diajukan_pada' => now(),
        'disetujui_oleh' => $user->id,
        'disetujui_pada' => now(),
    ]);

    expect(fn () => app(SubmitPengajuanRaporAction::class)->execute($kelas, $semester, $user))
        ->toThrow(ValidationException::class);

    $pengajuanRapor->refresh();
    expect($pengajuanRapor->status)->toBe(StatusPengajuanRapor::Disetujui);
});

it('menolak submit ulang kalau pengajuan rapor sedang Diverifikasi', function () {
    $kelas = Kelas::factory()->create();
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $kelas->tahun_ajaran_id]);
    $siswa = Siswa::factory()->create(['kelas_id' => $kelas->id]);
    $user = User::factory()->create();

    CatatanWaliKelas::factory()->create(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]);

    PengajuanRapor::create([
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $kelas->lembaga_id,
        'status' => StatusPengajuanRapor::Diverifikasi,
        'diajukan_oleh' => $user->id,
        'diajukan_pada' => now(),
    ]);

    expect(fn () => app(SubmitPengajuanRaporAction::class)->execute($kelas, $semester, $user))
        ->toThrow(ValidationException::class);
});

it('tetap boleh submit ulang kalau status Ditolak', function () {
    $this->seed([RolePermissionSeeder::class, WorkflowDefinitionSeeder::class]);

    $kelas = Kelas::factory()->create();
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $kelas->tahun_ajaran_id]);
    $siswa = Siswa::factory()->create(['kelas_id' => $kelas->id]);
    $user = User::factory()->create();

    CatatanWaliKelas::factory()->create(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]);

    PengajuanRapor::create([
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $kelas->lembaga_id,
        'status' => StatusPengajuanRapor::Ditolak,
        'diajukan_oleh' => $user->id,
        'diajukan_pada' => now(),
    ]);

    $result = app(SubmitPengajuanRaporAction::class)->execute($kelas, $semester, $user);

    expect($result->status)->toBe(StatusPengajuanRapor::Diajukan);
});
