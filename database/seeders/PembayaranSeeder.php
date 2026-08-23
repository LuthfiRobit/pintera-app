<?php
// database/seeders/PembayaranSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Domains\Keuangan\Models\Tagihan;
use Illuminate\Database\Seeder;

class PembayaranSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
            $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan@demo.test')->first();

            if ($diterima) {
                foreach (['pendaftaran', 'daftar_ulang'] as $kategori) {
                    $tagihan = Tagihan::where('pendaftaran_id', $diterima->id)->where('kategori', $kategori)->first();

                    if ($tagihan && ! Pembayaran::where('tagihan_id', $tagihan->id)->exists()) {
                        Pembayaran::create([
                            'tagihan_id' => $tagihan->id,
                            'sumber' => 'calon_siswa',
                            'metode' => 'transfer_manual',
                            'file_path' => 'demo/bukti-contoh.pdf',
                            'status' => 'menunggu_verifikasi',
                        ]);
                    }
                }
            }

            if ($cicilanDemo) {
                $tagihan = Tagihan::where('pendaftaran_id', $cicilanDemo->id)->where('kategori', 'daftar_ulang')->first();
                $termin1 = $tagihan?->skemaCicilan?->cicilan()->where('urutan', 1)->first();

                if ($termin1 && ! Pembayaran::where('cicilan_id', $termin1->id)->exists()) {
                    Pembayaran::create([
                        'cicilan_id' => $termin1->id,
                        'sumber' => 'calon_siswa',
                        'metode' => 'transfer_manual',
                        'file_path' => 'demo/bukti-contoh.pdf',
                        'status' => 'menunggu_verifikasi',
                    ]);
                }
            }
        }
    }
}
