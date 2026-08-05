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
    }
}
