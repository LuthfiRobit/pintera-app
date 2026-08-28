<?php

// database/seeders/RppSeeder.php

namespace Database\Seeders;

use App\Domains\Akademik\Enums\StatusRpp;
use App\Domains\Akademik\Models\Rpp;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Lembaga;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class RppSeeder extends Seeder
{
    /**
     * Menyiapkan contoh RPP/Modul Ajar di 3 status berbeda (draft, diajukan, disetujui)
     * supaya demo Perangkat Ajar tidak kosong. Kombinasi guru+kelas+mata_pelajaran+semester
     * diambil langsung dari JadwalPelajaran yang sudah ter-seed, sesuai kombinasi mengajar
     * yang nyata (bukan asal tebak) -- mirror validasi RppController::store().
     */
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            $jadwalList = JadwalPelajaran::whereHas('kelas', fn ($q) => $q->where('lembaga_id', $lembaga->id))
                ->whereNotNull('mata_pelajaran_id')
                ->with(['kelas', 'guru'])
                ->get()
                ->unique(fn ($jadwal) => "{$jadwal->guru_id}-{$jadwal->kelas_id}-{$jadwal->mata_pelajaran_id}-{$jadwal->semester_id}")
                ->values();

            if ($jadwalList->isEmpty()) {
                continue;
            }

            $contoh = [
                ['topik' => 'Penjumlahan dan Pengurangan Bilangan Cacah', 'status' => StatusRpp::Draft],
                ['topik' => 'Mengenal Bentuk Bangun Datar Sederhana', 'status' => StatusRpp::Diajukan],
                ['topik' => 'Perkalian Dasar 1-10', 'status' => StatusRpp::Disetujui],
            ];

            foreach ($contoh as $idx => $data) {
                $jadwal = $jadwalList->get($idx % $jadwalList->count());
                $guru = $jadwal->guru;
                $kelas = $jadwal->kelas;

                if (! $guru || ! $kelas) {
                    continue;
                }

                $rppLama = Rpp::where('guru_id', $guru->id)
                    ->where('kelas_id', $kelas->id)
                    ->where('mata_pelajaran_id', $jadwal->mata_pelajaran_id)
                    ->where('semester_id', $jadwal->semester_id)
                    ->where('judul_topik', $data['topik'])
                    ->first();

                if ($rppLama) {
                    continue;
                }

                $namaFile = 'rpp-demo-'.$idx.'.pdf';
                $path = "rpp/{$lembaga->id}/{$namaFile}";
                Storage::disk('public')->put($path, "Contoh dokumen RPP/Modul Ajar demo: {$data['topik']}.");

                $verifiedByUserId = null;
                $verifiedAt = null;
                if ($data['status'] === StatusRpp::Disetujui) {
                    $kepsek = Guru::where('lembaga_id', $lembaga->id)->where('id', $kelas->wali_kelas_guru_id)->first();
                    $verifiedByUserId = $kepsek?->user_id;
                    $verifiedAt = now()->subDays(3);
                }

                Rpp::create([
                    'yayasan_id' => $lembaga->yayasan_id,
                    'lembaga_id' => $lembaga->id,
                    'guru_id' => $guru->id,
                    'tahun_ajaran_id' => $kelas->tahun_ajaran_id,
                    'semester_id' => $jadwal->semester_id,
                    'kelas_id' => $kelas->id,
                    'mata_pelajaran_id' => $jadwal->mata_pelajaran_id,
                    'judul_topik' => $data['topik'],
                    'alokasi_waktu' => '2 x 35 menit',
                    'pertemuan_ke' => (string) ($idx + 1),
                    'file_path' => $path,
                    'file_name' => $namaFile,
                    'file_size_bytes' => Storage::disk('public')->size($path),
                    'mime_type' => 'application/pdf',
                    'status' => $data['status'],
                    'verified_by_user_id' => $verifiedByUserId,
                    'verified_at' => $verifiedAt,
                ]);
            }
        }
    }
}
