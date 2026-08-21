<?php

namespace Database\Seeders;

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\TagihanItem;
use App\Models\User;
use App\Services\Finance\PaymentService;
use Illuminate\Database\Seeder;

/**
 * Data demo untuk mencoba Modul Keuangan lewat browser secara end-to-end.
 *
 * Dipanggil dari DatabaseSeeder::run() (posisi setelah OrangTuaKaryawanSeeder dan
 * PendampinganSeeder) untuk demo end-to-end otomatis setiap fresh seed. Bergantung pada
 * akun demo orang tua dari OrangTuaKaryawanSeeder + wallet otomatis dari StudentCreated event
 * -- keduanya sudah terjamin ada di titik ini karena urutan DatabaseSeeder.
 */
class KeuanganDemoSeeder extends Seeder
{
    public function run(): void
    {
        $demoParents = [
            ['nik' => '0000019901850001', 'email' => 'ortu@demo.test'],
            ['nik' => '0000019901850002', 'email' => 'ortu.kb@demo.test'],
            ['nik' => '0000019901850003', 'email' => 'ortu.tk@demo.test'],
        ];

        foreach ($demoParents as $demo) {
            $user = User::where('username', $demo['nik'])->first();

            if (! $user || ! $user->orangTua) {
                $this->command?->warn("Lewati: user demo {$demo['email']} (NIK {$demo['nik']}) tidak ditemukan (OrangTuaKaryawanSeeder mungkin dilewati di env non-local/testing).");

                continue;
            }

            $orangTua = $user->orangTua;
            $siswa = $orangTua->siswa()->first();

            if (! $siswa) {
                continue;
            }

            $this->seedForSiswa($siswa, $orangTua);
        }
    }

    private function seedForSiswa(Siswa $siswa, OrangTua $orangTua): void
    {
        $lembaga = Lembaga::find($siswa->lembaga_id);

        $jenisTagihan = JenisTagihan::firstOrCreate(
            ['lembaga_id' => $siswa->lembaga_id, 'nama' => 'SPP Bulanan (Demo)'],
            [
                'kategori' => 'spp',
                'bisa_dicicil' => false,
                'default_amount' => 350000,
                'mode' => 'manual',
                'hari_jatuh_tempo' => 10,
                'va_expire_hours' => 24,
                'is_active' => true,
            ]
        );

        // 1. Top-up wallet DULU, sebelum ada tagihan belum-bayar -- supaya
        // auto-allocation engine (kalau aktif) tidak langsung melunasi
        // tagihan demo di bawah sebelum sempat dicoba manual oleh user.
        $wallet = $siswa->wallet;
        if ($wallet && $wallet->balance < 500000) {
            $wallet->topup(500000 - $wallet->balance, null, 'Saldo awal demo Keuangan');
        }

        // 2. Tagihan bulan berjalan, BELUM BAYAR -- untuk dicoba checkout
        // (VA / QRIS / wallet / transfer manual) langsung dari browser.
        $this->buatTagihan($siswa, $jenisTagihan, now()->format('Y-m'), 350000, now()->addDays(10));

        // 3. Tagihan bulan lalu, SUDAH LUNAS via cash -- supaya halaman
        // Riwayat & Kwitansi PDF langsung ada isinya tanpa harus bayar dulu.
        $tagihanLunas = $this->buatTagihan($siswa, $jenisTagihan, now()->subMonth()->format('Y-m'), 350000, now()->subMonth()->addDays(10));
        if ($tagihanLunas->status !== 'lunas') {
            $kasir = $this->cariAdminKeuangan($lembaga) ?? $orangTua->user;
            app(PaymentService::class)->createCashPayment($siswa, collect([$tagihanLunas]), $kasir->id);
        }

        // 4. Tagihan 2 bulan lalu, MENUNGGU VERIFIKASI transfer manual --
        // supaya halaman verifikasi admin ("admin/manual-payment") juga
        // langsung ada antrean untuk dicoba.
        $tagihanVerifikasi = $this->buatTagihan($siswa, $jenisTagihan, now()->subMonths(2)->format('Y-m'), 350000, now()->subMonths(2)->addDays(10));
        if ($tagihanVerifikasi->pembayaran()->doesntExist()) {
            app(PaymentService::class)->createManualPayment($siswa, collect([$tagihanVerifikasi]), [
                'requested_by' => $orangTua->user_id,
                'amount' => 350000,
                'transfer_proof_path' => 'demo/bukti-transfer-placeholder.jpg',
                'bank_origin' => 'BCA',
                'transfer_date' => now()->subMonths(2)->addDays(3)->format('Y-m-d'),
            ]);
        }
    }

    private function buatTagihan(Siswa $siswa, JenisTagihan $jenisTagihan, string $periode, float $nominal, \DateTimeInterface $jatuhTempo): Tagihan
    {
        $tagihan = Tagihan::firstOrCreate(
            [
                'tagihable_type' => Siswa::class,
                'tagihable_id' => $siswa->id,
                'jenis_tagihan_id' => $jenisTagihan->id,
                'billing_period' => $periode,
            ],
            [
                'kategori' => 'spp',
                'source_trigger' => 'demo_seeder',
                'total_tagihan' => $nominal,
                'net_amount' => $nominal,
                'status' => 'belum_bayar',
                'jatuh_tempo' => $jatuhTempo,
            ]
        );

        TagihanItem::firstOrCreate(
            ['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisTagihan->id],
            ['jumlah' => $nominal]
        );

        return $tagihan;
    }

    private function cariAdminKeuangan(?Lembaga $lembaga): ?User
    {
        if (! $lembaga) {
            return null;
        }

        $kode = match ($lembaga->bentuk_pendidikan) {
            'KB' => 'kb',
            'TK' => 'tk',
            'SD' => 'sd',
            default => 'smp',
        };

        return User::where('email', "keuangan.{$kode}@demo.test")->first();
    }
}
