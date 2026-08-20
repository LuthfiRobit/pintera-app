<?php

use App\Http\Controllers\Admin\JenisTagihanController;
use App\Http\Controllers\Admin\JenisTagihanMonitoringController;
use App\Http\Controllers\Admin\KategoriKeringananController;
use App\Http\Controllers\Admin\KasusAksesLogController;
use App\Http\Controllers\Admin\KasusController as AdminKasusController;
use App\Http\Controllers\Admin\KasusTerhapusController;
use App\Http\Controllers\Admin\KenaikanKelasController;
use App\Http\Controllers\Admin\KomponenPenilaianController;
use App\Http\Controllers\Admin\ManualPaymentController;
use App\Http\Controllers\Admin\PembayaranController;
use App\Http\Controllers\Admin\RaporController;
use App\Http\Controllers\Admin\RppController;
use App\Http\Controllers\Admin\TagihanController;
use App\Http\Controllers\Admin\VirtualAccountController;
use App\Http\Controllers\Guru\AsesmenController;
use App\Http\Controllers\Guru\Akademik\JurnalKbmController;
use App\Http\Controllers\Guru\Akademik\RekapKehadiranController;
use App\Http\Controllers\Guru\RaporController as GuruRaporController;
use App\Http\Controllers\KasusConsentController;
use App\Http\Controllers\KasusController;
use App\Http\Controllers\KasusEvaluasiController;
use App\Http\Controllers\KasusSesiController;
use App\Http\Controllers\KasusTugasBatchPreviewController;
use App\Http\Controllers\KasusTugasController;
use App\Http\Controllers\KasusTugasSubmissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {

    require base_path('routes/admin/roles.php');
    require base_path('routes/admin/lembaga.php');
    require base_path('routes/admin/guru-data.php');
    require base_path('routes/admin/whatsapp-template.php');
    require base_path('routes/admin/akademik-master.php');
    require base_path('routes/admin/siswa.php');
    require base_path('routes/admin/spmb.php');

    Route::get('jenis-tagihan/create', [JenisTagihanController::class, 'create'])->name('jenis-tagihan.create');
    Route::get('jenis-tagihan', [JenisTagihanController::class, 'index'])->name('jenis-tagihan.index');
    Route::post('jenis-tagihan', [JenisTagihanController::class, 'store'])->name('jenis-tagihan.store');
    Route::get('jenis-tagihan/{jenisTagihan}/edit', [JenisTagihanController::class, 'edit'])->name('jenis-tagihan.edit');
    Route::put('jenis-tagihan/{jenisTagihan}', [JenisTagihanController::class, 'update'])->name('jenis-tagihan.update');
    Route::delete('jenis-tagihan/{jenisTagihan}', [JenisTagihanController::class, 'destroy'])->name('jenis-tagihan.destroy');
    Route::post('jenis-tagihan/{jenisTagihan}/proses', [JenisTagihanController::class, 'prosesTagihan'])->name('jenis-tagihan.proses');
    Route::get('jenis-tagihan/{jenisTagihan}/nominal', [JenisTagihanController::class, 'nominal'])->name('jenis-tagihan.nominal');
    Route::post('jenis-tagihan/{jenisTagihan}/nominal', [JenisTagihanController::class, 'simpanNominal'])->name('jenis-tagihan.nominal.store');
    
    Route::get('jenis-tagihan/{jenisTagihan}/monitoring', [JenisTagihanMonitoringController::class, 'index'])->name('jenis-tagihan.monitoring.index');
    Route::post('jenis-tagihan/{jenisTagihan}/batal-tagihan/{tagihan}', [JenisTagihanMonitoringController::class, 'batalTagihan'])->name('jenis-tagihan.monitoring.batal');

    Route::post('kategori-keringanan', [KategoriKeringananController::class, 'store'])->name('kategori-keringanan.store');

    Route::get('tagihan', [TagihanController::class, 'index'])->name('tagihan.index');
    Route::get('tagihan/data', [TagihanController::class, 'data'])->name('tagihan.data');
    Route::post('tagihan/{tagihan}/skema-cicilan', [TagihanController::class, 'buatSkemaCicilan'])->name('tagihan.skema-cicilan.store');
    Route::post('skema-cicilan/{skemaCicilan}/nominal', [TagihanController::class, 'simpanNominalCicilan'])->name('skema-cicilan.nominal.store');
    Route::post('tagihan/{tagihan}/catat-manual', [TagihanController::class, 'catatManualTagihan'])->name('tagihan.catat-manual');
    Route::post('cicilan/{cicilan}/catat-manual', [TagihanController::class, 'catatManualCicilan'])->name('cicilan.catat-manual');

    Route::get('pembayaran', [PembayaranController::class, 'index'])->name('pembayaran.index');
    Route::get('pembayaran/data', [PembayaranController::class, 'data'])->name('pembayaran.data');
    Route::post('pembayaran/{pembayaran}/verifikasi', [PembayaranController::class, 'verifikasi'])->name('pembayaran.verifikasi');

    Route::get('manual-payment', [ManualPaymentController::class, 'index'])->name('manual-payment.index');
    Route::post('manual-payment/{manualPaymentRequest}/approve', [ManualPaymentController::class, 'approve'])->name('manual-payment.approve');
    Route::post('manual-payment/{manualPaymentRequest}/reject', [ManualPaymentController::class, 'reject'])->name('manual-payment.reject');

    Route::get('virtual-account', [VirtualAccountController::class, 'index'])->name('virtual-account.index');
    Route::get('virtual-account/{siswa}/riwayat', [VirtualAccountController::class, 'riwayat'])->name('virtual-account.riwayat');
    Route::get('virtual-account/calon', [VirtualAccountController::class, 'calonGenerate'])->name('virtual-account.calon');
    Route::post('virtual-account/generate', [VirtualAccountController::class, 'generate'])->name('virtual-account.generate');
    Route::get('virtual-account/export', [VirtualAccountController::class, 'export'])->name('virtual-account.export');

    // Perangkat Mengajar (RPP / Modul Ajar)
    Route::get('rpp', [RppController::class, 'index'])->name('rpp.index');
    Route::post('rpp', [RppController::class, 'store'])->name('rpp.store');
    Route::get('rpp/{rpp}/download', [RppController::class, 'download'])->name('rpp.download');
    Route::put('rpp/{rpp}', [RppController::class, 'update'])->name('rpp.update');
    Route::delete('rpp/{rpp}', [RppController::class, 'destroy'])->name('rpp.destroy');
    Route::post('rpp/{rpp}/submit', [RppController::class, 'submit'])->name('rpp.submit');
    Route::post('rpp/{rpp}/verify', [RppController::class, 'verify'])->name('rpp.verify');

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
    Route::get('rapor/persetujuan', [\App\Http\Controllers\Lembaga\Rapor\PersetujuanController::class, 'index'])->name('rapor.persetujuan.index');
    Route::get('rapor/persetujuan/{pengajuanRapor}', [\App\Http\Controllers\Lembaga\Rapor\PersetujuanController::class, 'show'])->name('rapor.persetujuan.show');
    Route::post('rapor/persetujuan/{pengajuanRapor}/keputusan', [\App\Http\Controllers\Lembaga\Rapor\PersetujuanController::class, 'decision'])->name('rapor.persetujuan.decision');
    Route::get('rapor/persetujuan/{pengajuanRapor}/cetak/{siswa}', [\App\Http\Controllers\Lembaga\Rapor\PersetujuanController::class, 'cetak'])->name('rapor.persetujuan.cetak');

    Route::get('kenaikan-kelas', [KenaikanKelasController::class, 'index'])->name('kenaikan-kelas.index');
    Route::post('kenaikan-kelas', [KenaikanKelasController::class, 'store'])->name('kenaikan-kelas.store');

    Route::get('kasus', [AdminKasusController::class, 'index'])->name('kasus.index');
    Route::get('kasus/{kasus}/triase', [AdminKasusController::class, 'triase'])->name('kasus.triase');
    Route::post('kasus/{kasus}/assign-konselor', [AdminKasusController::class, 'assignKonselor'])->name('kasus.assign-konselor');
    Route::delete('kasus/{kasus}', [AdminKasusController::class, 'destroy'])->name('kasus.destroy');
    Route::post('kasus/{kasus}/pulihkan', [AdminKasusController::class, 'restore'])->name('kasus.restore');
    Route::get('kasus-log-akses', [KasusAksesLogController::class, 'index'])->name('kasus.log-akses');
    Route::get('kasus-terhapus', [KasusTerhapusController::class, 'index'])->name('kasus.terhapus');

    // Sarana & Prasarana (Sarpras)
    Route::prefix('sarpras')->name('sarpras.')->group(function () {
        Route::resource('gedung', \App\Http\Controllers\Lembaga\Sarpras\GedungController::class)->except(['show']);
        Route::resource('ruangan', \App\Http\Controllers\Lembaga\Sarpras\RuanganController::class);
        Route::resource('kategori', \App\Http\Controllers\Lembaga\Sarpras\KategoriAsetController::class)->only(['index', 'store', 'destroy']);
        Route::resource('aset', \App\Http\Controllers\Lembaga\Sarpras\AsetBarangController::class);
        Route::get('mutasi', [\App\Http\Controllers\Lembaga\Sarpras\MutasiAsetController::class, 'index'])->name('mutasi.index');
        Route::post('mutasi', [\App\Http\Controllers\Lembaga\Sarpras\MutasiAsetController::class, 'store'])->name('mutasi.store');
        Route::get('kir/{ruangan}', [\App\Http\Controllers\Lembaga\Sarpras\KirController::class, 'show'])->name('kir.show');
        Route::get('kir/{ruangan}/export-pdf', [\App\Http\Controllers\Lembaga\Sarpras\KirController::class, 'exportPdf'])->name('kir.export');
        Route::get('rekap-global', [\App\Http\Controllers\Yayasan\Sarpras\RekapAsetGlobalController::class, 'index'])->name('rekap-global');
    });

    // Pengadaan & LPJ Sarpras
    Route::prefix('pengadaan')->name('pengadaan.')->group(function () {
        // Portal Lembaga
        Route::resource('proposal', \App\Http\Controllers\Lembaga\Pengadaan\PengajuanPengadaanController::class);
        Route::post('proposal/{proposal}/submit', [\App\Http\Controllers\Lembaga\Pengadaan\PengajuanPengadaanController::class, 'submit'])->name('proposal.submit');
        Route::get('lpj/{proposal}/create', [\App\Http\Controllers\Lembaga\Pengadaan\LpjPengadaanController::class, 'create'])->name('lpj.create');
        Route::post('lpj/{proposal}', [\App\Http\Controllers\Lembaga\Pengadaan\LpjPengadaanController::class, 'store'])->name('lpj.store');
        Route::get('lpj/{lpj}/staging-inventory', [\App\Http\Controllers\Lembaga\Pengadaan\LpjPengadaanController::class, 'stagingInventory'])->name('lpj.staging-inventory');
        Route::post('lpj/{lpj}/convert-inventory', [\App\Http\Controllers\Lembaga\Pengadaan\LpjPengadaanController::class, 'convertInventory'])->name('lpj.convert-inventory');

        // Portal Yayasan & Approval
        Route::get('inbox', [\App\Http\Controllers\Yayasan\Pengadaan\ApprovalPengadaanController::class, 'index'])->name('inbox.index');
        Route::get('inbox/{proposal}', [\App\Http\Controllers\Yayasan\Pengadaan\ApprovalPengadaanController::class, 'review'])->name('inbox.review');
        Route::post('inbox/{proposal}/decision', [\App\Http\Controllers\Yayasan\Pengadaan\ApprovalPengadaanController::class, 'decision'])->name('inbox.decision');
        Route::get('disbursement', [\App\Http\Controllers\Yayasan\Pengadaan\DisbursementPengadaanController::class, 'index'])->name('disbursement.index');
        Route::post('disbursement/{proposal}', [\App\Http\Controllers\Yayasan\Pengadaan\DisbursementPengadaanController::class, 'store'])->name('disbursement.store');
        Route::get('audit-lpj', [\App\Http\Controllers\Yayasan\Pengadaan\AuditLpjController::class, 'index'])->name('audit-lpj.index');
        Route::get('audit-lpj/{lpj}', [\App\Http\Controllers\Yayasan\Pengadaan\AuditLpjController::class, 'show'])->name('audit-lpj.show');
        Route::post('audit-lpj/{lpj}/verify', [\App\Http\Controllers\Yayasan\Pengadaan\AuditLpjController::class, 'verify'])->name('audit-lpj.verify');
    });
});

// Orang tua accounts have no lembaga_id of their own, so implicit route-model binding's
// default TenantScope-applied lookup would 404 on {kasus} before the controller's own
// isSubmitter/isKontakUtama/kasus.triase authorization logic ever runs. Bind explicitly,
// bypassing the tenant scope; real authorization stays inside each controller action.
Route::bind('kasus', function ($value) {
    return \App\Models\Kasus::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
        ->withTrashed()
        ->findOrFail($value);
});

Route::middleware(['auth', 'verified'])->prefix('kasus')->name('kasus.')->group(function () {
    Route::get('/', [KasusController::class, 'index'])->name('index');
    Route::get('ajukan', [KasusController::class, 'create'])->name('create');
    Route::post('/', [KasusController::class, 'store'])->name('store');
    Route::get('{kasus}', [KasusController::class, 'show'])->name('show');
    Route::patch('{kasus}/consent/{kasusConsent}', [KasusConsentController::class, 'approve'])->name('consent.approve');
    Route::post('{kasus}/sesi', [KasusSesiController::class, 'store'])->name('sesi.store');
    Route::patch('{kasus}/sesi/{kasusSesi}', [KasusSesiController::class, 'updateStatus'])->name('sesi.update-status');
    Route::post('{kasus}/tugas', [KasusTugasController::class, 'store'])->name('tugas.store');
    Route::post('{kasus}/tugas/preview', [KasusTugasBatchPreviewController::class, 'preview'])->name('tugas.preview');
    Route::post('{kasus}/tugas/{kasusTugas}/submission', [KasusTugasSubmissionController::class, 'store'])->name('tugas.submission.store');
    Route::patch('{kasus}/tugas/{kasusTugas}/submission/{kasusTugasSubmission}/review', [KasusTugasSubmissionController::class, 'review'])->name('tugas.submission.review');
    Route::get('{kasus}/tugas/{kasusTugas}/submission/{kasusTugasSubmission}/lampiran', [KasusTugasSubmissionController::class, 'download'])->name('tugas.submission.lampiran');
    Route::patch('{kasus}/tugas/{kasusTugas}/selesai', [KasusTugasController::class, 'markSelesai'])->name('tugas.selesai');
    Route::post('{kasus}/evaluasi', [KasusEvaluasiController::class, 'store'])->name('evaluasi.store');
});

Route::middleware(['auth', 'verified'])->prefix('guru')->name('guru.')->group(function () {
    Route::get('jurnal-kbm', [JurnalKbmController::class, 'index'])->name('jurnal-kbm.index');
    Route::get('jurnal-kbm/{sesi}', [JurnalKbmController::class, 'show'])->name('jurnal-kbm.show');
    Route::put('jurnal-kbm/{sesi}', [JurnalKbmController::class, 'update'])->name('jurnal-kbm.update');
    Route::get('jurnal-kbm-rekap', [RekapKehadiranController::class, 'index'])->name('jurnal-kbm.rekap');

    Route::get('asesmen', [AsesmenController::class, 'index'])->name('asesmen.index');
    Route::get('asesmen/create', [AsesmenController::class, 'create'])->name('asesmen.create');
    Route::post('asesmen', [AsesmenController::class, 'store'])->name('asesmen.store');
    Route::get('asesmen/{asesmen}', [AsesmenController::class, 'show'])->name('asesmen.show');
    Route::put('asesmen/{asesmen}/nilai', [AsesmenController::class, 'updateNilai'])->name('asesmen.update-nilai');

    Route::get('komponen-penilaian', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'index'])->name('komponen-penilaian.index');
    Route::get('komponen-penilaian/create', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'create'])->name('komponen-penilaian.create');
    Route::post('komponen-penilaian', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'store'])->name('komponen-penilaian.store');
    Route::get('komponen-penilaian/{komponenPenilaian}/edit', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'edit'])->name('komponen-penilaian.edit');
    Route::put('komponen-penilaian/{komponenPenilaian}', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'update'])->name('komponen-penilaian.update');
    Route::delete('komponen-penilaian/{komponenPenilaian}', [App\Http\Controllers\Guru\KomponenPenilaianController::class, 'destroy'])->name('komponen-penilaian.destroy');

    Route::get('rapor', [GuruRaporController::class, 'index'])->name('rapor.catatan.index');
    Route::get('rapor/siswa/{siswa}', [GuruRaporController::class, 'edit'])->name('rapor.catatan.edit');
    Route::put('rapor/siswa/{siswa}', [GuruRaporController::class, 'update'])->name('rapor.catatan.update');
    Route::post('rapor/generate-narasi/{siswa}', [GuruRaporController::class, 'generateNarasi'])->name('rapor.catatan.generate-narasi');
    Route::post('rapor/ajukan', [GuruRaporController::class, 'ajukan'])->name('rapor.pengajuan.submit');
    Route::get('rapor/cetak/{siswa}', [GuruRaporController::class, 'cetak'])->name('rapor.cetak');
});
