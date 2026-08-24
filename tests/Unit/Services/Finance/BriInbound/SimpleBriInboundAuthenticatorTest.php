<?php

namespace Tests\Unit\Services\Finance\BriInbound;

use App\Domains\Keuangan\Services\BriInbound\SimpleBriInboundAuthenticator;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SimpleBriInboundAuthenticatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.bri.inbound.client_id' => 'test-client-id',
            'services.bri.inbound.client_secret' => 'test-client-secret',
        ]);
    }

    public function test_issue_token_returns_null_for_wrong_credentials()
    {
        $authenticator = new SimpleBriInboundAuthenticator();

        $this->assertNull($authenticator->issueToken('test-client-id', 'wrong-secret'));
        $this->assertNull($authenticator->issueToken('wrong-client-id', 'test-client-secret'));
        $this->assertNull($authenticator->issueToken('', ''));
    }

    public function test_issue_token_returns_valid_token_for_correct_credentials()
    {
        $authenticator = new SimpleBriInboundAuthenticator();

        $token = $authenticator->issueToken('test-client-id', 'test-client-secret');

        $this->assertNotNull($token);
        $this->assertTrue($authenticator->validateToken($token));
    }

    public function test_validate_token_rejects_unknown_token()
    {
        $authenticator = new SimpleBriInboundAuthenticator();

        $this->assertFalse($authenticator->validateToken('token-yang-tidak-pernah-diterbitkan'));
        $this->assertFalse($authenticator->validateToken(''));
    }
}
