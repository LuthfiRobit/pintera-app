<?php

use App\Domains\Identity\Models\Person;
use App\Domains\Keuangan\Models\BriVirtualAccount;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\ManualPaymentRequest;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\PembayaranTagihan;
use App\Domains\Keuangan\Models\SiswaKeringanan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Models\Wallet;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo "=== PREPARING TEST DATA FOR BROWSER VERIFICATION ===\n";

// Ensure Yayasan & Lembaga exist
$yayasan = Yayasan::first() ?? Yayasan::create(['nama' => 'Yayasan Pendidikan Pintera', 'status' => 'aktif']);
$lembaga = Lembaga::first() ?? Lembaga::create([
    'nama' => 'SMA Pintera 1',
    'yayasan_id' => $yayasan->id,
    'jenjang' => 'SMA',
    'npsn' => '12345678',
    'bentuk_pendidikan' => 'SMA',
    'status_sekolah' => 'Swasta',
    'is_active' => true,
]);

$tahunAjaran = TahunAjaran::firstOrCreate(
    ['nama' => '2026/2027', 'lembaga_id' => $lembaga->id],
    ['is_active' => true, 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']
);

$kelas = Kelas::firstOrCreate(
    ['nama' => 'X-A', 'lembaga_id' => $lembaga->id],
    ['tingkat' => '10', 'tahun_ajaran_id' => $tahunAjaran->id]
);

// 1. Admin Bendahara Lembaga
$bendahara = User::updateOrCreate(
    ['email' => 'bendahara.test@pintera.id'],
    [
        'name' => 'Bendahara Test',
        'password' => Hash::make('password'),
        'lembaga_id' => $lembaga->id,
        'email_verified_at' => now(),
    ]
);
$bRole = Role::where('name', 'bendahara_lembaga')->first();
if ($bRole && ! $bendahara->hasRole('bendahara_lembaga')) {
    $bendahara->assignRole('bendahara_lembaga');
}

// 2. Admin Yayasan
$adminYayasan = User::updateOrCreate(
    ['email' => 'yayasan.test@pintera.id'],
    [
        'name' => 'Admin Yayasan Test',
        'password' => Hash::make('password'),
        'yayasan_id' => $yayasan->id,
        'email_verified_at' => now(),
    ]
);
$yRole = Role::where('name', 'like', '%yayasan%')->first();
if ($yRole && ! $adminYayasan->hasRole($yRole->name)) {
    $adminYayasan->assignRole($yRole->name);
}

// 3. Orang Tua User + Person + OrangTua + Siswa + Wallet + Tagihan
$userOrtu = User::updateOrCreate(
    ['email' => 'orangtua.test@pintera.id'],
    [
        'name' => 'Budi Santoso (Orang Tua)',
        'password' => Hash::make('password'),
        'lembaga_id' => $lembaga->id,
        'email_verified_at' => now(),
    ]
);
$oRole = Role::where('name', 'orang_tua')->first();
if ($oRole && ! $userOrtu->hasRole('orang_tua')) {
    $userOrtu->assignRole('orang_tua');
}

$personOrtu = Person::where('yayasan_id', $yayasan->id)->where('user_id', $userOrtu->id)->first()
    ?? Person::create([
        'user_id' => $userOrtu->id,
        'yayasan_id' => $yayasan->id,
        'nama_lengkap' => 'Budi Santoso',
        'nik' => '3201123456780001',
        'no_hp' => '081234567890',
        'email' => 'orangtua.test@pintera.id',
    ]);

$orangTua = OrangTua::firstOrCreate(
    ['person_id' => $personOrtu->id],
    ['pekerjaan' => 'Wiraswasta']
);

$personSiswa = Person::where('yayasan_id', $yayasan->id)->where('nama_lengkap', 'Ahmad Santoso')->first()
    ?? Person::create([
        'yayasan_id' => $yayasan->id,
        'nama_lengkap' => 'Ahmad Santoso',
        'nik' => '3201123456780002',
        'no_hp' => '081234567891',
    ]);

$siswa = Siswa::firstOrCreate(
    ['person_id' => $personSiswa->id],
    [
        'nama_lengkap' => 'Ahmad Santoso',
        'lembaga_id' => $lembaga->id,
        'nis' => '10001',
        'nisn' => '0010001001',
        'kelas_id' => $kelas->id,
        'status' => 'aktif',
    ]
);

if (! $siswa->orangTua()->where('orang_tua.id', $orangTua->id)->exists()) {
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
}

$wallet = Wallet::firstOrCreate(
    ['siswa_id' => $siswa->id],
    ['balance' => 1500000, 'va_number' => '1288800012345678']
);
$wallet->update(['balance' => 1500000, 'va_number' => '1288800012345678']);

// Seed BRI Permanent Virtual Account for Admin VA List
BriVirtualAccount::firstOrCreate(
    ['wallet_id' => $wallet->id, 'va_type' => 'WALLET_PERMANENT'],
    [
        'va_number' => '1288800012345678',
        'status' => 'PERMANENT',
        'amount' => 0,
        'customer_name' => 'Ahmad Santoso',
    ]
);

$jenisTagihan = JenisTagihan::firstOrCreate(
    ['nama' => 'SPP Reguler Bulanan', 'lembaga_id' => $lembaga->id],
    [
        'kategori' => 'spp',
        'mode' => 'otomatis',
        'tipe' => 'bulanan',
        'default_amount' => 500000,
        'is_active' => true,
        'hari_generate' => 1,
        'jatuh_tempo_hari' => 10,
    ]
);

$jenisTagihanUangPangkal = JenisTagihan::firstOrCreate(
    ['nama' => 'Uang Pangkal Gedung', 'lembaga_id' => $lembaga->id],
    [
        'kategori' => 'tahunan',
        'mode' => 'manual',
        'tipe' => 'sekali',
        'default_amount' => 2500000,
        'is_active' => true,
    ]
);

$tagihan1 = Tagihan::updateOrCreate(
    ['jenis_tagihan_id' => $jenisTagihan->id, 'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'billing_period' => '2026-09'],
    [
        'lembaga_id' => $lembaga->id,
        'person_id' => $siswa->person_id,
        'total_tagihan' => 500000,
        'discount_amount' => 50000,
        'net_amount' => 450000,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
        'jatuh_tempo' => now()->addDays(5),
        'perlu_ditinjau_ulang' => false,
    ]
);

// Tagihan sengaja diflag perlu_ditinjau_ulang secara langsung via seed untuk verifikasi UI
$tagihanDitinjau = Tagihan::updateOrCreate(
    ['jenis_tagihan_id' => $jenisTagihan->id, 'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'billing_period' => '2026-08'],
    [
        'lembaga_id' => $lembaga->id,
        'person_id' => $siswa->person_id,
        'total_tagihan' => 500000,
        'discount_amount' => 100000,
        'net_amount' => 400000,
        'paid_amount' => 300000,
        'status' => 'sebagian',
        'jatuh_tempo' => now()->subDays(10),
        'perlu_ditinjau_ulang' => true,
        'alasan_perlu_ditinjau' => 'Hasil recalculate net_amount (Rp 250.000) lebih kecil dari pembayaran yang sudah masuk (Rp 300.000).',
    ]
);

// Tagihan Lunas untuk Riwayat & Kwitansi
$tagihanLunas = Tagihan::updateOrCreate(
    ['jenis_tagihan_id' => $jenisTagihan->id, 'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'billing_period' => '2026-07'],
    [
        'lembaga_id' => $lembaga->id,
        'person_id' => $siswa->person_id,
        'total_tagihan' => 500000,
        'discount_amount' => 50000,
        'net_amount' => 450000,
        'paid_amount' => 450000,
        'status' => 'lunas',
        'jatuh_tempo' => '2026-07-10',
        'perlu_ditinjau_ulang' => false,
    ]
);

$pembayaranLunas = Pembayaran::firstOrCreate(
    ['tagihan_id' => $tagihanLunas->id, 'status' => 'lunas'],
    [
        'siswa_id' => $siswa->id,
        'wallet_id' => $wallet->id,
        'sumber' => 'admin',
        'metode' => 'transfer_manual',
        'amount' => 450000,
        'identifier_method' => 'manual',
        'diverifikasi_pada' => now()->subMonth(),
    ]
);

PembayaranTagihan::firstOrCreate(
    ['pembayaran_id' => $pembayaranLunas->id, 'tagihan_id' => $tagihanLunas->id],
    ['amount_allocated' => 450000]
);

// Pending Manual Payment Request for Admin Manual Payment Approval
$pembayaranPendingManual = Pembayaran::firstOrCreate(
    ['tagihan_id' => $tagihan1->id, 'sumber' => 'orang_tua', 'status' => 'menunggu_verifikasi'],
    [
        'siswa_id' => $siswa->id,
        'metode' => 'transfer_manual',
        'amount' => 450000,
        'file_path' => 'bukti-transfer/sample.jpg',
        'identifier_method' => 'manual',
    ]
);

ManualPaymentRequest::firstOrCreate(
    ['pembayaran_id' => $pembayaranPendingManual->id],
    [
        'requested_by' => $userOrtu->id,
        'amount' => 450000,
        'transfer_date' => now()->format('Y-m-d'),
        'bank_name' => 'Bank Mandiri',
        'account_name' => 'Budi Santoso',
        'account_number' => '1400012345678',
        'transfer_proof_path' => 'bukti-transfer/sample.jpg',
        'status' => 'PENDING',
    ]
);

$kategoriKeringanan = KategoriKeringanan::firstOrCreate(
    ['nama' => 'Tahfidz 30 Juz', 'lembaga_id' => $lembaga->id],
    ['bisa_digabung' => true, 'is_active' => true]
);

SiswaKeringanan::firstOrCreate(
    ['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategoriKeringanan->id],
    ['berlaku_dari' => '2026-07-01', 'berlaku_sampai' => '2027-06-30']
);

// Notifications for OrangTua user
$userOrtu->notifications()->delete();
$userOrtu->notifications()->create([
    'id' => Str::uuid()->toString(),
    'type' => 'App\Notifications\Finance\TagihanDiterbitkanNotification',
    'data' => [
        'title' => 'Tagihan SPP September 2026',
        'message' => 'Tagihan SPP sebesar Rp 450.000 telah diterbitkan.',
        'tagihan_id' => $tagihan1->id,
        'action_url' => route('keuangan.tagihan.show', $tagihan1->id),
    ],
    'read_at' => null,
]);

$userOrtu->notifications()->create([
    'id' => Str::uuid()->toString(),
    'type' => 'App\Notifications\GeneralSystemNotification',
    'data' => [
        'title' => 'Pengumuman Libur Nasional',
        'message' => 'Sekolah diliburkan pada tanggal merah yang akan datang.',
    ],
    'read_at' => null,
]);

echo "SUCCESS! Test users and full finance fixtures created:\n";
echo "1. Bendahara: bendahara.test@pintera.id / password\n";
echo "2. Orang Tua: orangtua.test@pintera.id / password\n";
echo "3. Yayasan: yayasan.test@pintera.id / password\n";
echo 'Lembaga: '.$lembaga->nama.' (ID: '.$lembaga->id.")\n";
