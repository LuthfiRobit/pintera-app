<?php

use App\Domains\Identity\Models\Person;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\KategoriKeringanan;
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
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_wali' => true, 'is_kontak_darurat' => true]);
}

$wallet = Wallet::firstOrCreate(
    ['siswa_id' => $siswa->id],
    ['balance' => 1500000, 'va_number' => '1288800012345678']
);
$wallet->update(['balance' => 1500000, 'va_number' => '1288800012345678']);

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

// Tagihan sengaja diflag perlu_ditinjau_ulang secara langsung via seed (bukan lewat
// RecalculateTagihanNominalAction) -- ini murni untuk menyiapkan state UI yang stabil
// untuk verifikasi browser, bukan untuk membuktikan logic recalculate itu sendiri
// (yang sudah punya test Pest tersendiri).
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

KategoriKeringanan::firstOrCreate(
    ['nama' => 'Tahfidz 30 Juz', 'lembaga_id' => $lembaga->id],
    ['bisa_digabung' => true, 'is_active' => true]
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

echo "SUCCESS! Test users created:\n";
echo "1. Bendahara: bendahara.test@pintera.id / password\n";
echo "2. Orang Tua: orangtua.test@pintera.id / password\n";
echo "3. Yayasan: yayasan.test@pintera.id / password\n";
echo 'Lembaga: '.$lembaga->nama.' (ID: '.$lembaga->id.")\n";
