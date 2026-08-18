<?php

namespace Tests\Unit\Domains\Pengadaan;

use App\Domains\Pengadaan\Actions\CreatePengajuanAction;
use App\Domains\Pengadaan\Actions\ProcessProposalApprovalAction;
use App\Domains\Pengadaan\Actions\RecordDisbursementAction;
use App\Domains\Pengadaan\Actions\SubmitPengajuanAction;
use App\Domains\Pengadaan\DataTransferObjects\DisbursementData;
use App\Domains\Pengadaan\DataTransferObjects\PengajuanPengadaanData;
use App\Domains\Pengadaan\Enums\StatusItemPengajuan;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Enums\TingkatUrgensi;
use App\Domains\Sarpras\Enums\JenisRuangan;
use App\Domains\Sarpras\Enums\TipePencatatanAset;
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\WorkflowDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PengajuanApprovalActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_partial_approval_and_disbursement_lifecycle(): void
    {
        $this->seed(WorkflowDefinitionSeeder::class);

        $roleKepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web']);
        $roleYayasan = Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web']);

        $yayasan = Yayasan::create(['nama' => 'Yayasan Bina Insan']);
        $lembaga = Lembaga::create([
            'yayasan_id' => $yayasan->id,
            'nama' => 'SMA IT',
            'jenjang' => 'SMA',
            'npsn' => '99999999',
            'status_aktif' => true,
        ]);

        $userPengaju = User::factory()->create(['lembaga_id' => $lembaga->id]);

        $userKepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $userKepsek->assignRole($roleKepsek);

        $userYayasan = User::factory()->create();
        $userYayasan->assignRole($roleYayasan);

        $gedung = Gedung::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'kode_gedung' => 'GD-A',
            'nama_gedung' => 'Gedung Utama',
            'jumlah_lantai' => 2,
        ]);

        $kategori = KategoriAset::create([
            'nama_kategori' => 'IT',
            'kode_kategori' => 'IT',
            'lembaga_id' => $lembaga->id,
            'yayasan_id' => $yayasan->id,
        ]);

        $ruangan = Ruangan::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'gedung_id' => $gedung->id,
            'kode_ruangan' => 'R-KLS',
            'nama_ruangan' => 'Ruang 10 SMA',
            'lantai' => 1,
            'jenis_ruangan' => JenisRuangan::KelasTeori,
        ]);

        $dto = new PengajuanPengadaanData(
            lembagaId: $lembaga->id,
            yayasanId: $yayasan->id,
            judulPengajuan: 'Beli 2 Item Sarpras',
            latarBelakang: 'Kebutuhan KBM',
            tingkatUrgensi: TingkatUrgensi::Mendesak,
            items: [
                [
                    'kategori_aset_id' => $kategori->id,
                    'target_ruangan_id' => $ruangan->id,
                    'nama_barang' => 'Proyektor Epson',
                    'qty' => 1,
                    'satuan' => 'unit',
                    'estimasi_harga_satuan' => 6000000,
                    'total_estimasi' => 6000000,
                    'tipe_pencatatan' => TipePencatatanAset::Unit->value,
                ],
                [
                    'kategori_aset_id' => $kategori->id,
                    'target_ruangan_id' => $ruangan->id,
                    'nama_barang' => 'Speaker Bluetooth',
                    'qty' => 1,
                    'satuan' => 'unit',
                    'estimasi_harga_satuan' => 1000000,
                    'total_estimasi' => 1000000,
                    'tipe_pencatatan' => TipePencatatanAset::Unit->value,
                ]
            ]
        );

        $proposal = app(CreatePengajuanAction::class)->execute($dto, $userPengaju->id);
        $this->assertEquals(StatusPengajuan::Draft, $proposal->status);
        $this->assertEquals(7000000, $proposal->total_estimasi);

        app(SubmitPengajuanAction::class)->execute($proposal);
        $proposal->refresh();
        $this->assertEquals(StatusPengajuan::Submitted, $proposal->status);
        $this->assertNotNull($proposal->approvalRequest);

        // Step 1: Kepsek approves all items
        app(ProcessProposalApprovalAction::class)->execute($proposal, $userKepsek, ApprovalAction::Approve, [], 'Lolos verifikasi internal');
        $proposal->refresh();
        $this->assertEquals(StatusPengajuan::InReview, $proposal->status);

        // Step 2: Yayasan approves Proyektor but rejects Speaker (Partial Approval)
        $itemProyektor = $proposal->items()->where('nama_barang', 'Proyektor Epson')->first();
        $itemSpeaker = $proposal->items()->where('nama_barang', 'Speaker Bluetooth')->first();

        $itemDecisions = [
            $itemProyektor->id => ['status' => StatusItemPengajuan::Approved->value, 'catatan' => 'ACC'],
            $itemSpeaker->id => ['status' => StatusItemPengajuan::Rejected->value, 'catatan' => 'Speaker masih ada di gudang'],
        ];

        app(ProcessProposalApprovalAction::class)->execute($proposal, $userYayasan, ApprovalAction::Approve, $itemDecisions, 'Disetujui sebagian');
        $proposal->refresh();
        $this->assertEquals(StatusPengajuan::Approved, $proposal->status);
        $this->assertEquals(6000000, $proposal->total_estimasi); // Adjusted to 6jt (only approved items)

        // Step 3: Yayasan disburse cash
        $disbursementData = new DisbursementData(
            nominalCair: 6000000,
            tanggalCair: now()->toDateString(),
            catatanPencairan: 'Transfer BSI',
            buktiTransferPath: 'transfers/bukti-cair.jpg'
        );

        app(RecordDisbursementAction::class)->execute($proposal, $disbursementData);
        $proposal->refresh();
        $this->assertEquals(StatusPengajuan::Disbursed, $proposal->status);
        $this->assertEquals(6000000, $proposal->nominal_pencairan);
    }
}
