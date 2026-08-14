<?php

namespace App\Console\Commands;

use App\Exceptions\BriApiException;
use App\Models\Pembayaran;
use App\Services\Finance\Gateway\BriSnap\BriSnapClient;
use App\Services\Finance\Gateway\BriSnapGateway;
use Illuminate\Console\Command;

class BriTestQris extends Command
{
    protected $signature = 'bri:test-qris {amount=10000}';

    protected $description = 'Generate QRIS via sandbox BRI SNAP asli dan langsung cek statusnya, tanpa menyimpan apa pun ke database';

    public function handle(): int
    {
        $gateway = new BriSnapGateway(app()->make(BriSnapClient::class));

        $pembayaran = new Pembayaran([
            'id' => now()->timestamp, // Using timestamp as ID for testing
            'amount' => (float) $this->argument('amount'),
        ]);

        $this->info('Generating QR...');

        try {
            $qrisResult = $gateway->createQris($pembayaran, 'DIRECT');
        } catch (BriApiException $e) {
            $this->error("Generate QR gagal — responseCode: {$e->responseCode}, responseMessage: {$e->responseMessage}");

            return self::FAILURE;
        }

        $referenceNo = $qrisResult->payload['referenceNo'] ?? null;

        $this->info("qrContent: {$qrisResult->qrCodeData}");
        $this->info("referenceNo: {$referenceNo}");

        if ($referenceNo === null) {
            $this->warn('referenceNo kosong di response BRI — tidak bisa lanjut Inquiry Payment.');

            return self::FAILURE;
        }

        $this->info('Checking status via Inquiry Payment...');

        try {
            $statusResult = $gateway->checkStatus($referenceNo, 'qris');
        } catch (BriApiException $e) {
            $this->error("Inquiry Payment gagal — responseCode: {$e->responseCode}, responseMessage: {$e->responseMessage}");

            return self::FAILURE;
        }

        $this->info("status: {$statusResult->status}");
        $this->info('payload lengkap: ' . json_encode($statusResult->payload, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
