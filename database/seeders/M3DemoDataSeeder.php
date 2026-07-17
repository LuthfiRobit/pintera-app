<?php

namespace Database\Seeders;

use App\Models\CalonMurid;
use App\Models\DokumenPendaftaran;
use App\Models\GelombangPpdb;
use App\Models\HasilSeleksi;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\SeleksiPpdb;
use App\Models\SkPpdb;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data demo M3 (Verifikasi & Keputusan): untuk setiap lembaga demo (SMP & SMA,
 * dibuat oleh LembagaSeeder), menambahkan sebaran pendaftaran yang mencakup
 * setiap kondisi yang perlu diuji manual: menunggu verifikasi dengan dokumen
 * campuran (sebagian terverifikasi, sebagian ditolak, sebagian belum), diterima
 * dengan nilai terisi dan SK sudah terbit, dan ditolak. Supaya M3 langsung bisa
 * diuji manual tanpa setup tambahan setelah migrate:fresh --seed.
 */
class M3DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['20223344', '20223355'] as $npsn) {
            $lembaga = Lembaga::where('npsn', $npsn)->first();

            if (! $lembaga) {
                continue;
            }

            $this->seedUntukLembaga($lembaga);
        }
    }

    private function seedUntukLembaga(Lembaga $lembaga): void
    {
        // Guards the whole method as a single idempotency check, rather than converting every
        // ::create() below to firstOrCreate/updateOrCreate individually: hasil_seleksi and
        // sk_ppdb both have real unique constraints (pendaftaran_id+seleksi_ppdb_id, and
        // lembaga_id+nomor_sk respectively), so a second unconditional ::create() call for
        // either would throw a QueryException, not silently duplicate. One early-return here
        // is simpler and safer than getting every individual sub-step's idempotency right.
        if (Pendaftaran::where('lembaga_id', $lembaga->id)->where('kode_pendaftaran', 'like', 'REG-DEMO-'.$lembaga->id.'-%')->exists()) {
            return;
        }

        $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

        if (! $tahunAjaranAktif) {
            return;
        }

        $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)
            ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
            ->where('nama', 'Reguler')
            ->first();

        $gelombang = GelombangPpdb::where('lembaga_id', $lembaga->id)
            ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
            ->where('tanggal_buka', '<=', now())
            ->where('tanggal_tutup', '>=', now())
            ->first();

        if (! $jalur || ! $gelombang) {
            return;
        }

        $staf = User::where('lembaga_id', $lembaga->id)->first();
        $syaratDokumen = $jalur->dokumenSyarat;
        $seleksiList = SeleksiPpdb::where('jalur_ppdb_id', $jalur->id)->where('gelombang_ppdb_id', $gelombang->id)->get();

        // 1. Menunggu verifikasi — dokumen campuran (terverifikasi, ditolak, belum diverifikasi).
        $menunggu = $this->buatPendaftaran($lembaga, $tahunAjaranAktif, $jalur, $gelombang, 'Calon Menunggu Verifikasi', 'wali.menunggu@example.test');
        foreach ($syaratDokumen as $index => $syarat) {
            $status = match ($index % 3) {
                0 => 'diterima',
                1 => 'ditolak',
                default => 'belum_diverifikasi',
            };
            DokumenPendaftaran::create([
                'pendaftaran_id' => $menunggu->id, 'dokumen_syarat_ppdb_id' => $syarat->id,
                'file_path' => 'demo/dokumen-contoh.pdf', 'nama_file_asli' => $syarat->nama_dokumen.'.pdf',
                'mime_type' => 'application/pdf', 'ukuran_bytes' => 102400,
                'status_verifikasi' => $status,
                'catatan_verifikasi' => $status === 'ditolak' ? 'Contoh catatan: berkas kurang jelas, mohon diunggah ulang.' : null,
                'diverifikasi_oleh_user_id' => $status !== 'belum_diverifikasi' ? $staf?->id : null,
                'diverifikasi_pada' => $status !== 'belum_diverifikasi' ? now() : null,
            ]);
        }

        // 2. Diterima — dokumen lengkap, nilai terisi, akan dicakup SK di bawah.
        $diterima = $this->buatPendaftaran($lembaga, $tahunAjaranAktif, $jalur, $gelombang, 'Calon Diterima', 'wali.diterima@example.test');
        foreach ($syaratDokumen as $syarat) {
            DokumenPendaftaran::create([
                'pendaftaran_id' => $diterima->id, 'dokumen_syarat_ppdb_id' => $syarat->id,
                'file_path' => 'demo/dokumen-contoh.pdf', 'nama_file_asli' => $syarat->nama_dokumen.'.pdf',
                'mime_type' => 'application/pdf', 'ukuran_bytes' => 102400,
                'status_verifikasi' => 'diterima', 'diverifikasi_oleh_user_id' => $staf?->id, 'diverifikasi_pada' => now(),
            ]);
        }
        foreach ($seleksiList as $seleksi) {
            HasilSeleksi::create([
                'pendaftaran_id' => $diterima->id, 'seleksi_ppdb_id' => $seleksi->id,
                'nilai' => random_int(75, 95), 'dinilai_oleh_user_id' => $staf?->id, 'dinilai_pada' => now(),
            ]);
        }
        $diterima->update([
            'status' => 'diterima', 'catatan_keputusan' => 'Nilai dan kelengkapan dokumen memenuhi syarat.',
            'ditetapkan_oleh_user_id' => $staf?->id, 'ditetapkan_pada' => now(),
        ]);

        // 3. Ditolak.
        $ditolak = $this->buatPendaftaran($lembaga, $tahunAjaranAktif, $jalur, $gelombang, 'Calon Ditolak', 'wali.ditolak@example.test');
        foreach ($seleksiList as $seleksi) {
            HasilSeleksi::create([
                'pendaftaran_id' => $ditolak->id, 'seleksi_ppdb_id' => $seleksi->id,
                'nilai' => random_int(30, 55), 'dinilai_oleh_user_id' => $staf?->id, 'dinilai_pada' => now(),
            ]);
        }
        $ditolak->update([
            'status' => 'ditolak', 'catatan_keputusan' => 'Nilai belum memenuhi kriteria kelulusan minimum.',
            'ditetapkan_oleh_user_id' => $staf?->id, 'ditetapkan_pada' => now(),
        ]);

        // Terbitkan satu SK mencakup kedua pendaftaran yang sudah final (diterima + ditolak),
        // supaya "download bukti dengan referensi SK" langsung bisa diuji di halaman publik.
        if ($staf) {
            $sk = SkPpdb::create([
                'gelombang_ppdb_id' => $gelombang->id, 'lembaga_id' => $lembaga->id,
                'nomor_sk' => '421.3/SK-PPDB.DEMO-'.$lembaga->id.'/2026',
                'tanggal_terbit' => now()->toDateString(),
                'diterbitkan_oleh_user_id' => $staf->id, 'file_path' => 'demo/sk-contoh.pdf',
            ]);
            Pendaftaran::whereIn('id', [$diterima->id, $ditolak->id])->update(['sk_ppdb_id' => $sk->id]);
        }
    }

    private function buatPendaftaran(
        Lembaga $lembaga,
        TahunAjaran $tahunAjaran,
        JalurPpdb $jalur,
        GelombangPpdb $gelombang,
        string $namaCalon,
        string $email
    ): Pendaftaran {
        // The seedUntukLembaga() guard above ensures this whole method only ever runs once
        // per lembaga across any number of DatabaseSeeder runs, so plain create() here is
        // safe — no need for firstOrCreate's search-key complexity for a single-shot insert.
        $nik = (string) random_int(3200000000000000, 3299999999999999);

        $calonMurid = CalonMurid::create([
            'yayasan_id' => $lembaga->yayasan_id,
            'nik' => $nik,
            'nama_lengkap' => $namaCalon.' ('.$lembaga->nama.')',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => now()->subYears(13)->toDateString(),
            'agama' => 'Islam',
        ]);

        return Pendaftaran::create([
            'calon_murid_id' => $calonMurid->id,
            'lembaga_id' => $lembaga->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'jalur_ppdb_id' => $jalur->id,
            'gelombang_ppdb_id' => $gelombang->id,
            'kode_pendaftaran' => 'REG-DEMO-'.$lembaga->id.'-'.random_int(10000, 99999),
            'email_pendaftaran' => $email,
            'submitted_at' => now()->subDays(random_int(1, 5)),
        ]);
    }
}
