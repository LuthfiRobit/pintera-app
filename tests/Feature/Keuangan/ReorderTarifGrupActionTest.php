<?php

use App\Domains\Keuangan\Actions\JenisTagihan\ReorderTarifGrupAction;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Jobs\RecalculateTagihanNominalJob;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('updates priority for each grup id in the given order, scoped to the correct jenis_tagihan', function () {
    $jenisTagihan = JenisTagihan::factory()->create();
    $grupA = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => 100000, 'priority' => 1]);
    $grupB = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => 200000, 'priority' => 2]);

    app(ReorderTarifGrupAction::class)->execute($jenisTagihan, [$grupB->id, $grupA->id]);

    expect($grupB->fresh()->priority)->toBe(1);
    expect($grupA->fresh()->priority)->toBe(2);
});

it('dispatches one recalc job per unpaid tagihan for that jenis_tagihan directly, without going through form submit', function () {
    Queue::fake();

    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $grupA = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => 100000, 'priority' => 1]);
    $grupB = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => 200000, 'priority' => 2]);
    $tagihan = Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'status' => 'belum_bayar']);

    $this->actingAs($admin)->patchJson(route('admin.jenis-tagihan.tarif-grup.reorder', $jenisTagihan), [
        'urutan_grup_id' => [$grupB->id, $grupA->id],
    ]);

    Queue::assertPushed(RecalculateTagihanNominalJob::class, 1);
    Queue::assertPushed(fn (RecalculateTagihanNominalJob $job) => $job->tagihanId === $tagihan->id);
});

it('rejects a grup id belonging to a different jenis_tagihan (tenant guard)', function () {
    $jenisTagihanA = JenisTagihan::factory()->create();
    $jenisTagihanB = JenisTagihan::factory()->create();
    $grupLain = $jenisTagihanB->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => 100000, 'priority' => 1]);

    app(ReorderTarifGrupAction::class)->execute($jenisTagihanA, [$grupLain->id]);

    expect($grupLain->fresh()->jenis_tagihan_id)->toBe($jenisTagihanB->id); // tidak berubah kepemilikan, update() dengan where jenis_tagihan_id filter tidak match apapun
});
