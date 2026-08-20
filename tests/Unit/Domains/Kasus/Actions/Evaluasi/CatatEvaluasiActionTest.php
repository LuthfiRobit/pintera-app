<?php

use App\Domains\Kasus\Actions\Evaluasi\CatatEvaluasiAction;
use App\Domains\Kasus\DataTransferObjects\CatatEvaluasiData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusEvaluasi;
use App\Domains\Kasus\Enums\StatusKasus;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('records an evaluasi row and transitions kasus status to selesai', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id, 'status' => StatusKasus::Berjalan]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    (new CatatEvaluasiAction)->execute(
        $kasus,
        new CatatEvaluasiData(catatan: 'Siswa sudah stabil.', keputusan: 'selesai', dibuatOlehUserId: $user->id),
        newStatus: 'selesai',
        originalStatus: 'berjalan',
    );

    expect($kasus->fresh()->status)->toBe(StatusKasus::Selesai)
        ->and(KasusEvaluasi::where('kasus_id', $kasus->id)->count())->toBe(1);
});

it('does not update kasus status when newStatus equals current status', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id, 'status' => StatusKasus::Berjalan]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    (new CatatEvaluasiAction)->execute(
        $kasus,
        new CatatEvaluasiData(catatan: 'Masih perlu lanjut.', keputusan: 'lanjut', dibuatOlehUserId: $user->id),
        newStatus: 'berjalan',
        originalStatus: 'berjalan',
    );

    expect($kasus->fresh()->status)->toBe(StatusKasus::Berjalan);
});
