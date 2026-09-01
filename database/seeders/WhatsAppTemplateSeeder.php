<?php

namespace Database\Seeders;

use App\Models\WhatsAppTemplate;
use Illuminate\Database\Seeder;

class WhatsAppTemplateSeeder extends Seeder
{
    public function run(): void
    {
        WhatsAppTemplate::firstOrCreate(
            ['kode' => 'consent_diminta'],
            [
                'isi_template' => 'Yth. Orang Tua {nama_siswa}, konselor {nama_konselor} telah dipilih untuk mendampingi. Mohon berikan persetujuan Anda melalui portal Pintera.',
                'deskripsi' => 'Dikirim saat admin memilih konselor untuk kasus, meminta persetujuan orang tua. Placeholder tersedia: {nama_siswa}, {nama_konselor}.',
            ]
        );

        WhatsAppTemplate::firstOrCreate(
            ['kode' => 'reminder_sesi_h1'],
            [
                'isi_template' => 'Pengingat: sesi pendampingan untuk {nama_siswa} dijadwalkan besok, {tanggal_sesi} di {lokasi_sesi}.',
                'deskripsi' => 'Dikirim H-1 sebelum sesi pendampingan terjadwal. Placeholder tersedia: {nama_siswa}, {tanggal_sesi}, {lokasi_sesi}.',
            ]
        );

        WhatsAppTemplate::firstOrCreate(['kode' => 'tagihan_baru'], [
            'isi_template' => 'Tagihan {jenis_tagihan} periode {billing_period} sebesar Rp{net_amount} telah diterbitkan, jatuh tempo {jatuh_tempo}.',
            'deskripsi' => 'Dikirim saat tagihan baru diterbitkan untuk siswa.',
        ]);

        WhatsAppTemplate::firstOrCreate(['kode' => 'tagihan_direvisi'], [
            'isi_template' => 'Tagihan {jenis_tagihan} telah direvisi dari Rp{net_amount_lama} menjadi Rp{net_amount_baru}.',
            'deskripsi' => 'Dikirim saat nominal tagihan siswa direvisi otomatis oleh sistem.',
        ]);

        WhatsAppTemplate::firstOrCreate(['kode' => 'pembayaran_berhasil'], [
            'isi_template' => 'Pembayaran {tagihan} sebesar Rp{amount} berhasil melalui {metode}.',
            'deskripsi' => 'Dikirim saat pembayaran tagihan berhasil diverifikasi.',
        ]);

        WhatsAppTemplate::firstOrCreate(['kode' => 'transfer_manual_disetujui'], [
            'isi_template' => 'Bukti transfer pembayaran Anda telah diverifikasi dan disetujui.',
            'deskripsi' => 'Dikirim saat admin menyetujui transfer manual.',
        ]);

        WhatsAppTemplate::firstOrCreate(['kode' => 'transfer_manual_ditolak'], [
            'isi_template' => 'Bukti transfer pembayaran Anda ditolak: {rejection_reason}. Silakan ajukan ulang.',
            'deskripsi' => 'Dikirim saat admin menolak transfer manual.',
        ]);

        WhatsAppTemplate::firstOrCreate(['kode' => 'saldo_tidak_cukup'], [
            'isi_template' => 'Saldo wallet Anda tidak mencukupi untuk pembayaran {tagihan}. Kekurangan: Rp{selisih}.',
            'deskripsi' => 'Dikirim saat auto-allocation gagal melunasi tagihan karena saldo kurang.',
        ]);

        WhatsAppTemplate::firstOrCreate(['kode' => 'tagihan_jatuh_tempo'], [
            'isi_template' => 'Tagihan {jenis_tagihan} akan jatuh tempo pada {jatuh_tempo}. Segera lakukan pembayaran.',
            'deskripsi' => 'Dikirim H-3 dan H-1 sebelum tanggal jatuh tempo tagihan.',
        ]);
    }
}
