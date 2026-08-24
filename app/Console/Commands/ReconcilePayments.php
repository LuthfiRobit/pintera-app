<?php

namespace App\Console\Commands;

use App\Domains\Keuangan\Contracts\PaymentGatewayInterface;
use App\Models\BriQrisPayment;
use App\Models\Pembayaran;
use App\Services\Finance\PaymentAllocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcilePayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:reconcile-payments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile waiting payments and retry failed wallet topups';

    public function __construct(
        protected PaymentGatewayInterface $gateway,
        protected PaymentAllocationService $allocationService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting payment reconciliation...');

        $this->reconcileWaitingPayments();
        $this->retryFailedTopups();

        $this->info('Reconciliation completed');
    }

    protected function reconcileWaitingPayments()
    {


        // We can do the same for QRIS if needed
        $waitingQris = BriQrisPayment::where('status', 'WAITING')
            ->whereNotNull('pembayaran_id')
            ->get();

        foreach ($waitingQris as $qris) {
            if ($qris->reference_no === null) {
                continue;
            }

            try {
                $statusResult = $this->gateway->checkStatus($qris->reference_no, 'qris');
                
                if ($statusResult->status === 'PAID') {
                    $reconciledPembayaranId = null;

                    DB::transaction(function () use ($qris, &$reconciledPembayaranId) {
                        $lockedQris = BriQrisPayment::where('id', $qris->id)->lockForUpdate()->first();
                        
                        if ($lockedQris->status !== 'PAID') {
                            $lockedQris->status = 'PAID';
                            $lockedQris->save();

                            $pembayaran = Pembayaran::find($lockedQris->pembayaran_id);
                            if ($pembayaran && $pembayaran->status !== 'lunas') {
                                $pembayaran->status = 'lunas';
                                $pembayaran->save();
                                $this->allocationService->allocate($pembayaran);
                                $reconciledPembayaranId = $pembayaran->id;
                            }
                        }
                    });

                    if ($reconciledPembayaranId !== null) {
                        $reconciledPembayaran = Pembayaran::find($reconciledPembayaranId);
                        if ($reconciledPembayaran !== null) {
                            $this->allocationService->topupSisaJikaAda($reconciledPembayaran);
                        }
                    }

                    $this->line("Reconciled QRIS: {$qris->qr_code}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to reconcile QRIS {$qris->qr_code}: " . $e->getMessage());
                $this->error("Failed to reconcile QRIS {$qris->qr_code}");
            }
        }
    }

    protected function retryFailedTopups()
    {
        $failedTopups = Pembayaran::where('topup_status', 'failed')
            ->where('status', 'lunas')
            ->whereNotNull('siswa_id')
            ->get();

        foreach ($failedTopups as $pembayaran) {
            try {
                $this->allocationService->topupSisaJikaAda($pembayaran);
                $this->line("Retried topup for Pembayaran ID: {$pembayaran->id}");
            } catch (\Throwable $e) {
                Log::error("Failed to retry topup for Pembayaran ID {$pembayaran->id}: " . $e->getMessage());
                $this->error("Failed to retry topup for Pembayaran ID {$pembayaran->id}");
            }
        }
    }
}
