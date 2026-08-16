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
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class SarprasPengadaanDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Menyiapkan Permissions, Roles, dan Workflow...');
        $this->call([
            SarprasPermissionSeeder::class,
            PengadaanPermissionSeeder::class,
            WorkflowDefinitionSeeder::class,
        ]);

        $yayasan = Yayasan::first();
        if (! $yayasan) {
            $yayasan = Yayasan::create(['nama' => 'Yayasan Pendidikan Islam Permata']);
        }

        $lembaga = Lembaga::first();
        if (! $lembaga) {
            $lembaga = Lembaga::create([
                'yayasan_id' => $yayasan->id,
                'nama' => 'SMP IT Permata Kraksaan',
                'jenjang' => 'SMP',
                'npsn' => '20223399',
                'status_aktif' => true,
            ]);
        }

        // Setup User Accounts & Permissions
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@sistem.test'],
            ['name' => 'Admin Sistem', 'password' => 'password', 'is_active' => true]
        );
        $superAdmin->givePermissionTo(Permission::all());

        $bendaharaYayasanRole = Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $bendaharaYayasanRole->givePermissionTo([
            'pengadaan.proposal.view',
            'pengadaan.approval.yayasan',
            'pengadaan.disbursement.manage',
            'pengadaan.lpj.verify',
            'sarpras.aset.view',
        ]);

        $bendaharaYayasan = User::firstOrCreate(
            ['email' => 'bendahara.yayasan@sistem.test'],
            ['name' => 'Ustadz Farid (Bendahara Yayasan)', 'password' => 'password', 'is_active' => true]
        );
        $bendaharaYayasan->assignRole($bendaharaYayasanRole);

        $kepsek = User::where('email', 'kepsek@sistem.test')->first();
        if ($kepsek) {
            $kepsek->givePermissionTo([
                'sarpras.gedung.view', 'sarpras.ruangan.view', 'sarpras.kategori.view', 'sarpras.aset.view', 'sarpras.mutasi.view',
                'pengadaan.proposal.view', 'pengadaan.approval.internal',
            ]);
        }

        $adm = User::where('email', 'adm@sistem.test')->first();
        if ($adm) {
            $adm->givePermissionTo([
                'sarpras.gedung.view', 'sarpras.gedung.manage',
                'sarpras.ruangan.view', 'sarpras.ruangan.manage',
                'sarpras.kategori.view', 'sarpras.kategori.manage',
                'sarpras.aset.view', 'sarpras.aset.manage',
                'sarpras.mutasi.create', 'sarpras.mutasi.view', 'sarpras.kir.export',
                'pengadaan.proposal.create', 'pengadaan.proposal.view', 'pengadaan.proposal.edit', 'pengadaan.proposal.delete',
                'pengadaan.lpj.submit',
            ]);
        }

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

        $this->command?->info('Menyiapkan Variasi Siklus Hidup Pengadaan & LPJ...');

        // 5. Proposal Pengadaan dalam Berbagai Tahapan untuk Pengujian Komprehensif
        // Case 1: Status Draft (Usulan baru sedang disusun di sekolah)
        if (! PengajuanPengadaan::where('nomor_pengajuan', 'PR/2026/08/DEMO-01')->exists()) {
            $dto1 = new PengajuanPengadaanData(
                lembagaId: $lembaga->id,
                yayasanId: $yayasan->id,
                judulPengajuan: 'Pengadaan Sound System Portable & 2 Mic Wireless Aula',
                latarBelakang: 'Kebutuhan kegiatan apel pagi santri dan kajian bulanan di Aula.',
                tingkatUrgensi: TingkatUrgensi::Biasa,
                items: [
                    [
                        'kategori_aset_id' => $katElk->id,
                        'target_ruangan_id' => $rAula->id,
                        'nama_barang' => 'Portable Wireless Amplifier Speaker 12 Inch',
                        'merk' => 'Baretone',
                        'spesifikasi' => 'Output 300W, Bluetooth, USB Player, Battery Powered',
                        'qty' => 1,
                        'satuan' => 'unit',
                        'estimasi_harga_satuan' => 3800000,
                        'tipe_pencatatan' => 'unit',
                    ],
                    [
                        'kategori_aset_id' => $katElk->id,
                        'target_ruangan_id' => $rAula->id,
                        'nama_barang' => 'Microphone Wireless Dual Channel UHF',
                        'merk' => 'Shure',
                        'spesifikasi' => 'Dual Handheld Mic, Jangkauan 50m Anti-Interference',
                        'qty' => 1,
                        'satuan' => 'set',
                        'estimasi_harga_satuan' => 1200000,
                        'tipe_pencatatan' => 'unit',
                    ]
                ]
            );
            $p1 = app(CreatePengajuanAction::class)->execute($dto1, $operatorUser->id);
            $p1->nomor_pengajuan = 'PR/2026/08/DEMO-01';
            $p1->save();
        }

        // Case 2: Status Submitted / In Review (Menunggu persetujuan Yayasan)
        if (! PengajuanPengadaan::where('nomor_pengajuan', 'PR/2026/08/DEMO-02')->exists()) {
            $dto2 = new PengajuanPengadaanData(
                lembagaId: $lembaga->id,
                yayasanId: $yayasan->id,
                judulPengajuan: 'Pengadaan 5 Unit PC All-in-One Lab Multimedia',
                latarBelakang: 'Penambahan komputer server dan client untuk asesmen ANBK & lab coding.',
                tingkatUrgensi: TingkatUrgensi::Mendesak,
                items: [
                    [
                        'kategori_aset_id' => $katElk->id,
                        'target_ruangan_id' => $rLabKom->id,
                        'nama_barang' => 'PC All-in-One Lenovo IdeaCentre AIO 3',
                        'merk' => 'Lenovo',
                        'spesifikasi' => 'Core i5-12450H, RAM 16GB, SSD 512GB, 23.8 Inch IPS FHD',
                        'qty' => 5,
                        'satuan' => 'unit',
                        'estimasi_harga_satuan' => 8800000,
                        'tipe_pencatatan' => 'unit',
                    ]
                ]
            );
            $p2 = app(CreatePengajuanAction::class)->execute($dto2, $operatorUser->id);
            $p2->nomor_pengajuan = 'PR/2026/08/DEMO-02';
            $p2->save();
            app(SubmitPengajuanAction::class)->execute($p2);
        }

        // Case 3: Status Approved (Disetujui Yayasan, siap dicairkan kasir)
        if (! PengajuanPengadaan::where('nomor_pengajuan', 'PR/2026/08/DEMO-03')->exists()) {
            $dto3 = new PengajuanPengadaanData(
                lembagaId: $lembaga->id,
                yayasanId: $yayasan->id,
                judulPengajuan: 'Pengadaan 2 Unit Lemari Arsip Besi 4 Pintu Kantor Guru',
                latarBelakang: 'Penyimpanan berkas akreditasi dan raport fisik santri.',
                tingkatUrgensi: TingkatUrgensi::Biasa,
                items: [
                    [
                        'kategori_aset_id' => $katMeb->id,
                        'target_ruangan_id' => $rKantorGuru->id,
                        'nama_barang' => 'Lemari Arsip Besi Sliding Door',
                        'merk' => 'VIP Steel',
                        'spesifikasi' => 'Plat Baja 0.7mm, Kunci Central Lock, Anti Karat',
                        'qty' => 2,
                        'satuan' => 'unit',
                        'estimasi_harga_satuan' => 2750000,
                        'tipe_pencatatan' => 'unit',
                    ]
                ]
            );
            $p3 = app(CreatePengajuanAction::class)->execute($dto3, $operatorUser->id);
            $p3->nomor_pengajuan = 'PR/2026/08/DEMO-03';
            $p3->status = StatusPengajuan::Approved;
            $p3->save();
            foreach ($p3->items as $it) {
                $it->status_item = StatusItemPengajuan::Approved;
                $it->save();
            }
        }

        // Case 4: Status Disbursed (Dana Cair Rp 9.000.000, siap diunggah LPJ oleh sekolah)
        if (! PengajuanPengadaan::where('nomor_pengajuan', 'PR/2026/08/DEMO-04')->exists()) {
            $dto4 = new PengajuanPengadaanData(
                lembagaId: $lembaga->id,
                yayasanId: $yayasan->id,
                judulPengajuan: 'Pengadaan 2 Unit AC Split 1.5 PK Ruang Laboratorium',
                latarBelakang: 'Pendingin ruangan server & lab komputer agar perangkat tidak overheat.',
                tingkatUrgensi: TingkatUrgensi::Mendesak,
                items: [
                    [
                        'kategori_aset_id' => $katElk->id,
                        'target_ruangan_id' => $rLabKom->id,
                        'nama_barang' => 'AC Split Daikin Flash Inverter 1.5 PK',
                        'merk' => 'Daikin',
                        'spesifikasi' => 'FTKQ35UVM4 + Pemasangan & Pipa Tembaga 5m',
                        'qty' => 2,
                        'satuan' => 'unit',
                        'estimasi_harga_satuan' => 4500000,
                        'tipe_pencatatan' => 'unit',
                    ]
                ]
            );
            $p4 = app(CreatePengajuanAction::class)->execute($dto4, $operatorUser->id);
            $p4->nomor_pengajuan = 'PR/2026/08/DEMO-04';
            $p4->status = StatusPengajuan::Approved;
            $p4->save();
            foreach ($p4->items as $it) {
                $it->status_item = StatusItemPengajuan::Approved;
                $it->save();
            }

            $disburseDto = new DisbursementData(
                nominalCair: 9000000,
                tanggalCair: '2026-08-10',
                catatanPencairan: 'Transfer BSI Kas Yayasan No. Trx #TRX-20260810-099'
            );
            app(RecordDisbursementAction::class)->execute($p4, $disburseDto);
        }

        // Case 5: Status Completed (LPJ selesai diverifikasi, siap uji coba staging konversi aset)
        if (! PengajuanPengadaan::where('nomor_pengajuan', 'PR/2026/08/DEMO-05')->exists()) {
            $dto5 = new PengajuanPengadaanData(
                lembagaId: $lembaga->id,
                yayasanId: $yayasan->id,
                judulPengajuan: 'Pengadaan 2 Unit Printer Epson L3210 & 20 Rim Kertas HVS',
                latarBelakang: 'Kebutuhan cetak lembar kerja siswa dan administrasi ujian.',
                tingkatUrgensi: TingkatUrgensi::Biasa,
                items: [
                    [
                        'kategori_aset_id' => $katElk->id,
                        'target_ruangan_id' => $rKantorGuru->id,
                        'nama_barang' => 'Printer Epson EcoTank L3210 All-in-One',
                        'merk' => 'Epson',
                        'spesifikasi' => 'Print, Scan, Copy, Tinta Original 4 Warna',
                        'qty' => 2,
                        'satuan' => 'unit',
                        'estimasi_harga_satuan' => 2300000,
                        'tipe_pencatatan' => 'unit',
                    ],
                    [
                        'kategori_aset_id' => $katKbm->id,
                        'target_ruangan_id' => $rKantorGuru->id,
                        'nama_barang' => 'Kertas HVS PaperOne A4 75gsm',
                        'merk' => 'PaperOne',
                        'spesifikasi' => 'Ukuran A4 210x297mm 75 gram, 500 lembar per rim',
                        'qty' => 20,
                        'satuan' => 'rim',
                        'estimasi_harga_satuan' => 48000,
                        'tipe_pencatatan' => 'batch',
                    ]
                ]
            );
            $p5 = app(CreatePengajuanAction::class)->execute($dto5, $operatorUser->id);
            $p5->nomor_pengajuan = 'PR/2026/08/DEMO-05';
            $p5->status = StatusPengajuan::Approved;
            $p5->save();
            foreach ($p5->items as $it) {
                $it->status_item = StatusItemPengajuan::Approved;
                $it->save();
            }

            $disburseDto5 = new DisbursementData(
                nominalCair: 5560000, // 4.6jt + 960rb
                tanggalCair: '2026-08-05',
                catatanPencairan: 'Pencairan Kas Operasional Yayasan'
            );
            app(RecordDisbursementAction::class)->execute($p5, $disburseDto5);

            $pItem1 = $p5->items()->where('tipe_pencatatan', TipePencatatanAset::Unit)->first();
            $pItem2 = $p5->items()->where('tipe_pencatatan', TipePencatatanAset::Batch)->first();

            $lpjData = new LpjPengadaanData(
                items: [
                    [
                        'pengajuan_item_id' => $pItem1->id,
                        'harga_satuan_riil' => 2250000,
                        'total_riil' => 4500000,
                    ],
                    [
                        'pengajuan_item_id' => $pItem2->id,
                        'harga_satuan_riil' => 47000,
                        'total_riil' => 940000,
                    ]
                ],
                buktiKembaliSisaDanaPath: null
            );

            $lpj5 = app(SubmitLpjPengadaanAction::class)->execute($p5, $lpjData);
            app(VerifyLpjAction::class)->execute($lpj5, $bendaharaYayasan->id, true, 'Nota Asli Gramedia & Faktur Valid. Sisa kas Rp 120.000 sudah dikembalikan ke kasir yayasan.');
        }

        $this->command?->info('Data Demo Sarpras & Pengadaan berhasil disiapkan dengan aman!');
    }
}
