<?php

namespace Database\Seeders;

use App\Models\CalonMurid;
use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Models\TagihanItem;
use App\Services\PembayaranService;
use Illuminate\Database\Seeder;

/**
 * Standalone seeder for manually inspecting the "Verifikasi Pembayaran" admin
 * queue -- NOT registered in DatabaseSeeder, run explicitly with
 * `php artisan db:seed --class=PembayaranDemoSeeder` when you want pending
 * payments to look at without repeating the full portal upload flow by hand.
 *
 * Produces, for the SMP demo lembaga (NPSN 20223344):
 *  - "Calon Diterima" (already status=diterima, from M3DemoDataSeeder) gets TWO
 *    separate tagihan (Pendaftaran + Daftar Ulang), each with its own pending
 *    payment -- same candidate, two distinct rows by design, to check whether
 *    that reads as "duplicate" in the queue list.
 *  - A second fresh candidate gets a 3x cicilan skema with termin 1 pending.
 */
class PembayaranDemoSeeder extends Seeder
{
    public function run(): void
    {
        $lembaga = Lembaga::where('npsn', '20223344')->first();

        if (! $lembaga) {
            $this->command?->warn('Lembaga SMP demo (NPSN 20223344) tidak ditemukan -- jalankan DatabaseSeeder dulu.');

            return;
        }

        $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)->where('nama', 'Reguler')->first();

        $jenisPendaftaran = JenisTagihan::firstOrCreate(
            ['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran'],
            ['bisa_dicicil' => false]
        );
        $jenisDaftarUlang = JenisTagihan::firstOrCreate(
            ['lembaga_id' => $lembaga->id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang'],
            ['bisa_dicicil' => true, 'maks_cicilan' => 3]
        );

        if ($jalur) {
            NominalTagihanJalur::firstOrCreate(
                ['jenis_tagihan_id' => $jenisPendaftaran->id, 'jalur_ppdb_id' => $jalur->id],
                ['nominal' => 150000]
            );
            NominalTagihanJalur::firstOrCreate(
                ['jenis_tagihan_id' => $jenisDaftarUlang->id, 'jalur_ppdb_id' => $jalur->id],
                ['nominal' => 900000]
            );
        }

        $this->seedDuaTagihanUntukCalonDiterima($lembaga, $jenisPendaftaran, $jenisDaftarUlang);
        $this->seedCicilanUntukKandidatBaru($lembaga, $jenisDaftarUlang);
    }

    private function seedDuaTagihanUntukCalonDiterima(Lembaga $lembaga, JenisTagihan $jenisPendaftaran, JenisTagihan $jenisDaftarUlang): void
    {
        $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)
            ->where('email_pendaftaran', 'wali.diterima@example.test')
            ->first();

        if (! $diterima) {
            $this->command?->warn('"Calon Diterima" (wali.diterima@example.test) tidak ditemukan -- jalankan M3DemoDataSeeder dulu.');

            return;
        }

        if (! Tagihan::where('pendaftaran_id', $diterima->id)->where('kategori', 'pendaftaran')->exists()) {
            $tagihan = Tagihan::create([
                'pendaftaran_id' => $diterima->id, 'kategori' => 'pendaftaran',
                'total_tagihan' => 150000, 'status' => 'belum_bayar',
            ]);
            TagihanItem::create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisPendaftaran->id, 'jumlah' => 150000]);
            Pembayaran::create([
                'tagihan_id' => $tagihan->id, 'sumber' => 'calon_siswa',
                'metode' => 'transfer_manual', 'file_path' => 'demo/bukti-contoh.pdf', 'status' => 'menunggu_verifikasi',
            ]);
        }

        if (! Tagihan::where('pendaftaran_id', $diterima->id)->where('kategori', 'daftar_ulang')->exists()) {
            $tagihan = Tagihan::create([
                'pendaftaran_id' => $diterima->id, 'kategori' => 'daftar_ulang',
                'total_tagihan' => 900000, 'status' => 'belum_bayar',
            ]);
            TagihanItem::create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisDaftarUlang->id, 'jumlah' => 900000]);
            Pembayaran::create([
                'tagihan_id' => $tagihan->id, 'sumber' => 'calon_siswa',
                'metode' => 'transfer_manual', 'file_path' => 'demo/bukti-contoh.pdf', 'status' => 'menunggu_verifikasi',
            ]);
        }
    }

    private function seedCicilanUntukKandidatBaru(Lembaga $lembaga, JenisTagihan $jenisDaftarUlang): void
    {
        $kodePendaftaran = 'REG-PEMBAYARAN-DEMO-'.$lembaga->id;

        $pendaftaran = Pendaftaran::where('lembaga_id', $lembaga->id)
            ->where('kode_pendaftaran', $kodePendaftaran)
            ->first();

        if (! $pendaftaran) {
            $tahunAjaran = \App\Models\TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
            $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)->where('nama', 'Reguler')->first();
            $gelombang = \App\Models\GelombangPpdb::where('lembaga_id', $lembaga->id)
                ->where('tahun_ajaran_id', $tahunAjaran?->id)
                ->where('tanggal_buka', '<=', now())->where('tanggal_tutup', '>=', now())
                ->first();

            if (! $tahunAjaran || ! $jalur || ! $gelombang) {
                $this->command?->warn('Data jalur/gelombang/tahun ajaran SMP tidak lengkap -- lewati seed kandidat cicilan.');

                return;
            }

            $calonMurid = CalonMurid::create([
                'yayasan_id' => $lembaga->yayasan_id,
                'nik' => (string) random_int(3200000000000000, 3299999999999999),
                'nama_lengkap' => 'Calon Cicilan Demo ('.$lembaga->nama.')',
                'jenis_kelamin' => 'P',
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => now()->subYears(13)->toDateString(),
                'agama' => 'Islam',
            ]);

            $pendaftaran = Pendaftaran::create([
                'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id,
                'tahun_ajaran_id' => $tahunAjaran->id, 'jalur_ppdb_id' => $jalur->id,
                'gelombang_ppdb_id' => $gelombang->id, 'kode_pendaftaran' => $kodePendaftaran,
                'email_pendaftaran' => 'wali.cicilan-demo@example.test', 'submitted_at' => now()->subDays(2),
                'status' => 'diterima', 'ditetapkan_pada' => now()->subDay(),
            ]);
        }

        if (Tagihan::where('pendaftaran_id', $pendaftaran->id)->where('kategori', 'daftar_ulang')->exists()) {
            return;
        }

        $tagihan = Tagihan::create([
            'pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang',
            'total_tagihan' => 900000, 'status' => 'belum_bayar',
        ]);
        TagihanItem::create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisDaftarUlang->id, 'jumlah' => 900000]);

        $skema = app(PembayaranService::class)->buatSkemaCicilan($tagihan, 3, 'calon_siswa');
        $termin1 = $skema->cicilan()->where('urutan', 1)->first();

        Pembayaran::create([
            'cicilan_id' => $termin1->id, 'sumber' => 'calon_siswa',
            'metode' => 'transfer_manual', 'file_path' => 'demo/bukti-contoh.pdf', 'status' => 'menunggu_verifikasi',
        ]);
    }
}
