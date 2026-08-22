<?php

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

    require base_path('routes/admin/sarpras.php');

    require base_path('routes/admin/pengadaan.php');
    require base_path('routes/admin/kehadiran-sdm.php');
});
