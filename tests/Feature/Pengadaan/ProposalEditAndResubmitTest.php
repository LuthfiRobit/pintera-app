<?php

namespace Tests\Feature\Pengadaan;

use App\Domains\Pengadaan\Actions\ProcessProposalApprovalAction;
use App\Domains\Pengadaan\Actions\SubmitPengajuanAction;
use App\Domains\Pengadaan\Enums\StatusItemPengajuan;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Enums\TingkatUrgensi;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Pengadaan\Models\PengajuanPengadaanItem;
use App\Domains\Sarpras\Enums\JenisRuangan;
use App\Domains\Sarpras\Enums\TipePencatatanAset;
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\WorkflowDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalEditAndResubmitTest extends TestCase
{
    use RefreshDatabase;

    protected Yayasan $yayasan;
    protected Lembaga $lembaga;
    protected User $pengusul;
    protected User $kepsek;
    protected KategoriAset $kategori;
    protected Ruangan $ruangan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            WorkflowDefinitionSeeder::class,
        ]);

        $this->yayasan = Yayasan::create(['nama' => 'Yayasan Test']);
        $this->lembaga = Lembaga::create([
            'yayasan_id' => $this->yayasan->id,
            'nama' => 'SMP IT Test',
            'npsn' => '20223344',
            'status_aktif' => true,
        ]);

        // Pengusul (Admin Administrasi Lembaga)
        $this->pengusul = User::factory()->create(['lembaga_id' => $this->lembaga->id]);
        $admRole = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web']);
        $this->pengusul->assignRole($admRole);

        // Kepala Sekolah (Reviewer Step 1)
        $this->kepsek = User::factory()->create(['lembaga_id' => $this->lembaga->id]);
        $kepsekRole = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web']);
        $this->kepsek->assignRole($kepsekRole);

        $gedung = Gedung::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'kode_gedung' => 'GD-01',
            'nama_gedung' => 'Gedung Utama',
        ]);

        $this->ruangan = Ruangan::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'gedung_id' => $gedung->id,
            'kode_ruangan' => 'R-01',
            'nama_ruangan' => 'Ruang Lab Komputer',
            'lantai' => 1,
            'jenis_ruangan' => JenisRuangan::Laboratorium,
        ]);

        $this->kategori = KategoriAset::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'nama_kategori' => 'Elektronik',
            'kode_kategori' => 'ELK',
        ]);
    }

    public function test_full_revision_edit_and_resubmit_workflow(): void
    {
        // 1. Buat Proposal dan Submit
        $proposal = PengajuanPengadaan::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'nomor_pengajuan' => 'PR/2026/08/TEST-REV',
            'judul_pengajuan' => 'Pengadaan Laptop Guru',
            'latar_belakang' => 'Kebutuhan KBM',
            'tingkat_urgensi' => TingkatUrgensi::Biasa,
            'total_estimasi' => 10000000,
            'status' => StatusPengajuan::Draft,
            'created_by_user_id' => $this->pengusul->id,
        ]);

        $item1 = PengajuanPengadaanItem::create([
            'pengajuan_pengadaan_id' => $proposal->id,
            'kategori_aset_id' => $this->kategori->id,
            'target_ruangan_id' => $this->ruangan->id,
            'nama_barang' => 'Laptop ASUS Core i3',
            'qty' => 2,
            'satuan' => 'unit',
            'estimasi_harga_satuan' => 5000000,
            'total_estimasi' => 10000000,
            'tipe_pencatatan' => TipePencatatanAset::Unit,
            'status_item' => StatusItemPengajuan::Pending,
        ]);

        app(SubmitPengajuanAction::class)->execute($proposal);
        $this->assertEquals(StatusPengajuan::Submitted, $proposal->refresh()->status);

        // 2. Kepsek Mereview dan Meminta Revisi
        app(ProcessProposalApprovalAction::class)->execute(
            proposal: $proposal,
            user: $this->kepsek,
            action: ApprovalAction::RequestRevision,
            itemDecisions: [
                $item1->id => ['status' => 'rejected', 'catatan' => 'Spek Core i3 kurang memadai, mohon ganti Core i5'],
            ],
            notes: 'Mohon sesuaikan prosesor laptop menjadi Core i5 dan kurangi kuantitas menjadi 1 unit jika anggaran terbatas.'
        );

        $proposal->refresh();
        $this->assertEquals(StatusPengajuan::RevisionRequired, $proposal->status);
        $this->assertEquals(ApprovalStatus::RevisionRequired, $proposal->approvalRequest->status);

        // 3. Pengusul Membuka Halaman Edit Proposal
        $editResponse = $this->actingAs($this->pengusul)->get(route('admin.pengadaan.proposal.edit', $proposal));
        $editResponse->assertOk();
        $editResponse->assertSee('Perhatian: Usulan Ini Memerlukan Perbaikan', false);
        $editResponse->assertSee('Laptop ASUS Core i3', false);

        // 4. Pengusul Menyimpan Revisi dan Langsung Mengajukan Ulang (Resubmit)
        $updateResponse = $this->actingAs($this->pengusul)->put(route('admin.pengadaan.proposal.update', $proposal), [
            'judul_pengajuan' => 'Pengadaan Laptop Guru Core i5 (Revisi)',
            'tingkat_urgensi' => 'mendesak',
            'latar_belakang' => 'Disesuaikan dengan arahan Kepala Sekolah',
            'items' => [
                [
                    'id' => $item1->id,
                    'nama_barang' => 'Laptop ASUS Core i5',
                    'kategori_aset_id' => $this->kategori->id,
                    'target_ruangan_id' => $this->ruangan->id,
                    'merk' => 'ASUS',
                    'spesifikasi' => 'Core i5 RAM 16GB SSD 512GB',
                    'qty' => 1,
                    'satuan' => 'unit',
                    'estimasi_harga_satuan' => 8500000,
                    'tipe_pencatatan' => 'unit',
                ]
            ],
            'submit_immediately' => '1',
        ]);

        $updateResponse->assertRedirect(route('admin.pengadaan.proposal.show', $proposal));
        $updateResponse->assertSessionHas('success');

        // 5. Verifikasi Status Proposal & Workflow Berhasil Kembali ke Step 1
        $proposal->refresh();
        $this->assertEquals(StatusPengajuan::Submitted, $proposal->status);
        $this->assertEquals('Pengadaan Laptop Guru Core i5 (Revisi)', $proposal->judul_pengajuan);
        $this->assertEquals(8500000, $proposal->total_estimasi);
        $this->assertEquals(1, $proposal->items()->count());
        $this->assertEquals('Laptop ASUS Core i5', $proposal->items()->first()->nama_barang);
        $this->assertEquals(StatusItemPengajuan::Pending, $proposal->items()->first()->status_item);

        // Workflow step aktif kembali ke Step 1 (Kepala Sekolah)
        $approvalReq = $proposal->approvalRequest->refresh();
        $this->assertEquals(ApprovalStatus::Pending, $approvalReq->status);
        $this->assertEquals(1, $approvalReq->currentStep->step_number);
        $this->assertTrue($proposal->canBeApprovedBy($this->kepsek));

        // 6. Kepala Sekolah Menyetujui Proposal Hasil Revisi
        app(ProcessProposalApprovalAction::class)->execute(
            proposal: $proposal,
            user: $this->kepsek,
            action: ApprovalAction::Approve,
            notes: 'Spesifikasi Core i5 sudah sesuai, usulan disetujui.'
        );

        $proposal->refresh();
        $this->assertEquals(StatusPengajuan::InReview, $proposal->status);
        $this->assertEquals(2, $proposal->approvalRequest->currentStep->step_number); // Lanjut ke Step 2 Yayasan
    }

    public function test_partial_item_revision_preserves_approved_item(): void
    {
        $proposal = PengajuanPengadaan::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'nomor_pengajuan' => 'PR/2026/08/TEST-PARTIAL',
            'judul_pengajuan' => 'Pengadaan 2 Macam Barang',
            'tingkat_urgensi' => TingkatUrgensi::Biasa,
            'total_estimasi' => 15000000,
            'status' => StatusPengajuan::Draft,
            'created_by_user_id' => $this->pengusul->id,
        ]);

        $item1 = PengajuanPengadaanItem::create([
            'pengajuan_pengadaan_id' => $proposal->id,
            'kategori_aset_id' => $this->kategori->id,
            'target_ruangan_id' => $this->ruangan->id,
            'nama_barang' => 'Laptop ASUS (Valid)',
            'qty' => 1,
            'satuan' => 'unit',
            'estimasi_harga_satuan' => 10000000,
            'total_estimasi' => 10000000,
            'tipe_pencatatan' => TipePencatatanAset::Unit,
            'status_item' => StatusItemPengajuan::Pending,
        ]);

        $item2 = PengajuanPengadaanItem::create([
            'pengajuan_pengadaan_id' => $proposal->id,
            'kategori_aset_id' => $this->kategori->id,
            'target_ruangan_id' => $this->ruangan->id,
            'nama_barang' => 'Kursi Kayu (Kemahalan)',
            'qty' => 10,
            'satuan' => 'buah',
            'estimasi_harga_satuan' => 500000,
            'total_estimasi' => 5000000,
            'tipe_pencatatan' => TipePencatatanAset::Batch,
            'status_item' => StatusItemPengajuan::Pending,
        ]);

        app(SubmitPengajuanAction::class)->execute($proposal);
        $proposal->refresh();

        // Kepsek Approve Item 1, tapi Reject Item 2
        app(ProcessProposalApprovalAction::class)->execute(
            proposal: $proposal,
            user: $this->kepsek,
            action: ApprovalAction::RequestRevision,
            itemDecisions: [
                $item1->id => ['status' => 'approved', 'catatan' => 'OK disetujui'],
                $item2->id => ['status' => 'rejected', 'catatan' => 'Harga kursi terlalu mahal, harap cari vendor lain max 250rb'],
            ],
            notes: 'Laptop disetujui, tolong revisi harga kursi.'
        );

        $proposal->refresh();
        $this->assertEquals(StatusPengajuan::RevisionRequired, $proposal->status);
        $this->assertEquals(StatusItemPengajuan::Approved, $item1->refresh()->status_item);
        $this->assertEquals(StatusItemPengajuan::Rejected, $item2->refresh()->status_item);

        // Pengusul edit proposal: sesuaikan harga item 2
        $this->actingAs($this->pengusul)->put(route('admin.pengadaan.proposal.update', $proposal), [
            'judul_pengajuan' => 'Pengadaan 2 Macam Barang (Revisi Kursi)',
            'tingkat_urgensi' => 'biasa',
            'items' => [
                [
                    'id' => $item1->id,
                    'nama_barang' => 'Laptop ASUS (Valid)',
                    'kategori_aset_id' => $this->kategori->id,
                    'target_ruangan_id' => $this->ruangan->id,
                    'qty' => 1,
                    'satuan' => 'unit',
                    'estimasi_harga_satuan' => 10000000,
                    'tipe_pencatatan' => 'unit',
                ],
                [
                    'id' => $item2->id,
                    'nama_barang' => 'Kursi Kayu (Harga Disesuaikan)',
                    'kategori_aset_id' => $this->kategori->id,
                    'target_ruangan_id' => $this->ruangan->id,
                    'qty' => 10,
                    'satuan' => 'buah',
                    'estimasi_harga_satuan' => 250000,
                    'tipe_pencatatan' => 'batch',
                ]
            ],
            'submit_immediately' => '1',
        ]);

        $proposal->refresh();
        $this->assertEquals(StatusPengajuan::Submitted, $proposal->status);
        $this->assertEquals(12500000, $proposal->total_estimasi); // 10jt + 2.5jt
        // Item 1 tetap Approved, Item 2 menjadi Pending
        $this->assertEquals(StatusItemPengajuan::Approved, $proposal->items()->where('id', $item1->id)->first()->status_item);
        $this->assertEquals(StatusItemPengajuan::Pending, $proposal->items()->where('id', $item2->id)->first()->status_item);
    }
}
