<?php

namespace Tests\Feature\Keuangan;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BriVaInboundTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.bri.inbound.client_id' => 'test-client-id',
            'services.bri.inbound.client_secret' => 'test-client-secret',
        ]);
    }

    public function test_token_endpoint_returns_access_token_for_correct_credentials()
    {
        $response = $this->postJson('/snap/v1.0/access-token/b2b', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['accessToken', 'tokenType', 'expiresIn']);
        $this->assertSame('BearerToken', $response->json('tokenType'));
    }

    public function test_token_endpoint_rejects_wrong_credentials()
    {
        $response = $this->postJson('/snap/v1.0/access-token/b2b', [
            'client_id' => 'test-client-id',
            'client_secret' => 'salah',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['responseCode' => '4017300']);
    }
}
