<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SimulateBriInbound extends Command
{
    protected $signature = 'bri:test-va-inbound {va_number} {amount=50000}';
    protected $description = 'Simulate an incoming BRI VA payment notification (for testing). '
        . 'Note: this dispatches via internal Laravel request handling (app()->handle()), not a real HTTP '
        . 'call to config("app.url") — it can only catch router/middleware-level problems, not '
        . 'webserver/TLS/reverse-proxy-level issues.';

    public function handle()
    {
        $vaNumber = $this->argument('va_number');
        $amount = $this->argument('amount');

        $this->info("Simulating payment for VA {$vaNumber} with amount {$amount}");

        // 1. Get Token
        $clientId = config('services.bri.inbound.client_id');
        $clientSecret = config('services.bri.inbound.client_secret');
        
        $requestToken = Request::create('/snap/v1.0/access-token/b2b', 'POST', [
            'grantType' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);
        
        $responseToken = app()->handle($requestToken);
        
        if ($responseToken->getStatusCode() !== 200) {
            $this->error('Failed to get token: ' . $responseToken->getContent());
            return 1;
        }

        $tokenData = json_decode($responseToken->getContent(), true);
        $token = $tokenData['accessToken'] ?? null;

        if (!$token) {
            $this->error('No access token in response');
            return 1;
        }

        $this->info('Token successfully generated');

        // 2. Post Payment
        $reqId = Str::uuid()->toString();
        $paymentPayload = [
            'virtualAccountNo' => $vaNumber,
            'paymentRequestId' => $reqId,
            'paidAmount' => [
                'value' => (string)$amount,
                'currency' => 'IDR'
            ]
        ];

        $requestPayment = Request::create('/snap/v1.0/transfer-va/payment', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode($paymentPayload));
        
        $requestPayment->headers->set('Authorization', 'Bearer ' . $token);
        $requestPayment->headers->set('Accept', 'application/json');

        $responsePayment = app()->handle($requestPayment);

        if ($responsePayment->getStatusCode() !== 200) {
            $this->error('Failed to inject payment: ' . $responsePayment->getContent());
            return 1;
        }

        $this->info('Payment successfully injected');
        return 0;
    }
}
