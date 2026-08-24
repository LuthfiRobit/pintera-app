<?php

namespace Database\Seeders;

use App\Domains\Sdm\Actions\AjukanIzinCutiAction;
use App\Domains\Sdm\Actions\AssignShiftAction;
use App\Domains\Sdm\Actions\GenerateEmployeeQrTokenAction;
use App\Domains\Sdm\Actions\ProsesApprovalIzinCutiAction;
use App\Domains\Sdm\Actions\RecordManualAttendanceAction;
use App\Domains\Sdm\Actions\SetAttendanceMethodConfigurationAction;
use App\Domains\Sdm\Actions\SetHariLiburMingguanSdmAction;
use App\Domains\Sdm\DataTransferObjects\HariKerjaSdmData;
use App\Domains\Sdm\DataTransferObjects\RecordManualAttendanceData;
use App\Domains\Sdm\DataTransferObjects\ShiftAssignmentData;
use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Sdm\Enums\TipeKalenderKerjaSdm;
use App\Domains\Sdm\Models\AttendanceEvent;
use App\Domains\Sdm\Models\AttendancePoint;
use App\Domains\Sdm\Models\AttendancePolicy;
use App\Domains\Sdm\Models\JenisShift;
use App\Domains\Sdm\Models\KalenderKerjaSdm;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Sdm\Models\PenugasanShift;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\Seeder;

class KehadiranSdmDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn(static::class.': dilewati, hanya boleh jalan di environment local/testing.');

            return;
        }

        $yayasan = Yayasan::first();
        if (! $yayasan) {
            $this->command?->warn(static::class.': tidak ada Yayasan, dilewati.');

            return;
        }

        $lembaga = Lembaga::where('npsn', '20223333')->first() ?? Lembaga::first();
        if (! $lembaga) {
            $this->command?->warn(static::class.': tidak ada Lembaga, dilewati.');

            return;
        }

        $guruAbsensi = Guru::where('email', 'hendra.gunawan@demo.test')->first();
        $guruPolicy = Guru::where('email', 'maya.anggraini@demo.test')->first();
        $guruShift = Guru::where('email', 'taufik.hidayat@demo.test')->first();

        if (! $guruAbsensi || ! $guruPolicy || ! $guruShift) {
            $this->command?->warn(static::class.': data Guru demo SDIT belum lengkap (jalankan GuruSeeder dulu), dilewati.');

            return;
        }

        // ── Akun admin_sdm (reuse akun admin_administrasi SDIT yang sudah ada) ──
        $adminSdm = User::where('email', 'adm.sd@demo.test')->first();
        if ($adminSdm && ! $adminSdm->hasRole('admin_sdm')) {
            $adminSdm->assignRole('admin_sdm');
        }

        $kepsek = User::where('email', 'kepsek.sd@demo.test')->first();

        $this->command?->info('Menyiapkan Konfigurasi Metode Absensi & Titik Absen...');

        // 1. Metode absensi: QR aktif untuk lembaga (Admin manual selalu aktif secara implisit)
        app(SetAttendanceMethodConfigurationAction::class)->execute($yayasan->id, $lembaga->id, AttendanceMethod::Qr, true);

        // 2. Titik absen
        $titikAbsen = AttendancePoint::firstOrCreate(
            ['lembaga_id' => $lembaga->id, 'nama' => 'Gerbang Utama'],
        );

        // 3. QR kehadiran untuk guru demo
        if (! $guruAbsensi->employeeQrCode) {
            app(GenerateEmployeeQrTokenAction::class)->execute($guruAbsensi);
        }

        $this->command?->info('Menyiapkan Kalender Kerja SDM...');

        // 4. Hari kerja mingguan SDM: Senin-Sabtu (beda dari kalender akademik)
        app(SetHariLiburMingguanSdmAction::class)->execute($lembaga, new HariKerjaSdmData(hariKerja: [1, 2, 3, 4, 5, 6]));

        // 5. Entri kalender nasional contoh (tanggal dinamis, bukan hardcode tahun)
        $tanggalLiburDemo = now()->addMonth()->startOfMonth()->toDateString();
        KalenderKerjaSdm::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'tanggal' => $tanggalLiburDemo, 'nama' => 'Contoh Cuti Bersama (Demo)'],
            ['tipe' => TipeKalenderKerjaSdm::Libur, 'keterangan' => 'Entri kalender kerja SDM contoh untuk keperluan demo.'],
        );

        $this->command?->info('Menyiapkan Attendance Policy...');

        // 6. Policy jam kerja untuk guru_kelas
        AttendancePolicy::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'jenis_karyawan_id' => null],
            ['jam_masuk' => '07:00', 'jam_pulang' => '15:00', 'toleransi_menit' => 15],
        );

        $this->command?->info('Menyiapkan Shift Bergilir...');

        // 7. Jenis shift + penugasan contoh (tanggal dinamis: minggu berjalan)
        $jenisShift = JenisShift::firstOrCreate(
            ['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi'],
            ['jam_masuk' => '06:00', 'jam_pulang' => '14:00'],
        );

        $sudahAdaPenugasan = PenugasanShift::where('pegawai_type', Guru::class)->where('pegawai_id', $guruShift->id)->exists();
        if (! $sudahAdaPenugasan) {
            app(AssignShiftAction::class)->execute($guruShift, new ShiftAssignmentData(
                lembagaId: $lembaga->id,
                jenisShiftId: $jenisShift->id,
                tanggalMulai: now()->startOfWeek()->toDateString(),
                tanggalSelesai: now()->endOfWeek()->toDateString(),
            ));
        }

        $this->command?->info('Menyiapkan Riwayat Kehadiran (3 hari terakhir)...');

        // 8. Riwayat kehadiran manual untuk guru demo, 3 hari kerja terakhir
        for ($i = 1; $i <= 3; $i++) {
            $tanggal = now()->subDays($i);
            if (in_array($tanggal->dayOfWeek, [0], true)) {
                continue; // lewati Minggu
            }

            $sudahAda = AttendanceEvent::where('pegawai_type', Guru::class)
                ->where('pegawai_id', $guruAbsensi->id)
                ->whereDate('waktu', $tanggal->toDateString())
                ->exists();

            if ($sudahAda || ! $adminSdm) {
                continue;
            }

            app(RecordManualAttendanceAction::class)->execute($guruAbsensi, new RecordManualAttendanceData(
                lembagaId: $lembaga->id,
                arah: 'masuk',
                status: AttendanceStatus::Hadir,
                waktu: $tanggal->setTime(7, 5)->toImmutable(),
                dicatatOlehUserId: $adminSdm->id,
                attendancePointId: $titikAbsen->id,
            ));
        }

        if ($kepsek && $adminSdm) {
            $this->command?->info('Menyiapkan Pengajuan Izin/Cuti (1 Pending, 1 Approved)...');

            // 9a. Pengajuan Sakit — masih Pending (menunggu verifikasi Kepala Sekolah)
            $sudahAdaSakit = PengajuanIzinCuti::where('pegawai_type', Guru::class)
                ->where('pegawai_id', $guruAbsensi->id)
                ->where('kategori', KategoriPengajuanIzin::Sakit)
                ->exists();

            if (! $sudahAdaSakit) {
                app(AjukanIzinCutiAction::class)->execute(
                    $guruAbsensi,
                    KategoriPengajuanIzin::Sakit,
                    now()->toDateString(),
                    now()->toDateString(),
                    'Demam, surat dokter menyusul (contoh data demo).',
                );
            }

            // 9b. Pengajuan Cuti — sudah Approved penuh (menunjukkan AttendanceEvent otomatis)
            $sudahAdaCuti = PengajuanIzinCuti::where('pegawai_type', Guru::class)
                ->where('pegawai_id', $guruPolicy->id)
                ->where('kategori', KategoriPengajuanIzin::Cuti)
                ->exists();

            if (! $sudahAdaCuti) {
                $tanggalCuti = now()->addWeek()->toDateString();
                $pengajuanCuti = app(AjukanIzinCutiAction::class)->execute(
                    $guruPolicy,
                    KategoriPengajuanIzin::Cuti,
                    $tanggalCuti,
                    $tanggalCuti,
                    'Acara keluarga (contoh data demo).',
                );

                app(ProsesApprovalIzinCutiAction::class)->execute($pengajuanCuti, $kepsek, ApprovalAction::Approve, 'Disetujui (demo).');
                app(ProsesApprovalIzinCutiAction::class)->execute($pengajuanCuti->fresh(), $adminSdm, ApprovalAction::Approve, 'Disetujui (demo).');
            }
        }

        $this->command?->info('Data Demo Kehadiran SDM berhasil disiapkan!');
    }
}
