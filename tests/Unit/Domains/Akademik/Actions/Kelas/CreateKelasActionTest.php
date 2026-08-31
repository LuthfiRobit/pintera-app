<?php

use App\Domains\Akademik\Actions\Kelas\CreateKelasAction;
use App\Domains\Akademik\DataTransferObjects\KelasData;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a kelas with minimal fields', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $tahunAjaran->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);
    $this->actingAs($user);

    $kelas = app(CreateKelasAction::class)->execute(new KelasData(
        tahunAjaranId: $tahunAjaran->id,
        nama: 'Kelas 1A',
        tingkat: '1',
        faseId: null,
        waliKelasGuruId: null,
        polaJamId: null,
    ));

    expect($kelas->fresh()->nama)->toBe('Kelas 1A');
    expect($kelas->fresh()->tahun_ajaran_id)->toBe($tahunAjaran->id);
    expect($kelas->fresh()->lembaga_id)->toBe($lembaga->id);
    expect($kelas->fresh()->kurikulum->value)->toBe('k13');
});

it('aborts with 404 when wali_kelas_guru_id belongs to a different lembaga than the tahun ajaran', function () {
    $lembagaA = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $lembagaB = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $tahunAjaran->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    $guruLain = Guru::factory()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaB->id])->id,
        'lembaga_id' => $lembagaB->id,
        'nik' => '3201234567899999',
        'nama' => 'Guru Lembaga Lain',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $execute = fn () => app(CreateKelasAction::class)->execute(new KelasData(
        tahunAjaranId: $tahunAjaran->id,
        nama: 'Kelas 1A',
        tingkat: '1',
        faseId: null,
        waliKelasGuruId: $guruLain->id,
        polaJamId: null,
    ));

    expect($execute)->toThrow(NotFoundHttpException::class);
});

it('overrides lembaga_id when provided (yayasan-scope create)', function () {
    $lembagaTarget = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaTarget->id]);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $tahunAjaran->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);

    $kelas = app(CreateKelasAction::class)->execute(new KelasData(
        tahunAjaranId: $tahunAjaran->id,
        nama: 'Kelas 1A',
        tingkat: '1',
        faseId: null,
        waliKelasGuruId: null,
        polaJamId: null,
    ), lembagaIdOverride: $lembagaTarget->id);

    expect($kelas->fresh()->lembaga_id)->toBe($lembagaTarget->id);
});
