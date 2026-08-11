<?php

namespace App\Console\Commands;

use App\Contracts\PaymentGatewayInterface;
use App\Models\BriQrisPayment;
use App\Models\BriVirtualAccount;
use App\Models\Pembayaran;
use App\Models\Wallet;
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
        // Find WAITING VAs
        $waitingVAs = BriVirtualAccount::where('status', 'WAITING')
            ->whereNotNull('pembayaran_id')
            ->get();

        foreach ($waitingVAs as $va) {
            try {
                $statusResult = $this->gateway->checkStatus($va->va_number);
                
                if ($statusResult->status === 'PAID') {
                    DB::transaction(function () use ($va) {
                        // Lock to avoid race condition with webhook
                        $lockedVa = BriVirtualAccount::where('id', $va->id)->lockForUpdate()->first();
                        
                        if ($lockedVa->status !== 'PAID') {
                            $lockedVa->status = 'PAID';
                            $lockedVa->save();

                            $pembayaran = Pembayaran::find($lockedVa->pembayaran_id);
                            if ($pembayaran && $pembayaran->status !== 'lunas') {
                                $pembayaran->status = 'lunas';
                                $pembayaran->save();
                                $this->allocationService->allocate($pembayaran);
                            }
                        }
                    });
                    $this->line("Reconciled VA: {$va->va_number}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to reconcile VA {$va->va_number}: " . $e->getMessage());
                $this->error("Failed to reconcile VA {$va->va_number}");
            }
        }

        // We can do the same for QRIS if needed
        $waitingQris = BriQrisPayment::where('status', 'WAITING')
            ->whereNotNull('pembayaran_id')
            ->get();

        foreach ($waitingQris as $qris) {
            try {
                $statusResult = $this->gateway->checkStatus($qris->qr_code); 
                
                if ($statusResult->status === 'PAID') {
                    DB::transaction(function () use ($qris) {
                        $lockedQris = BriQrisPayment::where('id', $qris->id)->lockForUpdate()->first();
                        
                        if ($lockedQris->status !== 'PAID') {
                            $lockedQris->status = 'PAID';
                            $lockedQris->save();

                            $pembayaran = Pembayaran::find($lockedQris->pembayaran_id);
                            if ($pembayaran && $pembayaran->status !== 'lunas') {
                                $pembayaran->status = 'lunas';
                                $pembayaran->save();
                                $this->allocationService->allocate($pembayaran);
                            }
                        }
                    });
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
            ->where('amount', '>', 0)
            ->get();

        foreach ($failedTopups as $pembayaran) {
            try {
                $wallet = Wallet::where('siswa_id', $pembayaran->siswa_id)->first();
                if ($wallet) {
                    $wallet->topup($pembayaran->amount, $pembayaran, 'Retry Failed Topup');
                    $pembayaran->update(['topup_status' => 'completed']);
                    $this->line("Retried failed topup for Pembayaran ID: {$pembayaran->id}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to retry topup for Pembayaran ID {$pembayaran->id}: " . $e->getMessage());
                $this->error("Failed to retry topup for Pembayaran ID {$pembayaran->id}");
            }
        }
    }
}
