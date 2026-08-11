<?php
require 'd:/laragon/www/pintera-app/vendor/autoload.php';
$app = require_once 'd:/laragon/www/pintera-app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = App\Models\JenisTagihan::whereIn('kategori', ['pendaftaran','daftar_ulang'])->where('is_active', true)->count();
echo 'Active PPDB-kategori jenis_tagihan rows still is_active=true (expected, not itself a problem — the guard, not is_active, is what protects them now): '.$rows.PHP_EOL;
$spurious = App\Models\Tagihan::where('tagihable_type', App\Models\Siswa::class)->whereIn('kategori', ['pendaftaran','daftar_ulang'])->count();
echo 'Spurious Siswa-tagihable PPDB-kategori Tagihan rows (must be 0): '.$spurious.PHP_EOL;
