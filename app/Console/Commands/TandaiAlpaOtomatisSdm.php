<?php

namespace App\Console\Commands;

use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Domains\Sdm\Services\AttendanceRecordAggregator;
use App\Domains\Sdm\Services\KalenderKerjaSdmResolver;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use Illuminate\Console\Command;

class TandaiAlpaOtomatisSdm extends Command
{
    protected $signature = 'sdm:tandai-alpa-otomatis';

    protected $description = 'Tandai pegawai aktif sebagai Alpa untuk hari kerja kemarin (H-1) yang sama sekali tidak punya AttendanceRecord';

    public function __construct(
        private readonly KalenderKerjaSdmResolver $resolver,
        private readonly AttendanceRecordAggregator $aggregator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tanggal = now()->subDay()->toImmutable();
        $jumlahDitandai = 0;

        foreach (Lembaga::all() as $lembaga) {
            $resolusi = $this->resolver->resolve($lembaga, $tanggal);

            if ($resolusi['libur']) {
                continue;
            }

            $pegawaiList = collect()
                ->concat(Guru::where('lembaga_id', $lembaga->id)->where('status_aktif', 'aktif')->get())
                ->concat(Karyawan::where('lembaga_id', $lembaga->id)->where('status_aktif', 'aktif')->get());

            foreach ($pegawaiList as $pegawai) {
                $sudahAda = AttendanceRecord::where('pegawai_type', $pegawai::class)
                    ->where('pegawai_id', $pegawai->id)
                    ->whereDate('tanggal', $tanggal->toDateString())
                    ->exists();

                if ($sudahAda) {
                    continue;
                }

                $pegawai->attendanceEvents()->create([
                    'lembaga_id' => $lembaga->id,
                    'method' => AttendanceMethod::System,
                    'arah' => 'masuk',
                    'status' => AttendanceStatus::Alpa,
                    'waktu' => $tanggal->setTime(23, 59),
                    'dicatat_oleh_user_id' => null,
                    'catatan' => 'Ditandai otomatis oleh sistem — tidak ada aktivitas kehadiran pada hari kerja ini.',
                ]);

                $this->aggregator->sync($pegawai, $tanggal);
                $jumlahDitandai++;
            }
        }

        $this->info("{$jumlahDitandai} pegawai ditandai Alpa otomatis untuk tanggal {$tanggal->toDateString()}.");

        return self::SUCCESS;
    }
}
