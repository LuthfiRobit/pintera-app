<?php

namespace App\Services\Finance\Gateway\BriSnap;

use App\Exceptions\BriApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BriSnapClient
{
    public function __construct(
        protected string $clientId,
        protected string $clientSecret,
        protected string $baseUrl,
        protected string $privateKeyPath,
        protected string $partnerId,
        protected string $channelId,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            clientId: (string) config('services.bri.client_id'),
            clientSecret: (string) config('services.bri.client_secret'),
            baseUrl: (string) config('services.bri.base_url'),
            privateKeyPath: (string) config('services.bri.private_key_path'),
            partnerId: (string) config('services.bri.partner_id'),
            channelId: (string) config('services.bri.channel_id'),
        );
    }

    public function currentTimestamp(): string
    {
        $now = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));

        return $now->format('Y-m-d\TH:i:s.v') . $now->format('P');
    }

    public function buildAsymmetricStringToSign(string $timestamp): string
    {
        return $this->clientId . '|' . $timestamp;
    }

    public function buildSymmetricStringToSign(string $method, string $path, string $accessToken, string $bodyHash, string $timestamp): string
    {
        return "{$method}:{$path}:{$accessToken}:{$bodyHash}:{$timestamp}";
    }

    public function hashBody(string $bodyJson): string
    {
        return strtolower(hash('sha256', $bodyJson));
    }

    protected function asymmetricSignature(string $timestamp): string
    {
        $stringToSign = $this->buildAsymmetricStringToSign($timestamp);
        $privateKey = file_get_contents(base_path($this->privateKeyPath));

        openssl_sign($stringToSign, $signatureRaw, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signatureRaw);
    }

    protected function symmetricSignature(string $method, string $path, string $accessToken, string $bodyJson, string $timestamp): string
    {
        $bodyHash = $this->hashBody($bodyJson);
        $stringToSign = $this->buildSymmetricStringToSign($method, $path, $accessToken, $bodyHash, $timestamp);

        return base64_encode(hash_hmac('sha512', $stringToSign, $this->clientSecret, true));
    }

    public function getAccessToken(): string
    {
        return Cache::remember('bri_snap_access_token', 850, function () {
            $timestamp = $this->currentTimestamp();

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-CLIENT-KEY' => $this->clientId,
                'X-TIMESTAMP' => $timestamp,
                'X-SIGNATURE' => $this->asymmetricSignature($timestamp),
            ])->post($this->baseUrl . '/snap/v1.0/access-token/b2b', [
                'grantType' => 'client_credentials',
            ]);

            $data = $response->json() ?? [];

            if (!$response->successful() || empty($data['accessToken'])) {
                throw new BriApiException(
                    (string) ($data['responseCode'] ?? (string) $response->status()),
                    (string) ($data['responseMessage'] ?? 'Failed to retrieve BRI SNAP access token')
                );
            }

            return $data['accessToken'];
        });
    }

    public function post(string $path, array $body): array
    {
        $accessToken = $this->getAccessToken();
        $timestamp = $this->currentTimestamp();
        $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES);
        $signature = $this->symmetricSignature('POST', $path, $accessToken, $bodyJson, $timestamp);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
            'X-PARTNER-ID' => $this->partnerId,
            'CHANNEL-ID' => $this->channelId,
            'X-EXTERNAL-ID' => (string) round(microtime(true) * 1000),
        ])->withBody($bodyJson, 'application/json')->post($this->baseUrl . $path);

        $data = $response->json() ?? [];

        if (!$response->successful() || (isset($data['responseCode']) && !str_starts_with((string) $data['responseCode'], '200'))) {
            throw new BriApiException(
                (string) ($data['responseCode'] ?? (string) $response->status()),
                (string) ($data['responseMessage'] ?? 'BRI SNAP API request failed')
            );
        }

        return $data;
    }
}
