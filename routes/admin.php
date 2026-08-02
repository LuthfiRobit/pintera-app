<?php

use App\Http\Controllers\Admin\DokumenSyaratController;
use App\Http\Controllers\Admin\FormulirFieldController;
use App\Http\Controllers\Admin\GelombangPpdbController;
use App\Http\Controllers\Admin\Guru\JabatanTambahanController as GuruJabatanTambahanController;
use App\Http\Controllers\Admin\Guru\RiwayatPendidikanController as GuruRiwayatPendidikanController;
use App\Http\Controllers\Admin\Guru\SertifikasiController as GuruSertifikasiController;
use App\Http\Controllers\Admin\GuruController;
use App\Http\Controllers\Admin\JabatanTambahanMasterController;
use App\Http\Controllers\Admin\JadwalPelajaranController;
use App\Http\Controllers\Admin\JalurPpdbController;
use App\Http\Controllers\Admin\JamPelajaranController;
use App\Http\Controllers\Admin\JenisTagihanController;
use App\Http\Controllers\Admin\JenisTesMasterController;
use App\Http\Controllers\Admin\KalenderAkademikController;
use App\Http\Controllers\Admin\KelasController;
use App\Http\Controllers\Admin\KenaikanKelasController;
use App\Http\Controllers\Admin\KomponenPenilaianController;
use App\Http\Controllers\Admin\Lembaga\DataPeriodikController as LembagaDataPeriodikController;
use App\Http\Controllers\Admin\Lembaga\EkstrakurikulerController as LembagaEkstrakurikulerController;
use App\Http\Controllers\Admin\Lembaga\LayananKhususController as LembagaLayananKhususController;
use App\Http\Controllers\Admin\Lembaga\ProgramInklusiController as LembagaProgramInklusiController;
use App\Http\Controllers\Admin\LembagaController;
use App\Http\Controllers\Admin\MataPelajaranController;
use App\Http\Controllers\Admin\PembayaranController;
use App\Http\Controllers\Admin\PendaftaranAdminController;
use App\Http\Controllers\Admin\PendaftaranSiswaController;
use App\Http\Controllers\Admin\PengaturanAkademikController;
use App\Http\Controllers\Admin\PolaJamController;
use App\Http\Controllers\Admin\RaporController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SeleksiController;
use App\Http\Controllers\Admin\SemesterController;
use App\Http\Controllers\Admin\SiswaController;
use App\Http\Controllers\Admin\SiswaImportController;
use App\Http\Controllers\Admin\SkPpdbController;
use App\Http\Controllers\Admin\SpmbKonfigurasiController;
use App\Http\Controllers\Admin\TagihanController;
use App\Http\Controllers\Admin\TahunAjaranController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('roles/data', [RoleController::class, 'data'])->name('roles.data');
    Route::get('roles/permissions-catalog', [RoleController::class, 'permissionsCatalog'])->name('roles.permissions-catalog');
    Route::resource('roles', RoleController::class)->except(['show']);
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::resource('lembaga', LembagaController::class)->except(['show', 'destroy']);
    Route::prefix('lembaga/{lembaga}')->name('lembaga.')->group(function () {
        Route::post('data-periodik', [LembagaDataPeriodikController::class, 'store'])->name('data-periodik.store');
        Route::put('data-periodik/{dataPeriodik}', [LembagaDataPeriodikController::class, 'update'])->name('data-periodik.update');
        Route::delete('data-periodik/{dataPeriodik}', [LembagaDataPeriodikController::class, 'destroy'])->name('data-periodik.destroy');

        Route::post('ekstrakurikuler', [LembagaEkstrakurikulerController::class, 'store'])->name('ekstrakurikuler.store');
        Route::put('ekstrakurikuler/{ekstrakurikuler}', [LembagaEkstrakurikulerController::class, 'update'])->name('ekstrakurikuler.update');
        Route::delete('ekstrakurikuler/{ekstrakurikuler}', [LembagaEkstrakurikulerController::class, 'destroy'])->name('ekstrakurikuler.destroy');

        Route::post('layanan-khusus', [LembagaLayananKhususController::class, 'store'])->name('layanan-khusus.store');
        Route::put('layanan-khusus/{layananKhusus}', [LembagaLayananKhususController::class, 'update'])->name('layanan-khusus.update');
        Route::delete('layanan-khusus/{layananKhusus}', [LembagaLayananKhususController::class, 'destroy'])->name('layanan-khusus.destroy');

        Route::post('program-inklusi', [LembagaProgramInklusiController::class, 'store'])->name('program-inklusi.store');
        Route::put('program-inklusi/{programInklusi}', [LembagaProgramInklusiController::class, 'update'])->name('program-inklusi.update');
        Route::delete('program-inklusi/{programInklusi}', [LembagaProgramInklusiController::class, 'destroy'])->name('program-inklusi.destroy');
    });
    Route::resource('guru', GuruController::class)->except(['show', 'destroy']);
    Route::patch('guru/{guru}/status', [GuruController::class, 'updateStatus'])->name('guru.update-status');

    Route::post('guru/{guru}/riwayat-pendidikan', [GuruRiwayatPendidikanController::class, 'store'])->name('guru.riwayat-pendidikan.store');
    Route::put('guru/{guru}/riwayat-pendidikan/{riwayatPendidikan}', [GuruRiwayatPendidikanController::class, 'update'])->name('guru.riwayat-pendidikan.update');
    Route::delete('guru/{guru}/riwayat-pendidikan/{riwayatPendidikan}', [GuruRiwayatPendidikanController::class, 'destroy'])->name('guru.riwayat-pendidikan.destroy');

    Route::post('guru/{guru}/sertifikasi', [GuruSertifikasiController::class, 'store'])->name('guru.sertifikasi.store');
    Route::put('guru/{guru}/sertifikasi/{sertifikasi}', [GuruSertifikasiController::class, 'update'])->name('guru.sertifikasi.update');
    Route::delete('guru/{guru}/sertifikasi/{sertifikasi}', [GuruSertifikasiController::class, 'destroy'])->name('guru.sertifikasi.destroy');

    Route::post('guru/{guru}/jabatan-tambahan', [GuruJabatanTambahanController::class, 'store'])->name('guru.jabatan-tambahan.store');
    Route::delete('guru/{guru}/jabatan-tambahan/{jabatanMasterId}', [GuruJabatanTambahanController::class, 'destroy'])->name('guru.jabatan-tambahan.destroy');
    Route::get('jabatan-tambahan-master', [JabatanTambahanMasterController::class, 'index'])->name('jabatan-tambahan-master.index');
    Route::post('jabatan-tambahan-master', [JabatanTambahanMasterController::class, 'store'])->name('jabatan-tambahan-master.store');
    Route::put('jabatan-tambahan-master/{jabatanTambahanMaster}', [JabatanTambahanMasterController::class, 'update'])->name('jabatan-tambahan-master.update');
    Route::delete('jabatan-tambahan-master/{jabatanTambahanMaster}', [JabatanTambahanMasterController::class, 'destroy'])->name('jabatan-tambahan-master.destroy');
    Route::resource('mata-pelajaran', MataPelajaranController::class)->except(['show', 'destroy']);
    Route::resource('kelas', KelasController::class)->parameters(['kelas' => 'kelas'])->except(['show', 'destroy']);
    Route::post('siswa/generate-akun-massal', [SiswaController::class, 'generateAkunMassal'])->name('siswa.generate-akun-massal');
    Route::post('siswa/{siswa}/generate-akun', [SiswaController::class, 'generateAkun'])->name('siswa.generate-akun');
    Route::resource('siswa', SiswaController::class)->except(['show', 'destroy']);
    Route::patch('siswa/{siswa}/status', [SiswaController::class, 'updateStatus'])->name('siswa.update-status');
    Route::patch('siswa/{siswa}/reset-password', [SiswaController::class, 'resetPassword'])->name('siswa.reset-password');
    Route::post('kalender-akademik', [KalenderAkademikController::class, 'store'])->name('kalender-akademik.store');
    Route::put('kalender-akademik/{kalenderAkademik}', [KalenderAkademikController::class, 'update'])->name('kalender-akademik.update');
    Route::delete('kalender-akademik/{kalenderAkademik}', [KalenderAkademikController::class, 'destroy'])->name('kalender-akademik.destroy');
    Route::get('pengaturan/akademik', [PengaturanAkademikController::class, 'index'])->name('pengaturan.akademik.index');
    Route::put('pengaturan/akademik/hari-aktif', [PengaturanAkademikController::class, 'updateHariAktif'])->name('pengaturan.akademik.hari-aktif');
    Route::get('siswa-spmb-daftar', [PendaftaranSiswaController::class, 'index'])->name('siswa.spmb-daftar.index');
    Route::post('siswa-spmb-daftar', [PendaftaranSiswaController::class, 'store'])->name('siswa.spmb-daftar.store');
    Route::get('siswa-import', [SiswaImportController::class, 'index'])->name('siswa.import.index');
    Route::get('siswa-import/template', [SiswaImportController::class, 'template'])->name('siswa.import.template');
    Route::post('siswa-import/preview', [SiswaImportController::class, 'preview'])->name('siswa.import.preview');
    Route::post('siswa-import/confirm', [SiswaImportController::class, 'confirm'])->name('siswa.import.confirm');

    Route::get('tahun-ajaran', [TahunAjaranController::class, 'index'])->name('tahun-ajaran.index');
    Route::get('tahun-ajaran/create', [TahunAjaranController::class, 'create'])->name('tahun-ajaran.create');
    Route::post('tahun-ajaran', [TahunAjaranController::class, 'store'])->name('tahun-ajaran.store');
    Route::patch('tahun-ajaran/{tahunAjaran}/activate', [TahunAjaranController::class, 'activate'])->name('tahun-ajaran.activate');

    Route::post('semester', [SemesterController::class, 'store'])->name('semester.store');
    Route::patch('semester/{semester}/activate', [SemesterController::class, 'activate'])->name('semester.activate');

    Route::get('jenis-tes', [JenisTesMasterController::class, 'index'])->name('jenis-tes.index');
    Route::post('jenis-tes', [JenisTesMasterController::class, 'store'])->name('jenis-tes.store');
    Route::put('jenis-tes/{jenisTes}', [JenisTesMasterController::class, 'update'])->name('jenis-tes.update');
    Route::delete('jenis-tes/{jenisTes}', [JenisTesMasterController::class, 'destroy'])->name('jenis-tes.destroy');

    Route::resource('gelombang-ppdb', GelombangPpdbController::class)->except(['show', 'destroy']);

    Route::resource('jalur-ppdb', JalurPpdbController::class)->except(['show', 'destroy']);

    Route::post('formulir-field', [FormulirFieldController::class, 'store'])->name('formulir-field.store');
    Route::delete('formulir-field/{formulirField}', [FormulirFieldController::class, 'destroy'])->name('formulir-field.destroy');

    Route::post('dokumen-syarat', [DokumenSyaratController::class, 'store'])->name('dokumen-syarat.store');
    Route::delete('dokumen-syarat/{dokumenSyarat}', [DokumenSyaratController::class, 'destroy'])->name('dokumen-syarat.destroy');

    Route::post('seleksi', [SeleksiController::class, 'store'])->name('seleksi.store');
    Route::delete('seleksi/{seleksi}', [SeleksiController::class, 'destroy'])->name('seleksi.destroy');

    Route::post('spmb-konfigurasi/duplikasi', [SpmbKonfigurasiController::class, 'duplikasi'])->name('spmb-konfigurasi.duplikasi');

    Route::get('spmb-pendaftaran', [PendaftaranAdminController::class, 'index'])->name('spmb-pendaftaran.index');
    Route::get('spmb-pendaftaran/data', [PendaftaranAdminController::class, 'data'])->name('spmb-pendaftaran.data');
    Route::get('spmb-pendaftaran/{pendaftaran}', [PendaftaranAdminController::class, 'show'])->name('spmb-pendaftaran.show');
    Route::post('spmb-pendaftaran/{pendaftaran}/dokumen/{dokumen}', [PendaftaranAdminController::class, 'verifikasiDokumen'])->name('spmb-pendaftaran.verifikasi-dokumen');
    Route::post('spmb-pendaftaran/{pendaftaran}/nilai', [PendaftaranAdminController::class, 'simpanNilai'])->name('spmb-pendaftaran.nilai');
    Route::post('spmb-pendaftaran/{pendaftaran}/keputusan', [PendaftaranAdminController::class, 'tetapkanKeputusan'])->name('spmb-pendaftaran.keputusan');
    Route::post('spmb-pendaftaran/{pendaftaran}/tagihan-susulan', [TagihanController::class, 'buatSusulan'])->name('tagihan.susulan');
    Route::get('spmb-pendaftaran-nilai-massal', [PendaftaranAdminController::class, 'nilaiMassal'])->name('spmb-pendaftaran.nilai-massal');
    Route::post('spmb-pendaftaran-nilai-massal', [PendaftaranAdminController::class, 'simpanNilaiMassal'])->name('spmb-pendaftaran.nilai-massal.store');

    Route::get('sk-ppdb/create', [SkPpdbController::class, 'create'])->name('sk-ppdb.create');
    Route::post('sk-ppdb', [SkPpdbController::class, 'store'])->name('sk-ppdb.store');

    Route::get('jenis-tagihan', [JenisTagihanController::class, 'index'])->name('jenis-tagihan.index');
    Route::post('jenis-tagihan', [JenisTagihanController::class, 'store'])->name('jenis-tagihan.store');
    Route::put('jenis-tagihan/{jenisTagihan}', [JenisTagihanController::class, 'update'])->name('jenis-tagihan.update');
    Route::delete('jenis-tagihan/{jenisTagihan}', [JenisTagihanController::class, 'destroy'])->name('jenis-tagihan.destroy');
    Route::get('jenis-tagihan/{jenisTagihan}/nominal', [JenisTagihanController::class, 'nominal'])->name('jenis-tagihan.nominal');
    Route::post('jenis-tagihan/{jenisTagihan}/nominal', [JenisTagihanController::class, 'simpanNominal'])->name('jenis-tagihan.nominal.store');

    Route::get('tagihan', [TagihanController::class, 'index'])->name('tagihan.index');
    Route::get('tagihan/data', [TagihanController::class, 'data'])->name('tagihan.data');
    Route::post('tagihan/{tagihan}/skema-cicilan', [TagihanController::class, 'buatSkemaCicilan'])->name('tagihan.skema-cicilan.store');
    Route::post('skema-cicilan/{skemaCicilan}/nominal', [TagihanController::class, 'simpanNominalCicilan'])->name('skema-cicilan.nominal.store');
    Route::post('tagihan/{tagihan}/catat-manual', [TagihanController::class, 'catatManualTagihan'])->name('tagihan.catat-manual');
    Route::post('cicilan/{cicilan}/catat-manual', [TagihanController::class, 'catatManualCicilan'])->name('cicilan.catat-manual');

    Route::get('pembayaran', [PembayaranController::class, 'index'])->name('pembayaran.index');
    Route::get('pembayaran/data', [PembayaranController::class, 'data'])->name('pembayaran.data');
    Route::post('pembayaran/{pembayaran}/verifikasi', [PembayaranController::class, 'verifikasi'])->name('pembayaran.verifikasi');

    Route::get('pola-jam', [PolaJamController::class, 'index'])->name('pola-jam.index');
    Route::get('pola-jam/create', [PolaJamController::class, 'create'])->name('pola-jam.create');
    Route::post('pola-jam', [PolaJamController::class, 'store'])->name('pola-jam.store');
    Route::get('pola-jam/{polaJam}/edit', [PolaJamController::class, 'edit'])->name('pola-jam.edit');
    Route::put('pola-jam/{polaJam}', [PolaJamController::class, 'update'])->name('pola-jam.update');
    Route::delete('pola-jam/{polaJam}', [PolaJamController::class, 'destroy'])->name('pola-jam.destroy');
    Route::put('pola-jam/{polaJam}/assign-kelas', [PolaJamController::class, 'assignKelas'])->name('pola-jam.assign-kelas');
    Route::post('jam-pelajaran', [JamPelajaranController::class, 'store'])->name('jam-pelajaran.store');
    Route::get('jam-pelajaran/{jamPelajaran}/edit', [JamPelajaranController::class, 'edit'])->name('jam-pelajaran.edit');
    Route::put('jam-pelajaran/{jamPelajaran}', [JamPelajaranController::class, 'update'])->name('jam-pelajaran.update');
    Route::delete('jam-pelajaran/{jamPelajaran}', [JamPelajaranController::class, 'destroy'])->name('jam-pelajaran.destroy');

    Route::get('jadwal-pelajaran', [JadwalPelajaranController::class, 'index'])->name('jadwal-pelajaran.index');
    Route::get('jadwal-pelajaran/opsi', [JadwalPelajaranController::class, 'opsi'])->name('jadwal-pelajaran.opsi');
    Route::get('jadwal-pelajaran/create', [JadwalPelajaranController::class, 'create'])->name('jadwal-pelajaran.create');
    Route::post('jadwal-pelajaran', [JadwalPelajaranController::class, 'store'])->name('jadwal-pelajaran.store');
    Route::get('jadwal-pelajaran/{jadwalPelajaran}/edit', [JadwalPelajaranController::class, 'edit'])->name('jadwal-pelajaran.edit');
    Route::put('jadwal-pelajaran/{jadwalPelajaran}', [JadwalPelajaranController::class, 'update'])->name('jadwal-pelajaran.update');
    Route::delete('jadwal-pelajaran/{jadwalPelajaran}', [JadwalPelajaranController::class, 'destroy'])->name('jadwal-pelajaran.destroy');

    Route::get('komponen-penilaian', [KomponenPenilaianController::class, 'index'])->name('komponen-penilaian.index');
    Route::get('komponen-penilaian/create', [KomponenPenilaianController::class, 'create'])->name('komponen-penilaian.create');
    Route::post('komponen-penilaian', [KomponenPenilaianController::class, 'store'])->name('komponen-penilaian.store');
    Route::get('komponen-penilaian/opsi', [KomponenPenilaianController::class, 'opsi'])->name('komponen-penilaian.opsi');
    Route::get('komponen-penilaian/{komponenPenilaian}/edit', [KomponenPenilaianController::class, 'edit'])->name('komponen-penilaian.edit');
    Route::put('komponen-penilaian/{komponenPenilaian}', [KomponenPenilaianController::class, 'update'])->name('komponen-penilaian.update');
    Route::delete('komponen-penilaian/{komponenPenilaian}', [KomponenPenilaianController::class, 'destroy'])->name('komponen-penilaian.destroy');
    Route::get('rapor', [RaporController::class, 'index'])->name('rapor.index');
    Route::get('rapor/opsi', [RaporController::class, 'opsi'])->name('rapor.opsi');
    Route::get('rapor/cetak', [RaporController::class, 'cetak'])->name('rapor.cetak');

    Route::get('kenaikan-kelas', [KenaikanKelasController::class, 'index'])->name('kenaikan-kelas.index');
    Route::post('kenaikan-kelas', [KenaikanKelasController::class, 'store'])->name('kenaikan-kelas.store');
});

Route::middleware(['auth', 'verified'])->prefix('guru')->name('guru.')->group(function () {
    Route::get('sesi', [\App\Http\Controllers\Guru\SesiPembelajaranController::class, 'index'])->name('sesi.index');
    Route::get('sesi/{sesi}', [\App\Http\Controllers\Guru\SesiPembelajaranController::class, 'show'])->name('sesi.show');
    Route::put('sesi/{sesi}', [\App\Http\Controllers\Guru\SesiPembelajaranController::class, 'update'])->name('sesi.update');

    Route::get('asesmen', [\App\Http\Controllers\Guru\AsesmenController::class, 'index'])->name('asesmen.index');
    Route::get('asesmen/create', [\App\Http\Controllers\Guru\AsesmenController::class, 'create'])->name('asesmen.create');
    Route::post('asesmen', [\App\Http\Controllers\Guru\AsesmenController::class, 'store'])->name('asesmen.store');
    Route::get('asesmen/{asesmen}', [\App\Http\Controllers\Guru\AsesmenController::class, 'show'])->name('asesmen.show');
    Route::put('asesmen/{asesmen}/nilai', [\App\Http\Controllers\Guru\AsesmenController::class, 'updateNilai'])->name('asesmen.update-nilai');

    Route::get('komponen-penilaian', [\App\Http\Controllers\Guru\KomponenPenilaianController::class, 'index'])->name('komponen-penilaian.index');
    Route::get('komponen-penilaian/create', [\App\Http\Controllers\Guru\KomponenPenilaianController::class, 'create'])->name('komponen-penilaian.create');
    Route::post('komponen-penilaian', [\App\Http\Controllers\Guru\KomponenPenilaianController::class, 'store'])->name('komponen-penilaian.store');
    Route::get('komponen-penilaian/{komponenPenilaian}/edit', [\App\Http\Controllers\Guru\KomponenPenilaianController::class, 'edit'])->name('komponen-penilaian.edit');
    Route::put('komponen-penilaian/{komponenPenilaian}', [\App\Http\Controllers\Guru\KomponenPenilaianController::class, 'update'])->name('komponen-penilaian.update');
    Route::delete('komponen-penilaian/{komponenPenilaian}', [\App\Http\Controllers\Guru\KomponenPenilaianController::class, 'destroy'])->name('komponen-penilaian.destroy');
});
