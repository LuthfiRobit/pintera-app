<?php

// tests/Feature/Keuangan/KategoriTagihanIsPpdbConsumersTest.php
//
// Task 4: regression coverage for the 5 `in_array($x->kategori, self::PPDB_KATEGORI, true)`
// sites fixed to `$x->kategori->isPpdb()`. Sites 1 (ProsesTagihan command) and 2
// (TagihanBillingGenerator::assertBillable) already have adequate PPDB-branch coverage in
// tests/Feature/Keuangan/ProsesTagihanCommandTest.php and
// tests/Feature/Keuangan/TagihanBillingGeneratorTest.php (both confirmed RED pre-fix, GREEN
// post-fix). This file adds coverage for the 2 sites that had no adequate existing coverage:
// - GenerateTagihanForActivatedBillType listener (site 3): no test exercised the listener in
//   isolation; the only indirect HTTP coverage in BillTypeActivatedEventTest is entangled with
//   an unrelated pre-existing issue in JenisTagihanController::update() (passing an enum
//   instance through a test PUT payload), so this listener is tested directly here instead.
// - JenisTagihanController::nominal() (site 4): only the non-PPDB rejection path was covered
//   (tests/Feature/Admin/JenisTagihanFinalReviewFixesTest.php, JenisTagihanTest.php); no test
//   asserted the PPDB *success* path actually renders the nominal view instead of redirecting.
// simpanNominal() (site 5) already has adequate PPDB-success coverage in
// tests/Feature/Admin/JenisTagihanFormTest.php and JenisTagihanTest.php (confirmed RED pre-fix).

use App\Domains\Keuangan\Events\BillTypeActivated;
use App\Domains\Keuangan\Listeners\GenerateTagihanForActivatedBillType;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Services\TagihanBillingGenerator;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('GenerateTagihanForActivatedBillType listener does not call the generator for a PPDB kategori', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'pendaftaran']);

    $generator = Mockery::mock(TagihanBillingGenerator::class);
    $generator->shouldNotReceive('generate');

    $listener = new GenerateTagihanForActivatedBillType($generator);
    $listener->handle(new BillTypeActivated($jenisTagihan));
});

it('GenerateTagihanForActivatedBillType listener calls the generator for a non-PPDB kategori', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'spp']);

    $generator = Mockery::mock(TagihanBillingGenerator::class);
    $generator->shouldReceive('generate')
        ->once()
        ->with($jenisTagihan, 'event', 'BillTypeActivated');

    $listener = new GenerateTagihanForActivatedBillType($generator);
    $listener->handle(new BillTypeActivated($jenisTagihan));
});

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('renders the nominal page for a PPDB-kategori jenis tagihan instead of redirecting', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.nominal', $jenisTagihan));

    $response->assertOk();
    $response->assertViewIs('portals.lembaga.keuangan.jenis-tagihan.nominal');
});
