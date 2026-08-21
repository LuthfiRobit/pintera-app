<?php

namespace Database\Seeders;

use App\Domains\Pengadaan\Actions\CreatePengajuanAction;
use App\Domains\Pengadaan\Actions\RecordDisbursementAction;
use App\Domains\Pengadaan\Actions\SubmitLpjPengadaanAction;
use App\Domains\Pengadaan\Actions\SubmitPengajuanAction;
use App\Domains\Pengadaan\Actions\VerifyLpjAction;
use App\Domains\Pengadaan\DataTransferObjects\DisbursementData;
use App\Domains\Pengadaan\DataTransferObjects\LpjPengadaanData;
use App\Domains\Pengadaan\DataTransferObjects\PengajuanPengadaanData;
use App\Domains\Pengadaan\Enums\StatusItemPengajuan;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Enums\TingkatUrgensi;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Sarpras\Enums\JenisRuangan;
use App\Domains\Sarpras\Enums\KondisiAset;
use App\Domains\Sarpras\Enums\SumberPerolehanAset;
use App\Domains\Sarpras\Enums\TipePencatatanAset;
use App\Domains\Sarpras\Models\AsetBarang;
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\RiwayatMutasiAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Domains\Workflow\Models\WorkflowDefinition;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\Seeder;

class SarprasPengadaanDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn(static::class.': dilewati, hanya boleh jalan di environment local/testing.');

            return;
        }

        $this->command?->info('Menyiapkan Permissions, Roles, dan Workflow...');
        $this->callOnce([
            WorkflowDefinitionSeeder::class,
        ]);

        $yayasan = Yayasan::first();
        if (! $yayasan) {
            $yayasan = Yayasan::create(['nama' => 'Yayasan Pintera']);
        }

        $lembaga = Lembaga::where('npsn', '20223344')->first() ?? Lembaga::first();
        if (! $lembaga) {
            $lembaga = Lembaga::create([
                'yayasan_id' => $yayasan->id,
                'nama' => 'SMPIT PINTERA',
                'jenjang' => 'SMP',
                'npsn' => '20223344',
                'status_aktif' => true,
            ]);
        }

        // Setup User Accounts — role & permission sudah lengkap dari
        // RolePermissionAssignmentSeeder (lihat database/seeders/RolePermissionAssignmentSeeder.php),
        // file ini HANYA membuat/memakai akun, tidak lagi mengulang assignment role/permission.
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@demo.test'],
            ['name' => 'Admin Sistem', 'password' => 'password', 'is_active' => true]
        );

        $bendaharaYayasan = User::firstOrCreate(
            ['email' => 'keuangan.yayasan@demo.test'],
            ['name' => 'Farid', 'password' => 'password', 'is_active' => true]
        );
        $bendaharaYayasan->assignRole('bendahara_yayasan');

        $kepsek = User::firstOrCreate(
            ['email' => 'kepsek.smp@demo.test'],
            ['name' => 'Dr. H. Ahmad Dahlan (Kepala Sekolah)', 'password' => 'password', 'is_active' => true, 'lembaga_id' => $lembaga->id]
        );
        $kepsek->update(['lembaga_id' => $lembaga->id]);
        $kepsek->assignRole('kepala_sekolah');

        $adm = User::firstOrCreate(
            ['email' => 'adm.smp@demo.test'],
            ['name' => 'Admin Sarpras & Operasional', 'password' => 'password', 'is_active' => true, 'lembaga_id' => $lembaga->id]
        );
        $adm->update(['lembaga_id' => $lembaga->id]);
        $adm->assignRole('admin_administrasi');

        $operatorUser = $adm ?? $superAdmin;

        $this->command?->info('Menyiapkan Master Gedung, Ruangan, dan Kategori Aset...');

        // 1. Gedung
        $gedungUtama = Gedung::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_gedung' => 'GD-A'],
            ['nama_gedung' => 'Gedung Utama Al-Hikmah', 'jumlah_lantai' => 3, 'is_aktif' => true]
        );

        $gedungLab = Gedung::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_gedung' => 'GD-B'],
            ['nama_gedung' => 'Gedung Laboratorium & Multimedia', 'jumlah_lantai' => 2, 'is_aktif' => true]
        );

        $gedungFasilitas = Gedung::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_gedung' => 'GD-C'],
            ['nama_gedung' => 'Gedung Olahraga & Aula Pertemuan', 'jumlah_lantai' => 1, 'is_aktif' => true]
        );

        // 2. Ruangan
        $rKelasA = Ruangan::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_ruangan' => 'R-101'],
            [
                'gedung_id' => $gedungUtama->id,
                'nama_ruangan' => 'Ruang Kelas VII-A',
                'lantai' => 1,
                'jenis_ruangan' => JenisRuangan::KelasTeori,
                'kapasitas_siswa' => 36,
                'is_shared' => false,
                'is_aktif' => true,
            ]
        );

        $rKelasB = Ruangan::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_ruangan' => 'R-102'],
            [
                'gedung_id' => $gedungUtama->id,
                'nama_ruangan' => 'Ruang Kelas VII-B',
                'lantai' => 1,
                'jenis_ruangan' => JenisRuangan::KelasTeori,
                'kapasitas_siswa' => 36,
                'is_shared' => false,
                'is_aktif' => true,
            ]
        );

        $rLabKom = Ruangan::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_ruangan' => 'R-LABKOM'],
            [
                'gedung_id' => $gedungLab->id,
                'nama_ruangan' => 'Laboratorium Komputer & Bahasa',
                'lantai' => 2,
                'jenis_ruangan' => JenisRuangan::Laboratorium,
                'kapasitas_siswa' => 40,
                'is_shared' => true,
                'is_aktif' => true,
            ]
        );

        $rKantorGuru = Ruangan::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_ruangan' => 'R-GURU'],
            [
                'gedung_id' => $gedungUtama->id,
                'nama_ruangan' => 'Ruang Kantor Guru & Tata Usaha',
                'lantai' => 1,
                'jenis_ruangan' => JenisRuangan::KantorGuru,
                'kapasitas_siswa' => 25,
                'is_shared' => false,
                'is_aktif' => true,
            ]
        );

        $rAula = Ruangan::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_ruangan' => 'R-AULA'],
            [
                'gedung_id' => $gedungFasilitas->id,
                'nama_ruangan' => 'Aula Pertemuan Serbaguna',
                'lantai' => 1,
                'jenis_ruangan' => JenisRuangan::Aula,
                'kapasitas_siswa' => 200,
                'is_shared' => true,
                'is_aktif' => true,
            ]
        );

        // 3. Kategori Aset
        $katElk = KategoriAset::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_kategori' => 'ELK'],
            ['nama_kategori' => 'Elektronik & Perangkat IT', 'deskripsi' => 'Laptop, Komputer, Smart TV, Proyektor, Printer']
        );

        $katMeb = KategoriAset::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_kategori' => 'MEB'],
            ['nama_kategori' => 'Mebeler & Perabotan', 'deskripsi' => 'Meja, Kursi, Lemari, Rak Buku']
        );

        $katKbm = KategoriAset::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_kategori' => 'KBM'],
            ['nama_kategori' => 'Media & Sarana KBM', 'deskripsi' => 'Papan Tulis, Alat Peraga Sains, Peta']
        );

        $this->command?->info('Menyiapkan Master Aset Barang & Riwayat Mutasi...');

        // 4. Aset Barang (Master Eksisting)
        // Unit Assets: Laptop Lab
        for ($i = 1; $i <= 3; $i++) {
            AsetBarang::firstOrCreate(
                ['kode_inventaris' => "INV/2026/ELK/00{$i}"],
                [
                    'yayasan_id' => $yayasan->id,
                    'lembaga_id' => $lembaga->id,
                    'kategori_aset_id' => $katElk->id,
                    'ruangan_id' => $rLabKom->id,
                    'nama_barang' => "Laptop ASUS ExpertBook B1400 #{$i}",
                    'merk' => 'ASUS',
                    'spesifikasi' => "Intel Core i5-1135G7, RAM 16GB, SSD 512GB\nS/N: AS-EXP-202600{$i}",
                    'tipe_pencatatan' => TipePencatatanAset::Unit,
                    'qty' => 1,
                    'satuan' => 'unit',
                    'kondisi' => KondisiAset::Baik,
                    'sumber_perolehan' => SumberPerolehanAset::BeliYayasan,
                    'tanggal_perolehan' => '2026-01-15',
                    'harga_perolehan' => 9500000,
                ]
            );
        }

        // Unit Asset: Smart TV Ruang Guru
        AsetBarang::firstOrCreate(
            ['kode_inventaris' => 'INV/2026/ELK/004'],
            [
                'yayasan_id' => $yayasan->id,
                'lembaga_id' => $lembaga->id,
                'kategori_aset_id' => $katElk->id,
                'ruangan_id' => $rKantorGuru->id,
                'nama_barang' => 'Smart TV Samsung 65 Inch UHD 4K',
                'merk' => 'Samsung',
                'spesifikasi' => 'Crystal UHD 4K, Smart Hub, Garansi Resmi SEIN',
                'tipe_pencatatan' => TipePencatatanAset::Unit,
                'qty' => 1,
                'satuan' => 'unit',
                'kondisi' => KondisiAset::Baik,
                'sumber_perolehan' => SumberPerolehanAset::BeliYayasan,
                'tanggal_perolehan' => '2026-02-10',
                'harga_perolehan' => 12500000,
            ]
        );

        // Batch Asset: Meja & Kursi Siswa Kelas VII-A
        AsetBarang::firstOrCreate(
            ['kode_inventaris' => 'INV/2026/MEB/001'],
            [
                'yayasan_id' => $yayasan->id,
                'lembaga_id' => $lembaga->id,
                'kategori_aset_id' => $katMeb->id,
                'ruangan_id' => $rKelasA->id,
                'nama_barang' => 'Meja Belajar Siswa Kayu Jati',
                'merk' => 'Custom Mebel',
                'spesifikasi' => 'Kayu Jati Finishing Melamic, Laci Buku & Gantungan Tas',
                'tipe_pencatatan' => TipePencatatanAset::Batch,
                'qty' => 36,
                'satuan' => 'buah',
                'kondisi' => KondisiAset::Baik,
                'sumber_perolehan' => SumberPerolehanAset::BeliLembaga,
                'tanggal_perolehan' => '2026-01-05',
                'harga_perolehan' => 16200000, // 450rb x 36
            ]
        );

        AsetBarang::firstOrCreate(
            ['kode_inventaris' => 'INV/2026/MEB/002'],
            [
                'yayasan_id' => $yayasan->id,
                'lembaga_id' => $lembaga->id,
                'kategori_aset_id' => $katMeb->id,
                'ruangan_id' => $rKelasA->id,
                'nama_barang' => 'Kursi Belajar Siswa Kayu Jati',
                'merk' => 'Custom Mebel',
                'spesifikasi' => 'Kayu Jati Kokoh Ergonomis',
                'tipe_pencatatan' => TipePencatatanAset::Batch,
                'qty' => 36,
                'satuan' => 'buah',
                'kondisi' => KondisiAset::Baik,
                'sumber_perolehan' => SumberPerolehanAset::BeliLembaga,
                'tanggal_perolehan' => '2026-01-05',
                'harga_perolehan' => 9000000, // 250rb x 36
            ]
        );

        // Proyektor dengan Riwayat Mutasi
        $asetProyektor = AsetBarang::firstOrCreate(
            ['kode_inventaris' => 'INV/2026/ELK/005'],
            [
                'yayasan_id' => $yayasan->id,
                'lembaga_id' => $lembaga->id,
                'kategori_aset_id' => $katElk->id,
                'ruangan_id' => $rKelasB->id,
                'nama_barang' => 'Proyektor Epson EB-X500',
                'merk' => 'Epson',
                'spesifikasi' => '3600 Lumens XGA 3LCD',
                'tipe_pencatatan' => TipePencatatanAset::Unit,
                'qty' => 1,
                'satuan' => 'unit',
                'kondisi' => KondisiAset::Baik,
                'sumber_perolehan' => SumberPerolehanAset::BeliYayasan,
                'tanggal_perolehan' => '2026-01-20',
                'harga_perolehan' => 6200000,
            ]
        );

        RiwayatMutasiAset::firstOrCreate(
            ['aset_barang_id' => $asetProyektor->id, 'ruangan_tujuan_id' => $rKelasB->id],
            [
                'ruangan_asal_id' => $rAula->id,
                'qty_pindah' => 1,
                'tanggal_mutasi' => '2026-02-01',
                'alasan_mutasi' => 'Pemindahan tetap untuk kebutuhan presentasi digital harian Kelas VII-B.',
                'dilakukan_oleh_user_id' => $operatorUser->id,
            ]
        );

        $this->command?->info('Menyiapkan 1 Data Usulan Pengadaan Aktif (2 Item) Siap Review...');
        
        // 5. Proposal Pengadaan Aktif (1 Usulan dengan 2 Item Barang Siap Review Berjenjang)
        $proposalDemo = PengajuanPengadaan::where('nomor_pengajuan', 'PR/2026/08/DEMO-01')->first();
        if (! $proposalDemo) {
            $dto = new PengajuanPengadaanData(
                lembagaId: $lembaga->id,
                yayasanId: $yayasan->id,
                judulPengajuan: 'Pengadaan Laptop Lab Komputer & Kursi Belajar Siswa',
                latarBelakang: 'Kebutuhan mendesak perangkat lab multimedia dan perabotan ruang kelas VII.',
                tingkatUrgensi: TingkatUrgensi::Mendesak,
                items: [
                    [
                        'kategori_aset_id' => $katElk->id,
                        'target_ruangan_id' => $rLabKom->id,
                        'nama_barang' => 'Laptop ASUS ExpertBook B1400',
                        'merk' => 'ASUS',
                        'spesifikasi' => 'Intel Core i5-1135G7, RAM 16GB, SSD 512GB',
                        'qty' => 1,
                        'satuan' => 'unit',
                        'estimasi_harga_satuan' => 9500000,
                        'tipe_pencatatan' => 'unit',
                    ],
                    [
                        'kategori_aset_id' => $katMeb->id,
                        'target_ruangan_id' => $rKelasA->id,
                        'nama_barang' => 'Kursi Belajar Siswa Kayu Jati',
                        'merk' => 'Custom Mebel',
                        'spesifikasi' => 'Kayu Jati Kokoh Ergonomis',
                        'qty' => 10,
                        'satuan' => 'buah',
                        'estimasi_harga_satuan' => 250000,
                        'tipe_pencatatan' => 'batch',
                    ]
                ]
            );
            $p = app(CreatePengajuanAction::class)->execute($dto, $adm->id);
            $p->nomor_pengajuan = 'PR/2026/08/DEMO-01';
            $p->save();

            // Submit proposal sehingga langsung masuk Step 1 (Menunggu Verifikasi Kepala Sekolah)
            app(SubmitPengajuanAction::class)->execute($p);
        }

        $this->command?->info('Data Demo Sarpras & Pengadaan (1 Proposal, 2 Item Siap Review) berhasil disiapkan!');
    }
}
