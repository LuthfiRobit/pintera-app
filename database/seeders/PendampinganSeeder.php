<?php
// database/seeders/PendampinganSeeder.php

namespace Database\Seeders;

use App\Enums\StatusKasus;
use App\Enums\StatusKasusSesi;
use App\Enums\StatusKasusTugas;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Kasus;
use App\Models\KasusConsent;
use App\Models\KasusEvaluasi;
use App\Models\KasusSesi;
use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PendampinganSeeder extends Seeder
{
    /**
     * Seed skenario kasus pendampingan yang mencakup semua status (1-5):
     *   1. diajukan          — baru diajukan, belum ada konselor
     *   2. menunggu_consent  — sudah triase, menunggu persetujuan ortu
     *   3. ditugaskan        — consent disetujui, konselor sudah ditugaskan
     *   4. berjalan          — sesi/tugas pertama sudah dibuat
     *   5. eskalasi          — dikonselerkan ke admin setelah evaluasi
     *   6. selesai           — kasus sudah ditutup konselor
     */
    public function run(): void
    {
        $smpit = Lembaga::where('npsn', '20223344')->firstOrFail();

        $gurubk     = $this->resolveGuruBk($smpit);
        $psikolog   = $this->resolveKaryawanPool();
        $adminUser  = User::where('email', 'adm.smpit@permatakraksaan.sch.id')->first();
        $siswas     = Siswa::where('lembaga_id', $smpit->id)->take(10)->get();

        if ($siswas->count() < 5) {
            $this->command->warn('PendampinganSeeder: kurang dari 5 siswa SMPIT, skip.');
            return;
        }

        // ── Skenario 1: Diajukan oleh guru ───────────────────────────────────
        $this->buatKasusDiajukan($siswas[0], $smpit, $gurubk);

        // ── Skenario 2: Menunggu Consent ─────────────────────────────────────
        $this->buatKasusMenungguConsent($siswas[1], $smpit, $gurubk, $psikolog);

        // ── Skenario 3: Ditugaskan (consent disetujui, belum ada sesi/tugas) ─
        $this->buatKasusDitugaskan($siswas[2], $smpit, $gurubk);

        // ── Skenario 4: Berjalan (ada sesi & tugas) ──────────────────────────
        $this->buatKasusBerjalan($siswas[3], $smpit, $gurubk, $adminUser);

        // ── Skenario 5: Eskalasi ──────────────────────────────────────────────
        $this->buatKasusEskalasi($siswas[4], $smpit, $psikolog, $adminUser);

        // ── Skenario 6: Selesai ───────────────────────────────────────────────
        if ($siswas->count() >= 6) {
            $this->buatKasusSelesai($siswas[5], $smpit, $gurubk, $adminUser);
        }
    }

    // ── Resolvers ────────────────────────────────────────────────────────────

    private function resolveGuruBk(Lembaga $smpit): ?Guru
    {
        return Guru::where('lembaga_id', $smpit->id)
            ->where('jenis_ptk', 'guru_bk')
            ->first();
    }

    private function resolveKaryawanPool(): ?Karyawan
    {
        return Karyawan::where('lembaga_id', null)
            ->where('status_aktif', 'aktif')
            ->first();
    }

    private function resolveOrangTuaKontakUtama(Siswa $siswa): ?OrangTua
    {
        return DB::table('siswa_orang_tua')
            ->where('siswa_id', $siswa->id)
            ->where('is_kontak_utama', true)
            ->join('orang_tua', 'orang_tua.id', '=', 'siswa_orang_tua.orang_tua_id')
            ->select('orang_tua.*')
            ->first()
            ? OrangTua::find(
                DB::table('siswa_orang_tua')
                    ->where('siswa_id', $siswa->id)
                    ->where('is_kontak_utama', true)
                    ->value('orang_tua_id')
            )
            : null;
    }

    // ── Skenario Kasus ───────────────────────────────────────────────────────

    /**
     * Skenario 1 — Status: diajukan
     * Guru mengajukan kasus, belum ada triase.
     */
    private function buatKasusDiajukan(Siswa $siswa, Lembaga $smpit, ?Guru $gurubk): void
    {
        if (! $gurubk) {
            return;
        }

        Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'diajukan_oleh_guru_id' => $gurubk->id, 'status' => StatusKasus::Diajukan],
            [
                'lembaga_id'           => $smpit->id,
                'kategori_masalah'     => 'Perilaku',
                'deskripsi'            => 'Siswa sering tidak mengumpulkan tugas dan terlihat menarik diri dari pergaulan teman sebaya. Guru wali kelas sudah berkomunikasi tapi perlu penanganan lebih lanjut.',
                'tingkat_urgensi'      => 'sedang',
            ]
        );
    }

    /**
     * Skenario 2 — Status: menunggu_consent
     * Admin sudah triase dan pilih konselor, menunggu persetujuan ortu.
     */
    private function buatKasusMenungguConsent(Siswa $siswa, Lembaga $smpit, ?Guru $gurubk, ?Karyawan $psikolog): void
    {
        if (! $gurubk) {
            return;
        }

        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'status' => StatusKasus::MenungguConsent],
            [
                'lembaga_id'              => $smpit->id,
                'diajukan_oleh_guru_id'   => $gurubk->id,
                'kategori_masalah'        => 'Sosial-Emosional',
                'deskripsi'               => 'Siswa menunjukkan tanda-tanda kecemasan berlebih saat ujian dan menghindari interaksi dengan guru.',
                'tingkat_urgensi'         => 'tinggi',
                'konselor_karyawan_id'    => $psikolog?->id,
                'konselor_guru_id'        => $psikolog ? null : $gurubk->id,
                'dikonfirmasi_pihak_lain_at' => null,
            ]
        );

        // Buat 2 baris consent (menunggu)
        KasusConsent::firstOrCreate(['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan'], ['status' => 'menunggu']);
        KasusConsent::firstOrCreate(['kasus_id' => $kasus->id, 'jenis' => 'pengumpulan_media'],  ['status' => 'menunggu']);
    }

    /**
     * Skenario 3 — Status: ditugaskan
     * Consent sesi disetujui, konselor resmi ditugaskan, belum ada sesi/tugas.
     */
    private function buatKasusDitugaskan(Siswa $siswa, Lembaga $smpit, ?Guru $gurubk): void
    {
        if (! $gurubk) {
            return;
        }

        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'status' => StatusKasus::Ditugaskan],
            [
                'lembaga_id'            => $smpit->id,
                'diajukan_oleh_guru_id' => $gurubk->id,
                'kategori_masalah'      => 'Akademik',
                'deskripsi'             => 'Performa akademik menurun drastis dalam 2 bulan terakhir. Nilai rata-rata turun dari 80 ke 62. Perlu asesmen penyebab.',
                'tingkat_urgensi'       => 'rendah',
                'konselor_guru_id'      => $gurubk->id,
            ]
        );

        // Consent sesi disetujui, media masih menunggu
        KasusConsent::firstOrCreate(
            ['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan'],
            ['status' => 'disetujui', 'disetujui_at' => now()->subDays(3)]
        );
        KasusConsent::firstOrCreate(
            ['kasus_id' => $kasus->id, 'jenis' => 'pengumpulan_media'],
            ['status' => 'menunggu']
        );
    }

    /**
     * Skenario 4 — Status: berjalan
     * Ada sesi selesai, ada tugas yang dikerjakan, ada evaluasi lanjut.
     */
    private function buatKasusBerjalan(Siswa $siswa, Lembaga $smpit, ?Guru $gurubk, ?User $adminUser): void
    {
        if (! $gurubk || ! $adminUser) {
            return;
        }

        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'status' => StatusKasus::Berjalan],
            [
                'lembaga_id'            => $smpit->id,
                'diajukan_oleh_guru_id' => $gurubk->id,
                'kategori_masalah'      => 'Perilaku',
                'deskripsi'             => 'Siswa menunjukkan agresi verbal di kelas, sudah terjadi 3 insiden dalam sebulan.',
                'tingkat_urgensi'       => 'tinggi',
                'konselor_guru_id'      => $gurubk->id,
            ]
        );

        // Consent keduanya disetujui
        KasusConsent::firstOrCreate(
            ['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan'],
            ['status' => 'disetujui', 'disetujui_at' => now()->subDays(14)]
        );
        KasusConsent::firstOrCreate(
            ['kasus_id' => $kasus->id, 'jenis' => 'pengumpulan_media'],
            ['status' => 'disetujui', 'disetujui_at' => now()->subDays(14)]
        );

        // Sesi 1: sudah selesai
        $sesi1 = KasusSesi::firstOrCreate(
            ['kasus_id' => $kasus->id, 'dijadwalkan_pada' => now()->subDays(10)->setTime(9, 0)],
            [
                'peserta'           => 'siswa',
                'lokasi_mode'       => 'Ruang BK',
                'status'            => StatusKasusSesi::Selesai,
                'catatan_internal'  => 'Siswa terbuka tentang masalah di rumah. Orang tua bekerja penuh dan jarang di rumah. Perlu sesi berikutnya dengan orang tua.',
            ]
        );

        // Sesi 2: terjadwal (besok)
        KasusSesi::firstOrCreate(
            ['kasus_id' => $kasus->id, 'dijadwalkan_pada' => now()->addDay()->setTime(10, 0)],
            [
                'peserta'     => 'keduanya',
                'lokasi_mode' => 'Ruang BK',
                'status'      => StatusKasusSesi::Terjadwal,
            ]
        );

        // Tugas: Jurnal Emosi Harian
        $tugas = KasusTugas::firstOrCreate(
            ['kasus_id' => $kasus->id, 'judul' => 'Jurnal Emosi Harian'],
            [
                'instruksi'          => 'Setiap malam sebelum tidur, tulis 3 hal yang kamu rasakan hari ini dan apa yang membuatmu merasakan itu.',
                'frekuensi'          => 'harian',
                'batch_id'           => (string) Str::uuid(),
                'batch_urutan'       => 1,
                'batch_total'        => 1,
                'mulai_pada'         => now()->subDays(7)->toDateString(),
                'batas_selesai_pada' => now()->addDays(7)->toDateString(),
                'status'             => StatusKasusTugas::Dikerjakan,
            ]
        );

        // Submission tugas
        $siswaModel = $siswa;
        KasusTugasSubmission::firstOrCreate(
            ['tugas_id' => $tugas->id, 'siswa_id' => $siswaModel->id, 'created_at' => now()->subDays(5)],
            [
                'teks'          => 'Hari ini saya merasa marah karena teman saya mengejek saya di depan kelas. Saya juga merasa sedih dan tidak mau masuk sekolah besok.',
                'status_review' => 'diterima',
                'catatan_revisi' => null,
            ]
        );

        // Evaluasi: lanjut
        KasusEvaluasi::firstOrCreate(
            ['kasus_id' => $kasus->id, 'dibuat_oleh_user_id' => $gurubk->user_id],
            [
                'tanggal'   => now()->subDays(5),
                'catatan'   => 'Progres positif — siswa mulai membuka diri. Sesi lanjutan dengan orang tua diperlukan untuk memahami konteks rumah.',
                'keputusan' => 'lanjut',
            ]
        );
    }

    /**
     * Skenario 5 — Status: eskalasi
     * Konselor mengeskalasi ke admin/koordinator BK.
     */
    private function buatKasusEskalasi(Siswa $siswa, Lembaga $smpit, ?Karyawan $psikolog, ?User $adminUser): void
    {
        if (! $psikolog || ! $adminUser) {
            return;
        }

        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'status' => StatusKasus::Eskalasi],
            [
                'lembaga_id'              => $smpit->id,
                'diajukan_oleh_guru_id'   => null,
                'diajukan_oleh_orang_tua_id' => $this->resolveOrangTuaKontakUtama($siswa)?->id,
                'kategori_masalah'        => 'Kesehatan Mental',
                'deskripsi'               => 'Orang tua melaporkan anak menolak makan dan tidak tidur selama beberapa hari. Ada indikasi depresi ringan.',
                'tingkat_urgensi'         => 'tinggi',
                'konselor_karyawan_id'    => $psikolog->id,
            ]
        );

        KasusConsent::firstOrCreate(
            ['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan'],
            ['status' => 'disetujui', 'disetujui_at' => now()->subDays(20)]
        );
        KasusConsent::firstOrCreate(
            ['kasus_id' => $kasus->id, 'jenis' => 'pengumpulan_media'],
            ['status' => 'disetujui', 'disetujui_at' => now()->subDays(20)]
        );

        KasusSesi::firstOrCreate(
            ['kasus_id' => $kasus->id, 'dijadwalkan_pada' => now()->subDays(15)->setTime(9, 0)],
            [
                'peserta'          => 'siswa',
                'lokasi_mode'      => 'Online (Google Meet)',
                'status'           => StatusKasusSesi::Selesai,
                'catatan_internal' => 'Indikasi depresi lebih serius dari yang dilaporkan orang tua. Perlu rujukan ke psikiater. Eskalasi ke koordinator BK.',
            ]
        );

        // Evaluasi: eskalasi
        KasusEvaluasi::firstOrCreate(
            ['kasus_id' => $kasus->id, 'dibuat_oleh_user_id' => $psikolog->user_id],
            [
                'tanggal'   => now()->subDays(12),
                'catatan'   => 'Kondisi siswa memerlukan penanganan psikiater di luar kapasitas konselor sekolah. Perlu keputusan dan dukungan koordinator BK untuk rujukan eksternal.',
                'keputusan' => 'eskalasi',
            ]
        );
    }

    /**
     * Skenario 6 — Status: selesai
     * Kasus sudah ditutup oleh konselor.
     */
    private function buatKasusSelesai(Siswa $siswa, Lembaga $smpit, ?Guru $gurubk, ?User $adminUser): void
    {
        if (! $gurubk || ! $adminUser) {
            return;
        }

        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'status' => StatusKasus::Selesai],
            [
                'lembaga_id'            => $smpit->id,
                'diajukan_oleh_guru_id' => $gurubk->id,
                'kategori_masalah'      => 'Akademik',
                'deskripsi'             => 'Siswa mengalami kesulitan konsentrasi dan sering absen. Sudah ditangani 6 sesi.',
                'tingkat_urgensi'       => 'sedang',
                'konselor_guru_id'      => $gurubk->id,
            ]
        );

        KasusConsent::firstOrCreate(
            ['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan'],
            ['status' => 'disetujui', 'disetujui_at' => now()->subDays(60)]
        );
        KasusConsent::firstOrCreate(
            ['kasus_id' => $kasus->id, 'jenis' => 'pengumpulan_media'],
            ['status' => 'menunggu']
        );

        // 2 sesi selesai
        foreach ([45, 30] as $daysAgo) {
            KasusSesi::firstOrCreate(
                ['kasus_id' => $kasus->id, 'dijadwalkan_pada' => now()->subDays($daysAgo)->setTime(9, 0)],
                [
                    'peserta'          => 'siswa',
                    'lokasi_mode'      => 'Ruang BK',
                    'status'           => StatusKasusSesi::Selesai,
                    'catatan_internal' => "Sesi ke-{$daysAgo} hari lalu: progres membaik.",
                ]
            );
        }

        // Tugas selesai
        $tugas = KasusTugas::firstOrCreate(
            ['kasus_id' => $kasus->id, 'judul' => 'Strategi Belajar Mandiri'],
            [
                'instruksi'          => 'Buat jadwal belajar mingguan dan patuhi selama 2 minggu. Catat hambatan yang ditemui.',
                'frekuensi'          => 'mingguan',
                'batch_id'           => (string) Str::uuid(),
                'batch_urutan'       => 1,
                'batch_total'        => 1,
                'mulai_pada'         => now()->subDays(30)->toDateString(),
                'batas_selesai_pada' => now()->subDays(16)->toDateString(),
                'status'             => StatusKasusTugas::Selesai,
            ]
        );

        KasusTugasSubmission::firstOrCreate(
            ['tugas_id' => $tugas->id, 'siswa_id' => $siswa->id],
            [
                'teks'          => 'Saya sudah membuat jadwal dan berhasil mengikutinya selama 2 minggu. Nilai ulangan harian naik.',
                'status_review' => 'diterima',
            ]
        );

        // Evaluasi: selesai
        KasusEvaluasi::firstOrCreate(
            ['kasus_id' => $kasus->id, 'dibuat_oleh_user_id' => $gurubk->user_id],
            [
                'tanggal'   => now()->subDays(15),
                'catatan'   => 'Siswa menunjukkan perbaikan signifikan. Mampu mengatur waktu belajar secara mandiri. Kasus dinyatakan selesai.',
                'keputusan' => 'selesai',
            ]
        );
    }
}
