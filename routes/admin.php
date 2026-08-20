<?php

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
    require base_path('routes/admin/keuangan.php');
    require base_path('routes/admin/rpp.php');
    require base_path('routes/admin/penilaian-rapor.php');
    require base_path('routes/admin/kasus-admin.php');

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
