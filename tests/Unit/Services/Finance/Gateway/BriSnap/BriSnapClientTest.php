<?php

namespace Tests\Unit\Services\Finance\Gateway\BriSnap;

use App\Exceptions\BriApiException;
use App\Services\Finance\Gateway\BriSnap\BriSnapClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BriSnapClientTest extends TestCase
{
    protected BriSnapClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->client = new BriSnapClient(
            clientId: 'kBb2FjksOMkjTgW3JwNcZc7yBaWWpIML',
            clientSecret: 'Zz9VcSiWgN96BAFG',
            baseUrl: 'https://fake-sandbox.test',
            privateKeyPath: 'tests/Fixtures/bri/test_private.pem',
            partnerId: '77777',
            channelId: '00001',
        );
    }

    public function test_current_timestamp_matches_iso8601_with_offset()
    {
        $timestamp = $this->client->currentTimestamp();

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+-]\d{2}:\d{2}$/',
            $timestamp
        );
    }

    public function test_asymmetric_string_to_sign_matches_bri_formula()
    {
        // Formula dari bri-api.md "Access Token and Signature":
        // stringToSign = client_ID + "|" + X-TIMESTAMP
        $stringToSign = $this->client->buildAsymmetricStringToSign('2026-08-14T10:00:00.000+07:00');

        $this->assertSame(
            'kBb2FjksOMkjTgW3JwNcZc7yBaWWpIML|2026-08-14T10:00:00.000+07:00',
            $stringToSign
        );
    }

    public function test_symmetric_string_to_sign_matches_documented_example()
    {
        // Contoh persis dari bri-api.md "B. Signature API Access":
        // POST:/snap/v1.0/dummy:muhpwhwOkPRU9nNXYnyYHj8t54x3:8b4e9e83b5231cff4f84358ec8ca81951cfe9f999f635b1566452a501d5c23b2:2021-11-29T09:22:18.172+07:00
        $stringToSign = $this->client->buildSymmetricStringToSign(
            'POST',
            '/snap/v1.0/dummy',
            'muhpwhwOkPRU9nNXYnyYHj8t54x3',
            '8b4e9e83b5231cff4f84358ec8ca81951cfe9f999f635b1566452a501d5c23b2',
            '2021-11-29T09:22:18.172+07:00'
        );

        $this->assertSame(
            'POST:/snap/v1.0/dummy:muhpwhwOkPRU9nNXYnyYHj8t54x3:8b4e9e83b5231cff4f84358ec8ca81951cfe9f999f635b1566452a501d5c23b2:2021-11-29T09:22:18.172+07:00',
            $stringToSign
        );
    }

    public function test_hash_body_matches_documented_known_answer()
    {
        // Dari bri-api.md "B. Signature API Access" > "5. Body":
        // Body: {"hello":"world"} -> SHA256 Result: 93a23971a914e5eacbf0a8d25154cda309c3c1c72fbb9914d47c60f3cb681588
        $this->assertSame(
            '93a23971a914e5eacbf0a8d25154cda309c3c1c72fbb9914d47c60f3cb681588',
            $this->client->hashBody('{"hello":"world"}')
        );
    }

    public function test_get_access_token_returns_token_from_response()
    {
        Http::fake([
            'https://fake-sandbox.test/snap/v1.0/access-token/b2b' => Http::response([
                'accessToken' => 'jwy7GgloLqfqbZ9OnxGxmYOuGu85',
                'tokenType' => 'BearerToken',
                'expiresIn' => '899',
            ], 200),
        ]);

        $token = $this->client->getAccessToken();

        $this->assertSame('jwy7GgloLqfqbZ9OnxGxmYOuGu85', $token);
    }

    public function test_get_access_token_is_cached_across_calls()
    {
        Http::fake([
            'https://fake-sandbox.test/snap/v1.0/access-token/b2b' => Http::response([
                'accessToken' => 'jwy7GgloLqfqbZ9OnxGxmYOuGu85',
                'tokenType' => 'BearerToken',
                'expiresIn' => '899',
            ], 200),
        ]);

        $this->client->getAccessToken();
        $this->client->getAccessToken();

        Http::assertSentCount(1);
    }

    public function test_get_access_token_throws_bri_api_exception_on_failure()
    {
        Http::fake([
            'https://fake-sandbox.test/snap/v1.0/access-token/b2b' => Http::response([
                'responseCode' => '4007301',
                'responseMessage' => 'Invalid Field Format',
            ], 400),
        ]);

        $this->expectException(BriApiException::class);

        $this->client->getAccessToken();
    }

    public function test_post_sends_body_matching_signature_and_returns_decoded_response()
    {
        Http::fake([
            'https://fake-sandbox.test/snap/v1.0/access-token/b2b' => Http::response([
                'accessToken' => 'jwy7GgloLqfqbZ9OnxGxmYOuGu85',
                'tokenType' => 'BearerToken',
                'expiresIn' => '899',
            ], 200),
            'https://fake-sandbox.test/snap/v1.1/qr/qr-mpm-generate' => Http::response([
                'responseCode' => '2004700',
                'responseMessage' => 'Successful',
                'partnerReferenceNo' => 'TEST0001',
                'qrContent' => '0002XXXXXXXXX',
                'referenceNo' => '409676201434',
            ], 200),
        ]);

        $result = $this->client->post('/snap/v1.1/qr/qr-mpm-generate', [
            'partnerReferenceNo' => 'TEST0001',
        ]);

        $this->assertSame('0002XXXXXXXXX', $result['qrContent']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://fake-sandbox.test/snap/v1.1/qr/qr-mpm-generate'
                && $request->hasHeader('Authorization', 'Bearer jwy7GgloLqfqbZ9OnxGxmYOuGu85')
                && $request->hasHeader('X-PARTNER-ID', '77777')
                && $request->hasHeader('CHANNEL-ID', '00001')
                && $request->hasHeader('X-SIGNATURE')
                && $request->hasHeader('X-TIMESTAMP');
        });
    }

    public function test_post_throws_bri_api_exception_on_non_success_response_code()
    {
        Http::fake([
            'https://fake-sandbox.test/snap/v1.0/access-token/b2b' => Http::response([
                'accessToken' => 'jwy7GgloLqfqbZ9OnxGxmYOuGu85',
                'tokenType' => 'BearerToken',
                'expiresIn' => '899',
            ], 200),
            'https://fake-sandbox.test/snap/v1.1/qr/qr-mpm-generate' => Http::response([
                'responseCode' => '4004701',
                'responseMessage' => 'Invalid Field Format',
            ], 400),
        ]);

        $this->expectException(BriApiException::class);

        $this->client->post('/snap/v1.1/qr/qr-mpm-generate', ['partnerReferenceNo' => 'TEST0001']);
    }
}
