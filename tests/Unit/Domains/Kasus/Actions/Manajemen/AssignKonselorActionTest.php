<?php

use App\Domains\Kasus\Actions\Manajemen\AssignKonselorAction;
use App\Domains\Kasus\DataTransferObjects\AssignKonselorData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusConsent;
use App\Domains\Kasus\Enums\StatusKasus;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('assigns a guru konselor, creates 2 consent rows, and sets status to menunggu_consent', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id, 'status' => StatusKasus::Diajukan]);

    $result = (new AssignKonselorAction)->execute($kasus, $siswa, new AssignKonselorData(
        tingkatUrgensi: 'sedang',
        konselorTipe: 'guru',
        konselorId: $guru->id,
    ));

    expect($result->status)->toBe(StatusKasus::MenungguConsent)
        ->and($result->konselor_guru_id)->toBe($guru->id)
        ->and($result->konselor_karyawan_id)->toBeNull()
        ->and(KasusConsent::where('kasus_id', $kasus->id)->count())->toBe(2);
});
