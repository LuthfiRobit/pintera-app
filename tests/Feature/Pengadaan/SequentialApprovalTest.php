<?php

namespace Tests\Feature\Pengadaan;

use App\Domains\Pengadaan\Actions\CreatePengajuanAction;
use App\Domains\Pengadaan\Actions\SubmitPengajuanAction;
use App\Domains\Pengadaan\DataTransferObjects\PengajuanPengadaanData;
use App\Domains\Pengadaan\Enums\StatusItemPengajuan;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Enums\TingkatUrgensi;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionAssignmentSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SequentialApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_sequential_approval_from_kepsek_to_bendahara_yayasan(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionAssignmentSeeder::class,
            WorkflowDefinitionSeeder::class,
        ]);

        $yayasan = Yayasan::create(['nama' => 'Yayasan Pendidikan Islam']);
        $lembaga = Lembaga::create([
            'yayasan_id' => $yayasan->id,
            'nama' => 'SMP IT Permata',
            'jenjang' => 'SMP',
            'npsn' => '12345678',
            'status_aktif' => true,
        ]);

        // 1. Roles & Users
        $kepsekRole = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $kepsek->assignRole($kepsekRole);
        $kepsek->givePermissionTo(['pengadaan.proposal.view', 'pengadaan.approval.internal']);

        $bendaharaRole = Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $bendahara = User::factory()->create(['lembaga_id' => null]);
        $bendahara->assignRole($bendaharaRole);
        $bendahara->givePermissionTo(['pengadaan.proposal.view', 'pengadaan.approval.yayasan', 'pengadaan.disbursement.manage']);

        $adm = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $adm->givePermissionTo(['pengadaan.proposal.create', 'pengadaan.proposal.view', 'pengadaan.proposal.edit']);

        // 2. Create proposal with 2 items and submit
        $dto = new PengajuanPengadaanData(
            lembagaId: $lembaga->id,
            yayasanId: $yayasan->id,
            judulPengajuan: 'Pengadaan Laptop dan Kursi',
            latarBelakang: 'Kebutuhan KBM baru',
            tingkatUrgensi: TingkatUrgensi::Mendesak,
            items: [
                [
                    'kategori_aset_id' => null,
                    'target_ruangan_id' => null,
                    'nama_barang' => 'Laptop ASUS',
                    'merk' => 'ASUS',
                    'spesifikasi' => 'Core i5',
                    'qty' => 1,
                    'satuan' => 'unit',
                    'estimasi_harga_satuan' => 9000000,
                    'tipe_pencatatan' => 'unit',
                ],
                [
                    'kategori_aset_id' => null,
                    'target_ruangan_id' => null,
                    'nama_barang' => 'Kursi Kayu',
                    'merk' => 'Custom',
                    'spesifikasi' => 'Jati',
                    'qty' => 10,
                    'satuan' => 'buah',
                    'estimasi_harga_satuan' => 200000,
                    'tipe_pencatatan' => 'batch',
                ]
            ]
        );

        $proposal = app(CreatePengajuanAction::class)->execute($dto, $adm->id);
        app(SubmitPengajuanAction::class)->execute($proposal);
        $proposal->refresh();

        $this->assertEquals(StatusPengajuan::Submitted, $proposal->status);
        $this->assertTrue($proposal->canBeApprovedBy($kepsek));
        $this->assertFalse($proposal->canBeApprovedBy($bendahara));

        // 3. Kepsek views detail page and sees review button
        $respKepsek = $this->actingAs($kepsek)->get(route('admin.pengadaan.proposal.show', $proposal));
        $respKepsek->assertOk();
        $respKepsek->assertSee(route('admin.pengadaan.inbox.review', $proposal));
        $respKepsek->assertSeeText('Proses Persetujuan Sekarang');

        // 4. Kepsek accesses review page and approves Step 1
        $respReview = $this->actingAs($kepsek)->get(route('admin.pengadaan.inbox.review', $proposal));
        $respReview->assertOk();

        $items = $proposal->items;
        $decisionData = [
            'action' => ApprovalAction::Approve->value,
            'notes' => 'Disetujui Kepala Sekolah, teruskan ke Yayasan.',
            'item_decisions' => [
                [
                    'item_id' => $items[0]->id,
                    'status' => StatusItemPengajuan::Approved->value,
                    'catatan' => 'ACC',
                ],
                [
                    'item_id' => $items[1]->id,
                    'status' => StatusItemPengajuan::Approved->value,
                    'catatan' => 'ACC',
                ]
            ]
        ];

        $respKepsekDecision = $this->actingAs($kepsek)->post(route('admin.pengadaan.inbox.decision', $proposal), $decisionData);
        $respKepsekDecision->assertRedirect(route('admin.pengadaan.proposal.show', $proposal));

        $proposal->refresh();
        $this->assertEquals(StatusPengajuan::InReview, $proposal->status);
        $this->assertFalse($proposal->canBeApprovedBy($kepsek));
        $this->assertTrue($proposal->canBeApprovedBy($bendahara));

        // 5. Bendahara Yayasan views detail page and sees review button for Step 2
        $respBendahara = $this->actingAs($bendahara)->get(route('admin.pengadaan.proposal.show', $proposal));
        $respBendahara->assertOk();
        $respBendahara->assertSee(route('admin.pengadaan.inbox.review', $proposal));
        $respBendahara->assertSeeText('Proses Persetujuan Sekarang');

        // 6. Bendahara Yayasan submits final approval for Step 2
        $decisionDataYayasan = [
            'action' => ApprovalAction::Approve->value,
            'notes' => 'Disetujui Yayasan, siap proses pencairan kas.',
            'item_decisions' => [
                [
                    'item_id' => $items[0]->id,
                    'status' => StatusItemPengajuan::Approved->value,
                    'catatan' => 'Anggaran disetujui penuh',
                ],
                [
                    'item_id' => $items[1]->id,
                    'status' => StatusItemPengajuan::Approved->value,
                    'catatan' => 'Anggaran disetujui penuh',
                ]
            ]
        ];

        $respBendaharaDecision = $this->actingAs($bendahara)->post(route('admin.pengadaan.inbox.decision', $proposal), $decisionDataYayasan);
        $respBendaharaDecision->assertRedirect(route('admin.pengadaan.inbox.index'));

        $proposal->refresh();
        $this->assertEquals(StatusPengajuan::Approved, $proposal->status);
    }
}
