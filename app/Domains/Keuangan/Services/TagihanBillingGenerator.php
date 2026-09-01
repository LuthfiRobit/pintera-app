<?php

// app/Domains/Keuangan/Services/TagihanBillingGenerator.php

namespace App\Domains\Keuangan\Services;

use App\Domains\Keuangan\Models\BillingJobLog;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Siswa;
use App\Notifications\Finance\TagihanDiterbitkanNotification;
use App\Services\Finance\NotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TagihanBillingGenerator
{
    public function __construct(
        private readonly JenisTagihanSasaranMatcher $matcher,
        private readonly TagihanNominalResolver $nominalResolver,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function generate(JenisTagihan $jenisTagihan, string $triggerType, ?string $triggerEvent = null): BillingJobLog
    {
        $this->assertBillable($jenisTagihan);

        $targetSiswa = $this->matcher->resolveTargetSiswa($jenisTagihan);

        $billsGenerated = 0;
        $errors = [];

        foreach ($targetSiswa as $siswa) {
            try {
                if ($this->generateForSiswa($siswa, $jenisTagihan, $triggerType)) {
                    $billsGenerated++;
                }
            } catch (\Throwable $e) {
                $errors[] = ['siswa_id' => $siswa->id, 'message' => $e->getMessage()];
            }
        }

        return $this->logResult($jenisTagihan, $triggerType, $triggerEvent, $billsGenerated, $errors);
    }

    public function generateForSiswa(Siswa $siswa, JenisTagihan $jenisTagihan, string $triggerType): bool
    {
        $createdTagihan = null;

        $result = DB::transaction(function () use ($siswa, $jenisTagihan, $triggerType, &$createdTagihan) {
            $billingPeriod = $jenisTagihan->mode === 'otomatis' ? now()->format('Y-m') : null;

            $exists = Tagihan::where('tagihable_type', Siswa::class)
                ->where('tagihable_id', $siswa->id)
                ->where('jenis_tagihan_id', $jenisTagihan->id)
                ->where('billing_period', $billingPeriod)
                ->where('status', '!=', 'dibatalkan')
                ->exists();

            if ($exists) {
                return false;
            }

            $resolved = $this->nominalResolver->resolve($siswa, $jenisTagihan);
            $netAmount = max(0, $resolved['nominal'] - $resolved['discount_amount']);

            $personId = $siswa->person_id
                ?? throw new \RuntimeException("Tidak bisa membuat tagihan: Siswa #{$siswa->id} tidak punya person_id yang valid — data kemungkinan cacat.");

            $createdTagihan = Tagihan::create([
                'tagihable_type' => Siswa::class,
                'tagihable_id' => $siswa->id,
                'jenis_tagihan_id' => $jenisTagihan->id,
                'person_id' => $personId,
                'kategori' => $jenisTagihan->kategori,
                'billing_period' => $billingPeriod,
                'source_trigger' => $triggerType,
                'total_tagihan' => $resolved['nominal'],
                'discount_amount' => $resolved['discount_amount'] ?: null,
                'discount_type' => $resolved['discount_type'],
                'net_amount' => $netAmount,
                'jatuh_tempo' => $this->resolveDueDate($jenisTagihan, $billingPeriod),
                'status' => 'belum_bayar',
            ]);

            return true;
        });

        if ($createdTagihan !== null) {
            $kontakUtama = $siswa->orangTua()->wherePivot('is_kontak_utama', true)->first();
            if ($kontakUtama !== null) {
                try {
                    $this->dispatcher->send($kontakUtama, new TagihanDiterbitkanNotification($createdTagihan->load('jenisTagihan')));
                } catch (\Throwable $e) {
                    Log::error('Gagal mengirim TagihanDiterbitkanNotification: '.$e->getMessage());
                }
            }
        }

        return $result;
    }

    public function generateForSiswaViaEvent(Siswa $siswa, JenisTagihan $jenisTagihan, string $triggerEvent): BillingJobLog
    {
        $this->assertBillable($jenisTagihan);

        $billsGenerated = 0;
        $errors = [];

        try {
            if ($this->generateForSiswa($siswa, $jenisTagihan, 'event')) {
                $billsGenerated = 1;
            }
        } catch (\Throwable $e) {
            $errors[] = ['siswa_id' => $siswa->id, 'message' => $e->getMessage()];
        }

        return $this->logResult($jenisTagihan, 'event', $triggerEvent, $billsGenerated, $errors);
    }

    private function assertBillable(JenisTagihan $jenisTagihan): void
    {
        if ($jenisTagihan->kategori->isPpdb()) {
            throw new \RuntimeException(
                "Jenis tagihan berkategori {$jenisTagihan->kategori->label()} tidak bisa diproses lewat billing engine — gunakan alur pendaftaran PPDB."
            );
        }
    }

    private function resolveDueDate(JenisTagihan $jenisTagihan, ?string $billingPeriod): ?string
    {
        if (! $billingPeriod || ! $jenisTagihan->hari_jatuh_tempo) {
            return null;
        }

        $year = (int) substr($billingPeriod, 0, 4);
        $month = (int) substr($billingPeriod, 5, 2);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $day = min($jenisTagihan->hari_jatuh_tempo, $daysInMonth);

        return Carbon::create($year, $month, $day)->toDateString();
    }

    private function logResult(JenisTagihan $jenisTagihan, string $triggerType, ?string $triggerEvent, int $billsGenerated, array $errors): BillingJobLog
    {
        $status = match (true) {
            empty($errors) => 'success',
            $billsGenerated === 0 => 'failed',
            default => 'partial',
        };

        return BillingJobLog::create([
            'jenis_tagihan_id' => $jenisTagihan->id,
            'trigger_type' => $triggerType,
            'trigger_event' => $triggerEvent,
            'period' => $jenisTagihan->mode === 'otomatis' ? now()->format('Y-m') : null,
            'bills_generated' => $billsGenerated,
            'status' => $status,
            'error_log' => empty($errors) ? null : $errors,
            'executed_at' => now(),
        ]);
    }
}
