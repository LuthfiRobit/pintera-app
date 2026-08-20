<?php

use App\Domains\Kasus\Actions\Consent\ApproveConsentAction;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusConsent;
use App\Domains\Kasus\Enums\StatusKasus;
use App\Models\Lembaga;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('approving the sesi_pendampingan consent moves kasus status to ditugaskan', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id, 'status' => StatusKasus::MenungguConsent]);
    $consent = KasusConsent::factory()->create(['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan', 'status' => 'menunggu']);

    (new ApproveConsentAction)->execute($kasus, $consent);

    expect($kasus->fresh()->status)->toBe(StatusKasus::Ditugaskan)
        ->and($consent->fresh()->status)->toBe('disetujui')
        ->and($consent->fresh()->disetujui_at)->not->toBeNull();
});

it('approving the pengumpulan_media consent does not change kasus status', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id, 'status' => StatusKasus::MenungguConsent]);
    $consent = KasusConsent::factory()->create(['kasus_id' => $kasus->id, 'jenis' => 'pengumpulan_media', 'status' => 'menunggu']);

    (new ApproveConsentAction)->execute($kasus, $consent);

    expect($kasus->fresh()->status)->toBe(StatusKasus::MenungguConsent)
        ->and($consent->fresh()->status)->toBe('disetujui');
});
