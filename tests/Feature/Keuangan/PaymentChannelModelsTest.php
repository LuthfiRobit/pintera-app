<?php

namespace Tests\Feature\Keuangan;

use App\Models\BriQrisPayment;
use App\Models\BriVirtualAccount;
use App\Models\ManualPaymentRequest;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentChannelModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pembayaran_table_has_new_columns_and_enum()
    {
        $this->assertTrue(Schema::hasColumn('pembayaran', 'siswa_id'));
        $this->assertTrue(Schema::hasColumn('pembayaran', 'topup_status'));
        
        // Buat record untuk test enum (akan error jika enum tidak sesuai)
        $siswa = Siswa::factory()->create();
        $pembayaran = Pembayaran::factory()->create([
            'siswa_id' => $siswa->id,
            'status' => 'menunggu_pembayaran',
            'topup_status' => 'pending',
        ]);
        
        $this->assertEquals('menunggu_pembayaran', $pembayaran->status);
        $this->assertEquals('pending', $pembayaran->topup_status);
        $this->assertTrue($pembayaran->siswa->is($siswa));
    }

    public function test_bri_virtual_accounts_model_and_schema()
    {
        $this->assertTrue(Schema::hasTable('bri_virtual_accounts'));
        
        $pembayaran = Pembayaran::factory()->create();
        $siswa = Siswa::factory()->create();
        $wallet = $siswa->wallet;
        
        $va = BriVirtualAccount::create([
            'pembayaran_id' => $pembayaran->id,
            'wallet_id' => $wallet->id,
            'va_type' => 'WALLET_PERMANENT',
            'va_number' => '1234567890',
            'amount' => null,
            'expired_at' => null,
            'status' => 'PERMANENT',
            'callback_payload' => ['foo' => 'bar'],
        ]);
        
        $this->assertIsArray($va->callback_payload);
        $this->assertEquals('bar', $va->callback_payload['foo']);
        $this->assertTrue($va->pembayaran->is($pembayaran));
        $this->assertTrue($va->wallet->is($wallet));
        $this->assertTrue($pembayaran->briVirtualAccount->is($va));
    }
    
    public function test_bri_qris_payments_model_and_schema()
    {
        $this->assertTrue(Schema::hasTable('bri_qris_payments'));
        
        $pembayaran = Pembayaran::factory()->create();
        
        $qris = BriQrisPayment::create([
            'pembayaran_id' => $pembayaran->id,
            'qris_type' => 'DIRECT',
            'amount' => 50000,
            'qr_code' => 'DUMMY_QR_STRING',
            'expired_at' => now()->addMinutes(15),
            'status' => 'WAITING',
            'callback_payload' => ['qr' => 'data'],
        ]);
        
        $this->assertIsArray($qris->callback_payload);
        $this->assertEquals('data', $qris->callback_payload['qr']);
        $this->assertTrue($qris->pembayaran->is($pembayaran));
        $this->assertTrue($pembayaran->briQrisPayment->is($qris));
    }

    public function test_manual_payment_requests_model_and_schema()
    {
        $this->assertTrue(Schema::hasTable('manual_payment_requests'));
        
        $pembayaran = Pembayaran::factory()->create();
        $user = User::factory()->create();
        
        $manual = ManualPaymentRequest::create([
            'pembayaran_id' => $pembayaran->id,
            'requested_by' => $user->id,
            'amount' => 50000,
            'transfer_proof_path' => 'proofs/test.jpg',
            'bank_origin' => 'BCA',
            'transfer_date' => now()->toDateString(),
            'status' => 'PENDING',
        ]);
        
        $this->assertTrue($manual->pembayaran->is($pembayaran));
        $this->assertTrue($manual->requestedBy->is($user));
        $this->assertTrue($pembayaran->manualRequest->is($manual));
    }
}
