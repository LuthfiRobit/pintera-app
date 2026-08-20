<?php
// database/seeders/PendampinganSeeder.php

namespace Database\Seeders;

use App\Domains\Kasus\Enums\StatusKasus;
use App\Domains\Kasus\Enums\StatusKasusSesi;
use App\Domains\Kasus\Enums\StatusKasusTugas;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusConsent;
use App\Domains\Kasus\Models\KasusEvaluasi;
use App\Domains\Kasus\Models\KasusSesi;
use App\Domains\Kasus\Models\KasusTugas;
use App\Domains\Kasus\Models\KasusTugasSubmission;
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

        // ── Skenario 7: Kasus ringan SMP (percaya diri presentasi, selesai) ──
        if ($siswas->count() >= 7 && $gurubk) {
            $this->buatKasusRinganSmp($siswas[6], $smpit, $gurubk);
        }

        // ── Skenario ringan lintas jenjang: KB, TK, SD ───────────────────────
        // Menunjukkan fitur ini juga cocok untuk kasus sehari-hari yang ringan,
        // bukan hanya kasus berat — bukan cuma jenjang SMP.
        $this->buatSkenarioRinganLintasJenjang($psikolog);
    }

    private function buatSkenarioRinganLintasJenjang(?Karyawan $psikolog): void
    {
        $kbit = Lembaga::where('npsn', '20223311')->first();
        $tkit = Lembaga::where('npsn', '20223322')->first();
        $sdit = Lembaga::where('npsn', '20223333')->first();

        if ($kbit) {
            $siswaKb = Siswa::where('lembaga_id', $kbit->id)->first();
            $guruKb  = Guru::where('lembaga_id', $kbit->id)->first();
            if ($siswaKb && $guruKb) {
                $this->buatKasusRinganKb($siswaKb, $kbit, $guruKb);
            }
        }

        if ($tkit) {
            $siswaTk = Siswa::where('lembaga_id', $tkit->id)->first();
            if ($siswaTk) {
                $orangTuaTk = $this->resolveOrangTuaKontakUtama($siswaTk);
                if ($orangTuaTk) {
                    $this->buatKasusRinganTk($siswaTk, $tkit, $orangTuaTk, $psikolog);
                }
            }
        }

        if ($sdit) {
            $siswaSd = Siswa::where('lembaga_id', $sdit->id)->first();
            $guruSd  = Guru::where('lembaga_id', $sdit->id)->first();
            if ($siswaSd && $guruSd) {
                $this->buatKasusRinganSd($siswaSd, $sdit, $guruSd);
            }
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
            ['siswa_id' => $siswa->id, 'diajukan_oleh_guru_id' => $gurubk->id, 'kategori_masalah' => 'Perilaku'],
            [
                'lembaga_id'           => $smpit->id,
                'status'               => StatusKasus::Diajukan,
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
            ['siswa_id' => $siswa->id, 'kategori_masalah' => 'Sosial-Emosional'],
            [
                'lembaga_id'              => $smpit->id,
                'diajukan_oleh_guru_id'   => $gurubk->id,
                'status'                  => StatusKasus::MenungguConsent,
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
            ['siswa_id' => $siswa->id, 'kategori_masalah' => 'Akademik', 'tingkat_urgensi' => 'rendah'],
            [
                'lembaga_id'            => $smpit->id,
                'diajukan_oleh_guru_id' => $gurubk->id,
                'status'                => StatusKasus::Ditugaskan,
                'deskripsi'             => 'Performa akademik menurun drastis dalam 2 bulan terakhir. Nilai rata-rata turun dari 80 ke 62. Perlu asesmen penyebab.',
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
            ['siswa_id' => $siswa->id, 'kategori_masalah' => 'Perilaku', 'tingkat_urgensi' => 'tinggi'],
            [
                'lembaga_id'            => $smpit->id,
                'diajukan_oleh_guru_id' => $gurubk->id,
                'status'                => StatusKasus::Berjalan,
                'deskripsi'             => 'Siswa menunjukkan agresi verbal di kelas, sudah terjadi 3 insiden dalam sebulan.',
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
            ['siswa_id' => $siswa->id, 'kategori_masalah' => 'Kesehatan Mental'],
            [
                'lembaga_id'              => $smpit->id,
                'diajukan_oleh_guru_id'   => null,
                'diajukan_oleh_orang_tua_id' => $this->resolveOrangTuaKontakUtama($siswa)?->id,
                'status'                  => StatusKasus::Eskalasi,
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
            ['siswa_id' => $siswa->id, 'kategori_masalah' => 'Akademik', 'tingkat_urgensi' => 'sedang'],
            [
                'lembaga_id'            => $smpit->id,
                'diajukan_oleh_guru_id' => $gurubk->id,
                'status'                => StatusKasus::Selesai,
                'deskripsi'             => 'Siswa mengalami kesulitan konsentrasi dan sering absen. Sudah ditangani 6 sesi.',
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

    /**
     * Skenario ringan SMP — Status: selesai
     * Contoh kasus ringan (bukan kasus berat) yang ditangani sampai tuntas,
     * untuk menunjukkan fitur ini juga cocok untuk keperluan sehari-hari.
     */
    private function buatKasusRinganSmp(Siswa $siswa, Lembaga $smpit, Guru $gurubk): void
    {
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'kategori_masalah' => 'Kepercayaan Diri'],
            [
                'lembaga_id'            => $smpit->id,
                'diajukan_oleh_guru_id' => $gurubk->id,
                'status'                => StatusKasus::Selesai,
                'deskripsi'             => 'Siswa terlihat gugup dan menghindar setiap kali diminta presentasi di depan kelas, meski persiapannya sudah baik.',
                'tingkat_urgensi'       => 'rendah',
                'konselor_guru_id'      => $gurubk->id,
            ]
        );

        KasusConsent::firstOrCreate(
            ['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan'],
            ['status' => 'disetujui', 'disetujui_at' => now()->subDays(20)]
        );
        KasusConsent::firstOrCreate(
            ['kasus_id' => $kasus->id, 'jenis' => 'pengumpulan_media'],
            ['status' => 'menunggu']
        );

        KasusSesi::firstOrCreate(
            ['kasus_id' => $kasus->id, 'dijadwalkan_pada' => now()->subDays(10)->setTime(9, 0)],
            [
                'peserta'          => 'siswa',
                'lokasi_mode'      => 'Ruang BK',
                'status'           => StatusKasusSesi::Selesai,
                'catatan_internal' => 'Latihan presentasi singkat 1-lawan-1 dengan konselor. Siswa lebih rileks setelah beberapa kali mencoba.',
            ]
        );

        $tugas = KasusTugas::firstOrCreate(
            ['kasus_id' => $kasus->id, 'judul' => 'Latihan Bicara di Depan Cermin'],
            [
                'instruksi'          => 'Latihan menyampaikan 1 topik singkat di depan cermin selama 2 menit setiap hari, rekam perasaanmu setelahnya.',
                'frekuensi'          => 'harian',
                'batch_id'           => (string) Str::uuid(),
                'batch_urutan'       => 1,
                'batch_total'        => 1,
                'mulai_pada'         => now()->subDays(9)->toDateString(),
                'batas_selesai_pada' => now()->subDays(9)->toDateString(),
                'status'             => StatusKasusTugas::Selesai,
            ]
        );

        KasusTugasSubmission::firstOrCreate(
            ['tugas_id' => $tugas->id, 'siswa_id' => $siswa->id],
            [
                'teks'          => 'Sudah coba latihan di depan cermin, awalnya canggung tapi lama-lama lebih pede.',
                'status_review' => 'diterima',
            ]
        );

        KasusEvaluasi::firstOrCreate(
            ['kasus_id' => $kasus->id, 'dibuat_oleh_user_id' => $gurubk->user_id],
            [
                'tanggal'   => now()->subDays(8),
                'catatan'   => 'Siswa sudah berhasil presentasi di kelas minggu ini tanpa menghindar. Kasus dinyatakan selesai.',
                'keputusan' => 'selesai',
            ]
        );
    }

    /**
     * Skenario ringan KB — Status: diajukan
     * Diajukan oleh guru (wali kelas), belum ditriase. Contoh kasus keseharian
     * jenjang usia dini, bukan masalah perilaku/emosional berat.
     */
    private function buatKasusRinganKb(Siswa $siswa, Lembaga $kbit, Guru $guruKb): void
    {
        Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'diajukan_oleh_guru_id' => $guruKb->id, 'kategori_masalah' => 'Kebiasaan Makan'],
            [
                'lembaga_id'       => $kbit->id,
                'status'           => StatusKasus::Diajukan,
                'deskripsi'        => 'Ananda selalu menolak sayur saat makan bersama di kelas dan hanya mau makan nasi putih. Mohon saran pendampingan sederhana untuk orang tua di rumah.',
                'tingkat_urgensi'  => 'rendah',
            ]
        );
    }

    /**
     * Skenario ringan TK — Status: menunggu_consent
     * Diajukan LANGSUNG oleh orang tua (bukan guru) — menunjukkan jalur
     * pengajuan orang tua. Sudah ditriase admin (konselor psikolog pool
     * ditugaskan), tinggal menunggu persetujuan consent dari orang tua yang
     * sama.
     */
    private function buatKasusRinganTk(Siswa $siswa, Lembaga $tkit, OrangTua $orangTuaTk, ?Karyawan $psikolog): void
    {
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'diajukan_oleh_orang_tua_id' => $orangTuaTk->id, 'kategori_masalah' => 'Adaptasi Sekolah'],
            [
                'lembaga_id'           => $tkit->id,
                'diajukan_oleh_guru_id' => null,
                'status'               => StatusKasus::MenungguConsent,
                'deskripsi'            => 'Ananda masih menangis dan sulit dilepas setiap kali diantar ke sekolah, sudah berlangsung 2 minggu sejak masuk TK.',
                'tingkat_urgensi'      => 'rendah',
                'konselor_karyawan_id' => $psikolog?->id,
            ]
        );

        KasusConsent::firstOrCreate(['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan'], ['status' => 'menunggu']);
        KasusConsent::firstOrCreate(['kasus_id' => $kasus->id, 'jenis' => 'pengumpulan_media'],  ['status' => 'menunggu']);
    }

    /**
     * Skenario ringan SD — Status: berjalan
     * Memakai fitur tugas batch generator (frekuensi harian) pada kasus
     * ringan, untuk menunjukkan fitur terbaru juga cocok dipakai di luar
     * kasus berat.
     */
    private function buatKasusRinganSd(Siswa $siswa, Lembaga $sdit, Guru $guruSd): void
    {
        $kasus = Kasus::firstOrCreate(
            ['siswa_id' => $siswa->id, 'kategori_masalah' => 'Konsentrasi Belajar'],
            [
                'lembaga_id'            => $sdit->id,
                'diajukan_oleh_guru_id' => $guruSd->id,
                'status'                => StatusKasus::Berjalan,
                'deskripsi'             => 'Ananda mudah teralihkan dan sulit duduk tenang selama 10-15 menit saat pelajaran berlangsung.',
                'tingkat_urgensi'       => 'rendah',
                'konselor_guru_id'      => $guruSd->id,
            ]
        );

        KasusConsent::firstOrCreate(
            ['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan'],
            ['status' => 'disetujui', 'disetujui_at' => now()->subDays(5)]
        );
        KasusConsent::firstOrCreate(
            ['kasus_id' => $kasus->id, 'jenis' => 'pengumpulan_media'],
            ['status' => 'menunggu']
        );

        // Tugas batch: 5 baris harian (hasil generate TugasBatchGenerator, frekuensi harian).
        // 2 hari pertama (lampau) sudah disubmit & diterima; 3 sisanya masih berjalan.
        $batchId = (string) Str::uuid();
        $mulai   = now()->subDays(4)->startOfDay();
        for ($i = 0; $i < 5; $i++) {
            $tugasHarian = KasusTugas::firstOrCreate(
                ['kasus_id' => $kasus->id, 'judul' => 'Latihan Fokus 10 Menit', 'mulai_pada' => $mulai->copy()->addDays($i)->toDateString()],
                [
                    'instruksi'          => 'Duduk tenang dan kerjakan 1 lembar latihan soal selama 10 menit tanpa gangguan gawai.',
                    'frekuensi'          => 'harian',
                    'batch_id'           => $batchId,
                    'batch_urutan'       => $i + 1,
                    'batch_total'        => 5,
                    'batas_selesai_pada' => $mulai->copy()->addDays($i)->toDateString(),
                    'status'             => $i < 2 ? StatusKasusTugas::Selesai : StatusKasusTugas::Dikerjakan,
                ]
            );

            if ($i < 2) {
                KasusTugasSubmission::firstOrCreate(
                    ['tugas_id' => $tugasHarian->id, 'siswa_id' => $siswa->id],
                    [
                        'teks'          => 'Sudah selesai duduk tenang dan kerjakan latihan soalnya selama 10 menit.',
                        'status_review' => 'diterima',
                    ]
                );
            }
        }
    }
}
