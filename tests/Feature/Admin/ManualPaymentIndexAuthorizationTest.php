<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\ManualPaymentRequest;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Models\User;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function buatAdminDanRequestUntukLembaga(string $label): array
{
    Permission::firstOrCreate(['name' => 'pembayaran.verifikasi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_keuangan', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('pembayaran.verifikasi');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('admin_keuangan');

    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => "Anak {$label}"]);
    $jenis = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenis->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);
    $manualRequest = ManualPaymentRequest::create([
        'pembayaran_id' => $pembayaran->id, 'requested_by' => User::factory()->create()->id, 'amount' => 100000,
        'transfer_proof_path' => 'bukti-transfer/x.jpg', 'transfer_date' => now()->toDateString(), 'status' => 'PENDING',
    ]);

    return [$admin, $lembaga, $manualRequest];
}

it('does not show another lembaga\'s pending request in the listing', function () {
    [$adminA, , $reqA] = buatAdminDanRequestUntukLembaga('A');
    [, , $reqB] = buatAdminDanRequestUntukLembaga('B');

    $response = $this->actingAs($adminA)->get(route('admin.manual-payment.index'));

    $response->assertOk();
    $response->assertSee('Anak A');
    $response->assertDontSee('Anak B');
});

it('does not count another lembaga\'s pending requests in the KPI totals', function () {
    [$adminA, , $reqA] = buatAdminDanRequestUntukLembaga('A');
    buatAdminDanRequestUntukLembaga('B');
    buatAdminDanRequestUntukLembaga('C');

    $response = $this->actingAs($adminA)->get(route('admin.manual-payment.index'));

    $response->assertOk();
    $response->assertViewHas('totalMenunggu', 1);
    $response->assertViewHas('totalNominalMenunggu', (float) $reqA->amount);
});
