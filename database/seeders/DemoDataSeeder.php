<?php

namespace Database\Seeders;

use App\Models\DokumenSyaratPpdb;
use App\Models\EkstrakurikulerLembaga;
use App\Models\FormulirField;
use App\Models\GelombangPpdb;
use App\Models\Guru;
use App\Models\GuruJabatanTambahan;
use App\Models\JabatanTambahanMaster;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use App\Models\LembagaDataPeriodik;
use App\Models\LayananKhususLembaga;
use App\Models\ProgramInklusiLembaga;
use App\Models\RiwayatPendidikanGuru;
use App\Models\SeleksiPpdb;
use App\Models\Semester;
use App\Models\SertifikasiGuru;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\Seeder;

/**
 * Data demo untuk manual testing M0 (fondasi) & M1 (SPMB Konfigurasi):
 * 1 yayasan menaungi 2 lembaga (SMP & SMA), masing-masing dengan staf,
 * guru lengkap, tahun ajaran, dan konfigurasi PPDB.
 *
 * SMP: tahun ajaran 2025/2026 (nonaktif) sudah penuh terisi konfigurasi PPDB —
 * untuk menguji fitur duplikasi ke 2026/2027 (aktif). Tahun ajaran aktif JUGA
 * diisi konfigurasi PPDB sendiri (gelombang bertanggal relatif ke hari ini),
 * supaya wizard SPMB publik langsung bisa diuji untuk SMP tanpa perlu
 * menjalankan fitur duplikasi terlebih dahulu.
 * SMA: tahun ajaran 2026/2027 (aktif) langsung terisi penuh, gelombang juga
 * bertanggal relatif ke hari ini — untuk menguji halaman dossier Jalur dan
 * wizard SPMB publik tanpa perlu duplikasi dulu.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $yayasan = Yayasan::firstOrFail();

        $smp = $this->seedLembagaSmp($yayasan);
        $sma = $this->seedLembagaSma($yayasan);

        $this->seedStaf($smp, 'smp');
        $this->seedStaf($sma, 'sma');

        [$smpTaLama, $smpTaBaru] = $this->seedTahunAjaran($smp, '2026', '2027', statusAktifBaru: true);
        [$smaTaLama, $smaTaBaru] = $this->seedTahunAjaran($sma, '2026', '2027', statusAktifBaru: true);

        $this->seedDataPeriodik($smp, $smpTaBaru);
        $this->seedDataPeriodik($sma, $smaTaBaru);

        $this->seedLayananProgramEkskul($smp, 'SMP');
        $this->seedLayananProgramEkskul($sma, 'SMA');

        // SMP: konfigurasi PPDB lengkap di tahun ajaran LAMA (2025/2026) — untuk uji fitur duplikasi.
        $jenisTesSmp = $this->seedJenisTes($smp, ['Tes Tulis', 'Wawancara', 'Tes Baca Al-Qur\'an']);
        $this->seedKonfigurasiPpdb($smp, $smpTaLama, $jenisTesSmp, $this->gelombangSmp(), $this->jalurSmp());

        // SMP: konfigurasi PPDB juga di tahun ajaran AKTIF, supaya wizard SPMB publik
        // bisa langsung diuji untuk SMP tanpa perlu menjalankan fitur duplikasi dulu.
        $this->seedKonfigurasiPpdb($smp, $smpTaBaru, $jenisTesSmp, $this->gelombangSmpAktif(), $this->jalurSmp());

        // SMA: konfigurasi PPDB lengkap langsung di tahun ajaran AKTIF (2026/2027).
        $jenisTesSma = $this->seedJenisTes($sma, ['Tes Tulis', 'Tes Wawancara', 'Tes Potensi Akademik']);
        $this->seedKonfigurasiPpdb($sma, $smaTaBaru, $jenisTesSma, $this->gelombangSma(), $this->jalurSma());
    }

    private function seedLembagaSmp(Yayasan $yayasan): Lembaga
    {
        return Lembaga::firstOrCreate(
            ['npsn' => '20223344'],
            [
                'yayasan_id' => $yayasan->id,
                'nss' => '202026001045',
                'nama' => 'SMP Islam Al-Hikmah',
                'bentuk_pendidikan' => 'SMP',
                'status_sekolah' => 'swasta',
                'status_kepemilikan' => 'Yayasan',
                'naungan' => 'kemendikdasmen',
                'sk_pendirian_nomor' => '421.3/SK.045/Disdik/2006',
                'sk_pendirian_tanggal' => '2006-06-01',
                'sk_izin_operasional_nomor' => '421.3/IOP.089/Disdik/2006',
                'sk_izin_operasional_tanggal' => '2006-07-15',
                'akreditasi' => 'A',
                'sk_akreditasi_nomor' => '1234/BAN-SM/SK/2022',
                'tanggal_sk_akreditasi' => '2022-11-10',
                'nama_kepala_sekolah' => 'Drs. H. Bambang Suryadi, M.Pd.',
                'nama_bendahara_bosp' => 'Nur Aisyah, S.Pd.',
                'alamat_jalan' => 'Jl. Pendidikan Raya No. 45',
                'rt' => '003',
                'rw' => '008',
                'desa_kelurahan' => 'Sukaluyu',
                'kecamatan' => 'Cibeunying Kaler',
                'kabupaten_kota' => 'Kota Bandung',
                'provinsi' => 'Jawa Barat',
                'kode_pos' => '40123',
                'lintang' => '-6.8951000',
                'bujur' => '107.6134000',
                'telepon' => '022-7301234',
                'email' => 'smp@alhikmah.sch.id',
                'website' => 'https://smp.alhikmah.sch.id',
                'nama_bank' => 'Bank BRI',
                'cabang_kcp_unit' => 'KCP Bandung Cibeunying',
                'rekening_atas_nama' => 'SMP Islam Al-Hikmah',
                'nomor_rekening' => '0123-01-987654-50-1',
                'mbs' => true,
                'nama_wajib_pajak' => 'SMP Islam Al-Hikmah',
                'npwp' => '02.345.678.9-012.000',
                'memungut_iuran' => true,
                'nominal_iuran' => 350000,
                'periode_iuran' => 'bulanan',
                'status_aktif' => true,
            ]
        );
    }

    private function seedLembagaSma(Yayasan $yayasan): Lembaga
    {
        return Lembaga::firstOrCreate(
            ['npsn' => '20223355'],
            [
                'yayasan_id' => $yayasan->id,
                'nss' => '302026001046',
                'nama' => 'SMA Islam Al-Hikmah',
                'bentuk_pendidikan' => 'SMA',
                'status_sekolah' => 'swasta',
                'status_kepemilikan' => 'Yayasan',
                'naungan' => 'kemendikdasmen',
                'sk_pendirian_nomor' => '421.3/SK.078/Disdik/2010',
                'sk_pendirian_tanggal' => '2010-05-20',
                'sk_izin_operasional_nomor' => '421.3/IOP.112/Disdik/2010',
                'sk_izin_operasional_tanggal' => '2010-06-30',
                'akreditasi' => 'A',
                'sk_akreditasi_nomor' => '5678/BAN-SM/SK/2021',
                'tanggal_sk_akreditasi' => '2021-09-05',
                'nama_kepala_sekolah' => 'Dr. Hj. Ratna Dewi, M.M.Pd.',
                'nama_bendahara_bosp' => 'Fajar Ramadhan, S.E.',
                'alamat_jalan' => 'Jl. Pendidikan Raya No. 47',
                'rt' => '003',
                'rw' => '008',
                'desa_kelurahan' => 'Sukaluyu',
                'kecamatan' => 'Cibeunying Kaler',
                'kabupaten_kota' => 'Kota Bandung',
                'provinsi' => 'Jawa Barat',
                'kode_pos' => '40123',
                'lintang' => '-6.8953000',
                'bujur' => '107.6138000',
                'telepon' => '022-7301235',
                'email' => 'sma@alhikmah.sch.id',
                'website' => 'https://sma.alhikmah.sch.id',
                'nama_bank' => 'Bank BRI',
                'cabang_kcp_unit' => 'KCP Bandung Cibeunying',
                'rekening_atas_nama' => 'SMA Islam Al-Hikmah',
                'nomor_rekening' => '0123-01-987655-50-2',
                'mbs' => true,
                'nama_wajib_pajak' => 'SMA Islam Al-Hikmah',
                'npwp' => '02.345.679.0-012.000',
                'memungut_iuran' => true,
                'nominal_iuran' => 450000,
                'periode_iuran' => 'bulanan',
                'status_aktif' => true,
            ]
        );
    }

    private function seedStaf(Lembaga $lembaga, string $prefix): void
    {
        if ($prefix === 'smp') {
            $pimpinan = [
                ['name' => 'Drs. H. Bambang Suryadi, M.Pd.', 'email' => 'kepsek.smp@alhikmah.sch.id', 'role' => 'kepala_sekolah'],
                ['name' => 'Dewi Lestari, S.Pd.', 'email' => 'adm.smp@alhikmah.sch.id', 'role' => 'admin_administrasi'],
                ['name' => 'Nur Aisyah, S.Pd.', 'email' => 'keuangan.smp@alhikmah.sch.id', 'role' => 'admin_keuangan'],
            ];
            $guruList = [
                [
                    'name' => 'Budi Santoso, S.Pd.', 'email' => 'budi.santoso@alhikmah.sch.id',
                    'nik' => '3273011503850001', 'nuptk' => '1234567890123456', 'nip' => '198503152010011001',
                    'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1985-03-15',
                    'no_hp' => '081234567801', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'PNS',
                    'golongan_pangkat' => 'Penata Muda Tk.I / III-b', 'tmt_tugas' => '2010-01-01', 'tmt_pns' => '2010-01-01',
                    'pendidikan' => [
                        ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Universitas Pendidikan Indonesia', 'fakultas' => 'FPMIPA', 'bidang_studi' => 'Pendidikan Matematika', 'kependidikan' => true, 'tahun_masuk' => 2003, 'tahun_lulus' => 2007],
                    ],
                    'sertifikasi' => ['jenis_sertifikasi' => 'Sertifikasi Guru (Portofolio)', 'nomor_sertifikat' => '123456789012', 'bidang_studi_sertifikasi' => 'Matematika', 'nrg' => '112233445566', 'tahun_sertifikasi' => 2012],
                    'jabatan_tambahan' => null,
                ],
                [
                    'name' => 'Siti Rahmawati, S.Pd.', 'email' => 'siti.rahmawati@alhikmah.sch.id',
                    'nik' => '3273015207880002', 'nuptk' => null, 'nip' => null,
                    'jenis_kelamin' => 'P', 'tempat_lahir' => 'Cimahi', 'tanggal_lahir' => '1988-07-12',
                    'no_hp' => '081234567802', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                    'golongan_pangkat' => null, 'tmt_tugas' => '2015-07-01', 'tmt_pns' => null,
                    'pendidikan' => [
                        ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Universitas Islam Negeri Sunan Gunung Djati', 'fakultas' => 'Tarbiyah', 'bidang_studi' => 'Pendidikan Guru Madrasah Ibtidaiyah', 'kependidikan' => true, 'tahun_masuk' => 2006, 'tahun_lulus' => 2010],
                    ],
                    'sertifikasi' => null,
                    'jabatan_tambahan' => 'Wali Kelas',
                ],
                [
                    'name' => 'Andi Wijaya, S.Pd.I.', 'email' => 'andi.wijaya@alhikmah.sch.id',
                    'nik' => '3273012009900003', 'nuptk' => null, 'nip' => null,
                    'jenis_kelamin' => 'L', 'tempat_lahir' => 'Garut', 'tanggal_lahir' => '1990-09-20',
                    'no_hp' => '081234567803', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'Honorer',
                    'golongan_pangkat' => null, 'tmt_tugas' => '2019-07-01', 'tmt_pns' => null,
                    'pendidikan' => [
                        ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Institut Agama Islam Negeri Bandung', 'fakultas' => 'Tarbiyah', 'bidang_studi' => 'Pendidikan Agama Islam', 'kependidikan' => true, 'tahun_masuk' => 2008, 'tahun_lulus' => 2012],
                    ],
                    'sertifikasi' => null,
                    'jabatan_tambahan' => 'Pembina Ekstrakurikuler',
                ],
            ];
        } else {
            $pimpinan = [
                ['name' => 'Dr. Hj. Ratna Dewi, M.M.Pd.', 'email' => 'kepsek.sma@alhikmah.sch.id', 'role' => 'kepala_sekolah'],
                ['name' => 'Rizal Firmansyah, S.Kom.', 'email' => 'adm.sma@alhikmah.sch.id', 'role' => 'admin_administrasi'],
                ['name' => 'Fajar Ramadhan, S.E.', 'email' => 'keuangan.sma@alhikmah.sch.id', 'role' => 'admin_keuangan'],
            ];
            $guruList = [
                [
                    'name' => 'Hendra Gunawan, S.Pd.', 'email' => 'hendra.gunawan@alhikmah.sch.id',
                    'nik' => '3273010108820004', 'nuptk' => '2234567890123456', 'nip' => '198201082008011002',
                    'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1982-01-08',
                    'no_hp' => '081234567804', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'PNS',
                    'golongan_pangkat' => 'Penata / III-c', 'tmt_tugas' => '2008-01-01', 'tmt_pns' => '2008-01-01',
                    'pendidikan' => [
                        ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Institut Teknologi Bandung', 'fakultas' => 'FMIPA', 'bidang_studi' => 'Fisika', 'kependidikan' => false, 'tahun_masuk' => 2000, 'tahun_lulus' => 2004],
                        ['jenjang_pendidikan' => 'S2', 'sekolah_formal' => 'Universitas Pendidikan Indonesia', 'fakultas' => 'Sekolah Pascasarjana', 'bidang_studi' => 'Pendidikan Fisika', 'kependidikan' => true, 'tahun_masuk' => 2013, 'tahun_lulus' => 2015],
                    ],
                    'sertifikasi' => ['jenis_sertifikasi' => 'Sertifikasi Guru (PLPG)', 'nomor_sertifikat' => '223456789012', 'bidang_studi_sertifikasi' => 'Fisika', 'nrg' => '122334455667', 'tahun_sertifikasi' => 2010],
                    'jabatan_tambahan' => 'Wakil Kepala Sekolah Kurikulum',
                ],
                [
                    'name' => 'Maya Anggraini, S.Pd.', 'email' => 'maya.anggraini@alhikmah.sch.id',
                    'nik' => '3273014412910005', 'nuptk' => null, 'nip' => null,
                    'jenis_kelamin' => 'P', 'tempat_lahir' => 'Sumedang', 'tanggal_lahir' => '1991-12-04',
                    'no_hp' => '081234567805', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'PPPK',
                    'golongan_pangkat' => 'IX', 'tmt_tugas' => '2021-03-01', 'tmt_pns' => null,
                    'pendidikan' => [
                        ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Universitas Pendidikan Indonesia', 'fakultas' => 'FPBS', 'bidang_studi' => 'Pendidikan Bahasa Inggris', 'kependidikan' => true, 'tahun_masuk' => 2009, 'tahun_lulus' => 2013],
                    ],
                    'sertifikasi' => ['jenis_sertifikasi' => 'Sertifikasi Guru (PPG Dalam Jabatan)', 'nomor_sertifikat' => '323456789012', 'bidang_studi_sertifikasi' => 'Bahasa Inggris', 'nrg' => '132334455668', 'tahun_sertifikasi' => 2022],
                    'jabatan_tambahan' => null,
                ],
                [
                    'name' => 'Taufik Hidayat, S.Pd.', 'email' => 'taufik.hidayat@alhikmah.sch.id',
                    'nik' => '3273011511870006', 'nuptk' => null, 'nip' => null,
                    'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1987-11-15',
                    'no_hp' => '081234567806', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'GTY',
                    'golongan_pangkat' => null, 'tmt_tugas' => '2016-07-01', 'tmt_pns' => null,
                    'pendidikan' => [
                        ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Universitas Pendidikan Indonesia', 'fakultas' => 'FIP', 'bidang_studi' => 'Bimbingan dan Konseling', 'kependidikan' => true, 'tahun_masuk' => 2005, 'tahun_lulus' => 2009],
                    ],
                    'sertifikasi' => null,
                    'jabatan_tambahan' => 'Koordinator BK',
                ],
            ];
        }

        $adminYayasanRole = 'yayasan_super_admin';

        // Admin yayasan dibuat sekali saja (dicek lewat email agar tidak dobel saat seeder dijalankan untuk lembaga kedua).
        if (! User::where('email', 'admin.yayasan@alhikmah.sch.id')->exists()) {
            $adminYayasan = User::create([
                'name' => 'Ahmad Fauzi (Admin Yayasan)',
                'email' => 'admin.yayasan@alhikmah.sch.id',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
            $adminYayasan->assignRole($adminYayasanRole);
        }

        foreach ($pimpinan as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'lembaga_id' => $lembaga->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->assignRole($data['role']);
        }

        foreach ($guruList as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'lembaga_id' => $lembaga->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->assignRole('guru');

            $guru = Guru::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'lembaga_id' => $lembaga->id,
                    'nik' => $data['nik'],
                    'nuptk' => $data['nuptk'],
                    'nip' => $data['nip'],
                    'nama' => $data['name'],
                    'jenis_kelamin' => $data['jenis_kelamin'],
                    'tempat_lahir' => $data['tempat_lahir'],
                    'tanggal_lahir' => $data['tanggal_lahir'],
                    'agama' => 'Islam',
                    'kewarganegaraan' => 'WNI',
                    'alamat_jalan' => 'Jl. Cihampelas No. '.random_int(10, 200),
                    'rt' => '002',
                    'rw' => '005',
                    'desa_kelurahan' => 'Cipaganti',
                    'kecamatan' => 'Coblong',
                    'kabupaten_kota' => 'Kota Bandung',
                    'provinsi' => 'Jawa Barat',
                    'kode_pos' => '40131',
                    'no_hp' => $data['no_hp'],
                    'email' => $data['email'],
                    'jenis_ptk' => $data['jenis_ptk'],
                    'status_kepegawaian' => $data['status_kepegawaian'],
                    'golongan_pangkat' => $data['golongan_pangkat'],
                    'tmt_tugas' => $data['tmt_tugas'],
                    'tmt_pns' => $data['tmt_pns'],
                    'status_aktif' => 'aktif',
                ]
            );

            foreach ($data['pendidikan'] as $riwayat) {
                RiwayatPendidikanGuru::firstOrCreate(
                    ['guru_id' => $guru->id, 'jenjang_pendidikan' => $riwayat['jenjang_pendidikan']],
                    $riwayat + ['gelar_akademik' => null]
                );
            }

            if ($data['sertifikasi']) {
                SertifikasiGuru::firstOrCreate(
                    ['guru_id' => $guru->id, 'jenis_sertifikasi' => $data['sertifikasi']['jenis_sertifikasi']],
                    $data['sertifikasi']
                );
            }

            if ($data['jabatan_tambahan']) {
                $jabatan = JabatanTambahanMaster::where('nama', $data['jabatan_tambahan'])->first();
                if ($jabatan && ! GuruJabatanTambahan::where('guru_id', $guru->id)->where('jabatan_tambahan_master_id', $jabatan->id)->exists()) {
                    GuruJabatanTambahan::create([
                        'guru_id' => $guru->id,
                        'jabatan_tambahan_master_id' => $jabatan->id,
                        'mulai_periode' => $data['tmt_tugas'],
                        'no_sk' => 'SK.'.random_int(100, 999).'/Yayasan/'.date('Y', strtotime($data['tmt_tugas'])),
                    ]);
                }
            }
        }
    }

    /**
     * @return array{0: TahunAjaran, 1: TahunAjaran} [tahun ajaran lama (nonaktif), tahun ajaran baru]
     */
    private function seedTahunAjaran(Lembaga $lembaga, string $tahunAwal, string $tahunAkhir, bool $statusAktifBaru): array
    {
        $lama = TahunAjaran::firstOrCreate(
            ['lembaga_id' => $lembaga->id, 'nama' => ($tahunAwal - 1).'/'.$tahunAwal],
            [
                'tanggal_mulai' => ($tahunAwal - 1).'-07-01',
                'tanggal_selesai' => $tahunAwal.'-06-30',
                'status_aktif' => false,
            ]
        );
        $this->seedSemester($lama, $tahunAwal - 1);

        $baru = TahunAjaran::firstOrCreate(
            ['lembaga_id' => $lembaga->id, 'nama' => $tahunAwal.'/'.$tahunAkhir],
            [
                'tanggal_mulai' => $tahunAwal.'-07-01',
                'tanggal_selesai' => $tahunAkhir.'-06-30',
                'status_aktif' => $statusAktifBaru,
            ]
        );
        $this->seedSemester($baru, $tahunAwal);

        return [$lama, $baru];
    }

    private function seedSemester(TahunAjaran $tahunAjaran, int $tahunGanjil): void
    {
        $ganjilAktif = $tahunAjaran->status_aktif;

        Semester::firstOrCreate(
            ['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil'],
            [
                'urutan' => 1,
                'kode_dapodik' => $tahunGanjil.'1',
                'tanggal_mulai' => $tahunGanjil.'-07-01',
                'tanggal_selesai' => $tahunGanjil.'-12-20',
                'status_aktif' => $ganjilAktif,
            ]
        );

        Semester::firstOrCreate(
            ['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Genap'],
            [
                'urutan' => 2,
                'kode_dapodik' => $tahunGanjil.'2',
                'tanggal_mulai' => ($tahunGanjil + 1).'-01-05',
                'tanggal_selesai' => ($tahunGanjil + 1).'-06-30',
                'status_aktif' => false,
            ]
        );
    }

    private function seedDataPeriodik(Lembaga $lembaga, TahunAjaran $tahunAjaranAktif): void
    {
        foreach ($tahunAjaranAktif->semester as $semester) {
            LembagaDataPeriodik::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'semester_id' => $semester->id],
                [
                    'waktu_penyelenggaraan' => 'Pagi',
                    'sumber_listrik' => 'PLN',
                    'daya_listrik' => 5500,
                    'akses_internet' => 'Telkom Indihome (Fiber Optik)',
                    'status_bos' => true,
                    'sertifikasi_iso' => null,
                    'ketersediaan_air_bersih' => true,
                    'kecukupan_air_bersih' => true,
                    'jumlah_tempat_cuci_tangan' => 8,
                    'jumlah_jamban' => 6,
                    'stratifikasi_uks' => 'Strata 3 (Optimal)',
                    'media_kie_sanitasi' => true,
                ]
            );
        }
    }

    private function seedLayananProgramEkskul(Lembaga $lembaga, string $jenjang): void
    {
        LayananKhususLembaga::firstOrCreate(
            ['lembaga_id' => $lembaga->id, 'jenis_layanan' => 'Kelas Tahfidz Intensif'],
            [
                'no_sk' => 'SK.021/Yayasan/2020',
                'tmt' => '2020-07-01',
                'tst' => null,
                'keterangan' => 'Program unggulan hafalan Al-Qur\'an minimal 5 juz sebelum lulus.',
            ]
        );

        ProgramInklusiLembaga::firstOrCreate(
            ['lembaga_id' => $lembaga->id, 'kebutuhan_khusus' => 'Tunadaksa'],
            [
                'no_sk' => 'SK.033/Yayasan/2021',
                'tanggal_sk' => '2021-02-10',
                'tmt' => '2021-07-01',
                'tst' => null,
                'keterangan' => 'Menyediakan akses ramah kursi roda dan pendamping belajar.',
            ]
        );

        $ekskul = $jenjang === 'SMP'
            ? [['Olahraga', 'Futsal', 4], ['Kepramukaan', 'Pramuka', 2], ['Keagamaan', 'Qiroah', 2]]
            : [['Olahraga', 'Basket', 4], ['Kepramukaan', 'Paskibra', 3], ['Seni', 'Teater', 2]];

        foreach ($ekskul as [$jenis, $nama, $jam]) {
            EkstrakurikulerLembaga::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'nama_ekskul' => $nama],
                [
                    'jenis_ekskul' => $jenis,
                    'no_sk' => 'SK.'.random_int(100, 999).'/Yayasan/2024',
                    'tanggal_sk' => '2024-07-01',
                    'jam_per_minggu' => $jam,
                ]
            );
        }
    }

    /**
     * @return array<string, JenisTesMaster>
     */
    private function seedJenisTes(Lembaga $lembaga, array $namaList): array
    {
        $map = [];
        foreach ($namaList as $nama) {
            $map[$nama] = JenisTesMaster::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'nama' => $nama],
                ['deskripsi' => "Seleksi berupa {$nama} yang dinilai oleh tim penerimaan murid baru."]
            );
        }

        return $map;
    }

    private function gelombangSmp(): array
    {
        return [
            ['nama' => 'Gelombang 1', 'tanggal_buka' => '2025-01-06', 'tanggal_tutup' => '2025-02-14', 'kuota' => 80],
            ['nama' => 'Gelombang 2', 'tanggal_buka' => '2025-03-03', 'tanggal_tutup' => '2025-04-11', 'kuota' => 40],
        ];
    }

    private function gelombangSma(): array
    {
        return [
            ['nama' => 'Gelombang 1', 'tanggal_buka' => now()->subDays(5)->toDateString(), 'tanggal_tutup' => now()->addMonths(2)->toDateString(), 'kuota' => 120],
            ['nama' => 'Gelombang 2', 'tanggal_buka' => now()->addMonths(3)->toDateString(), 'tanggal_tutup' => now()->addMonths(4)->toDateString(), 'kuota' => 60],
        ];
    }

    /**
     * Konfigurasi PPDB SMP di atas sengaja hanya diisi di tahun ajaran LAMA (nonaktif)
     * untuk menguji fitur duplikasi. Gelombang di bawah ini dipakai khusus untuk
     * mengisi tahun ajaran AKTIF SMP juga, supaya wizard SPMB publik langsung bisa
     * diuji untuk SMP tanpa perlu menjalankan fitur duplikasi terlebih dahulu.
     * Tanggal relatif terhadap now() supaya selalu "sedang buka" kapan pun seeder dijalankan.
     */
    private function gelombangSmpAktif(): array
    {
        return [
            ['nama' => 'Gelombang 1', 'tanggal_buka' => now()->subDays(5)->toDateString(), 'tanggal_tutup' => now()->addMonths(2)->toDateString(), 'kuota' => 80],
            ['nama' => 'Gelombang 2', 'tanggal_buka' => now()->addMonths(3)->toDateString(), 'tanggal_tutup' => now()->addMonths(4)->toDateString(), 'kuota' => 40],
        ];
    }

    private function jalurSmp(): array
    {
        return [
            [
                'nama' => 'Reguler',
                'deskripsi' => 'Jalur pendaftaran umum berdasarkan urutan pendaftaran dan kelengkapan berkas.',
                'status_aktif' => true,
                'formulir' => [
                    ['label' => 'Sekolah Asal', 'field_type' => 'text', 'is_required' => true, 'options' => null],
                    ['label' => 'Nilai Rata-rata Rapor', 'field_type' => 'number', 'is_required' => true, 'options' => null],
                ],
                'dokumen' => [
                    ['nama_dokumen' => 'Akta Kelahiran', 'wajib' => true],
                    ['nama_dokumen' => 'Kartu Keluarga', 'wajib' => true],
                    ['nama_dokumen' => 'Fotokopi Rapor', 'wajib' => true],
                    ['nama_dokumen' => 'Pas Foto 3x4', 'wajib' => true],
                ],
                'seleksi' => [
                    ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Tes Tulis', 'jadwal' => '2025-02-20 08:00:00', 'kriteria' => 'Nilai minimal 65', 'bobot' => 60],
                    ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Wawancara', 'jadwal' => '2025-02-21 08:00:00', 'kriteria' => 'Lolos wawancara motivasi', 'bobot' => 40],
                ],
            ],
            [
                'nama' => 'Prestasi',
                'deskripsi' => 'Jalur khusus bagi calon murid dengan prestasi akademik atau non-akademik.',
                'status_aktif' => true,
                'formulir' => [
                    ['label' => 'Jenis Prestasi', 'field_type' => 'select', 'is_required' => true, 'options' => ['Akademik', 'Non-Akademik', 'Keagamaan']],
                    ['label' => 'Uraian Prestasi', 'field_type' => 'textarea', 'is_required' => true, 'options' => null],
                    ['label' => 'Sertifikat Pendukung', 'field_type' => 'file', 'is_required' => true, 'options' => null],
                ],
                'dokumen' => [
                    ['nama_dokumen' => 'Akta Kelahiran', 'wajib' => true],
                    ['nama_dokumen' => 'Kartu Keluarga', 'wajib' => true],
                    ['nama_dokumen' => 'Sertifikat/Piagam Prestasi', 'wajib' => true],
                ],
                'seleksi' => [
                    ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Wawancara', 'jadwal' => '2025-02-22 09:00:00', 'kriteria' => 'Verifikasi keaslian sertifikat & wawancara', 'bobot' => 100],
                ],
            ],
            [
                'nama' => 'Afirmasi',
                'deskripsi' => 'Jalur bagi calon murid dari keluarga kurang mampu, bebas biaya pendaftaran.',
                'status_aktif' => true,
                'formulir' => [],
                'dokumen' => [
                    ['nama_dokumen' => 'Kartu Keluarga Sejahtera (KKS) / SKTM', 'wajib' => true],
                    ['nama_dokumen' => 'Akta Kelahiran', 'wajib' => true],
                ],
                'seleksi' => [],
            ],
        ];
    }

    private function jalurSma(): array
    {
        return [
            [
                'nama' => 'Reguler',
                'deskripsi' => 'Jalur pendaftaran umum berdasarkan nilai rapor dan hasil tes seleksi.',
                'status_aktif' => true,
                'formulir' => [
                    ['label' => 'Sekolah Asal', 'field_type' => 'text', 'is_required' => true, 'options' => null],
                    ['label' => 'Pilihan Jurusan', 'field_type' => 'select', 'is_required' => true, 'options' => ['IPA', 'IPS']],
                ],
                'dokumen' => [
                    ['nama_dokumen' => 'Ijazah / SKL SMP', 'wajib' => true],
                    ['nama_dokumen' => 'Kartu Keluarga', 'wajib' => true],
                    ['nama_dokumen' => 'Fotokopi Rapor Kelas VII-IX', 'wajib' => true],
                    ['nama_dokumen' => 'Pas Foto 3x4', 'wajib' => true],
                ],
                'seleksi' => [
                    ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Tes Tulis', 'jadwal' => '2026-08-24 08:00:00', 'kriteria' => 'Nilai minimal 70', 'bobot' => 50],
                    ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Tes Potensi Akademik', 'jadwal' => '2026-08-25 08:00:00', 'kriteria' => 'Skor TPA minimal 60', 'bobot' => 50],
                ],
            ],
            [
                'nama' => 'Prestasi',
                'deskripsi' => 'Jalur khusus bagi calon murid dengan prestasi akademik, olahraga, atau seni tingkat kabupaten/kota ke atas.',
                'status_aktif' => true,
                'formulir' => [
                    ['label' => 'Tingkat Prestasi', 'field_type' => 'select', 'is_required' => true, 'options' => ['Kabupaten/Kota', 'Provinsi', 'Nasional', 'Internasional']],
                    ['label' => 'Uraian Prestasi', 'field_type' => 'textarea', 'is_required' => true, 'options' => null],
                ],
                'dokumen' => [
                    ['nama_dokumen' => 'Ijazah / SKL SMP', 'wajib' => true],
                    ['nama_dokumen' => 'Sertifikat/Piagam Prestasi', 'wajib' => true],
                ],
                'seleksi' => [
                    ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Tes Wawancara', 'jadwal' => '2026-08-26 09:00:00', 'kriteria' => 'Verifikasi keaslian sertifikat & wawancara', 'bobot' => 100],
                ],
            ],
            [
                'nama' => 'Afirmasi',
                'deskripsi' => 'Jalur bagi calon murid dari keluarga kurang mampu, bebas biaya pendaftaran.',
                'status_aktif' => true,
                'formulir' => [],
                'dokumen' => [
                    ['nama_dokumen' => 'Kartu Keluarga Sejahtera (KKS) / SKTM', 'wajib' => true],
                    ['nama_dokumen' => 'Ijazah / SKL SMP', 'wajib' => true],
                ],
                'seleksi' => [],
            ],
        ];
    }

    /**
     * @param  array<string, JenisTesMaster>  $jenisTesMap
     */
    private function seedKonfigurasiPpdb(Lembaga $lembaga, TahunAjaran $tahunAjaran, array $jenisTesMap, array $gelombangConfig, array $jalurConfig): void
    {
        $gelombangMap = [];
        foreach ($gelombangConfig as $g) {
            $gelombangMap[$g['nama']] = GelombangPpdb::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => $g['nama']],
                [
                    'tanggal_buka' => $g['tanggal_buka'],
                    'tanggal_tutup' => $g['tanggal_tutup'],
                    'kuota' => $g['kuota'],
                ]
            );
        }

        foreach ($jalurConfig as $j) {
            $jalur = JalurPpdb::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => $j['nama']],
                [
                    'deskripsi' => $j['deskripsi'],
                    'status_aktif' => $j['status_aktif'],
                ]
            );

            foreach ($j['formulir'] as $urutan => $field) {
                FormulirField::firstOrCreate(
                    ['jalur_ppdb_id' => $jalur->id, 'label' => $field['label']],
                    [
                        'field_type' => $field['field_type'],
                        'options' => $field['options'],
                        'is_required' => $field['is_required'],
                        'urutan' => $urutan,
                    ]
                );
            }

            foreach ($j['dokumen'] as $urutan => $dokumen) {
                DokumenSyaratPpdb::firstOrCreate(
                    ['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => $dokumen['nama_dokumen']],
                    [
                        'wajib' => $dokumen['wajib'],
                        'urutan' => $urutan,
                    ]
                );
            }

            foreach ($j['seleksi'] as $seleksi) {
                SeleksiPpdb::firstOrCreate(
                    [
                        'jalur_ppdb_id' => $jalur->id,
                        'gelombang_ppdb_id' => $gelombangMap[$seleksi['gelombang']]->id,
                        'jenis_tes_master_id' => $jenisTesMap[$seleksi['jenis_tes']]->id,
                    ],
                    [
                        'jadwal' => $seleksi['jadwal'],
                        'kriteria_kelulusan' => $seleksi['kriteria'],
                        'bobot' => $seleksi['bobot'],
                    ]
                );
            }
        }
    }
}
