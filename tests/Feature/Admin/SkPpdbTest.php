<?php

use App\Models\SkPpdb;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('generates a PDF, creates a sk_ppdb row, and links every finalized pendaftaran to it', function () {
    Storage::fake('public');
    [$lembaga, $jalur, $gelombang, $diterima] = buatPendaftaranUntukAdmin(namaCalon: 'Sudah Diterima', status: 'diterima');
    [, , , $ditolak] = buatPendaftaranUntukAdmin($lembaga, 'Sudah Ditolak', 'ditolak');
    $ditolak->update(['jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id]);
    [, , , $belumFinal] = buatPendaftaranUntukAdmin($lembaga, 'Masih Menunggu', 'menunggu_verifikasi');
    $belumFinal->update(['jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    $response = $this->actingAs($user)->post(route('admin.sk-ppdb.store'), [
        'gelombang_ppdb_id' => $gelombang->id,
        'nomor_sk' => '421.3/SK-PPDB.001/2026',
        'tanggal_terbit' => now()->toDateString(),
    ]);

    $response->assertRedirect();
    $sk = SkPpdb::first();
    expect($sk)->not->toBeNull();
    expect($sk->nomor_sk)->toBe('421.3/SK-PPDB.001/2026');
    expect($sk->diterbitkan_oleh_user_id)->toBe($user->id);
    Storage::disk('public')->assertExists($sk->file_path);

    expect($diterima->fresh()->sk_ppdb_id)->toBe($sk->id);
    expect($ditolak->fresh()->sk_ppdb_id)->toBe($sk->id);
    expect($belumFinal->fresh()->sk_ppdb_id)->toBeNull();
});

it('allows issuing a second sk for pendaftaran that became final after the first sk', function () {
    Storage::fake('public');
    [$lembaga, $jalur, $gelombang, $pertama] = buatPendaftaranUntukAdmin(namaCalon: 'Batch Pertama', status: 'diterima');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');
    $this->actingAs($user)->post(route('admin.sk-ppdb.store'), [
        'gelombang_ppdb_id' => $gelombang->id, 'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
    ]);

    [, , , $susulan] = buatPendaftaranUntukAdmin($lembaga, 'Batch Susulan', 'diterima');
    $susulan->update(['jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id]);

    $this->actingAs($user)->post(route('admin.sk-ppdb.store'), [
        'gelombang_ppdb_id' => $gelombang->id, 'nomor_sk' => '421.3/SK-PPDB.002-SUSULAN/2026', 'tanggal_terbit' => now()->addWeek()->toDateString(),
    ]);

    expect(SkPpdb::count())->toBe(2);
    $skPertama = SkPpdb::where('nomor_sk', '421.3/SK-PPDB.001/2026')->first();
    $skKedua = SkPpdb::where('nomor_sk', '421.3/SK-PPDB.002-SUSULAN/2026')->first();
    expect($susulan->fresh()->sk_ppdb_id)->toBe($skKedua->id);
    // Asserting equality with the ORIGINAL sk (not just inequality with the new one) is the
    // real "unchanged" claim: inequality alone would still pass if a regression nulled the
    // field out instead of overwriting it, which is not what actually happened here but is
    // exactly the kind of drift this test exists to catch.
    expect($pertama->fresh()->sk_ppdb_id)->toBe($skPertama->id);
});

it('rejects a duplicate nomor_sk for the same lembaga with a validation error', function () {
    Storage::fake('public');
    [$lembaga, , $gelombang] = buatPendaftaranUntukAdmin(status: 'diterima');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');
    $this->actingAs($user)->post(route('admin.sk-ppdb.store'), [
        'gelombang_ppdb_id' => $gelombang->id, 'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
    ]);

    $response = $this->actingAs($user)->post(route('admin.sk-ppdb.store'), [
        'gelombang_ppdb_id' => $gelombang->id, 'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
    ]);

    $response->assertSessionHasErrors('nomor_sk');
    expect(SkPpdb::count())->toBe(1);
});

it('denies terbitkan sk without the terbitkan-sk permission', function () {
    [$lembaga, , $gelombang] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $this->actingAs($user)->post(route('admin.sk-ppdb.store'), [
        'gelombang_ppdb_id' => $gelombang->id, 'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
    ])->assertForbidden();
});

it('allows two different lembaga to use the identical nomor_sk without conflict', function () {
    Storage::fake('public');
    [$lembagaA, , $gelombangA] = buatPendaftaranUntukAdmin(status: 'diterima');
    [$lembagaB, , $gelombangB] = buatPendaftaranUntukAdmin(status: 'diterima');
    $userA = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $userA->assignRole('kepala_sekolah');
    $userB = User::factory()->create(['lembaga_id' => $lembagaB->id]);
    $userB->assignRole('kepala_sekolah');

    $responseA = $this->actingAs($userA)->post(route('admin.sk-ppdb.store'), [
        'gelombang_ppdb_id' => $gelombangA->id, 'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
    ]);
    $responseB = $this->actingAs($userB)->post(route('admin.sk-ppdb.store'), [
        'gelombang_ppdb_id' => $gelombangB->id, 'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
    ]);

    $responseA->assertSessionDoesntHaveErrors('nomor_sk');
    $responseB->assertSessionDoesntHaveErrors('nomor_sk');
    // SkPpdb is tenant-scoped (BelongsToTenant), so querying as the still-"logged in" $userB
    // would only see lembagaB's row; withoutGlobalScopes() confirms both rows genuinely exist.
    expect(SkPpdb::withoutGlobalScopes()->count())->toBe(2);
    expect(SkPpdb::withoutGlobalScopes()->where('lembaga_id', $lembagaA->id)->where('nomor_sk', '421.3/SK-PPDB.001/2026')->exists())->toBeTrue();
    expect(SkPpdb::withoutGlobalScopes()->where('lembaga_id', $lembagaB->id)->where('nomor_sk', '421.3/SK-PPDB.001/2026')->exists())->toBeTrue();
});

it('denies the terbitkan sk form without the terbitkan-sk permission', function () {
    [$lembaga] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $this->actingAs($user)->get(route('admin.sk-ppdb.create'))->assertForbidden();
});

it('issues a SK for a yayasan-scoped user once an active lembaga is selected in session', function () {
    Storage::fake('public');
    [$lembaga, , $gelombang, $diterima] = buatPendaftaranUntukAdmin(namaCalon: 'Sudah Diterima', status: 'diterima');
    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('yayasan_super_admin');

    $response = $this->actingAs($user)
        ->withSession(['active_lembaga_id' => $lembaga->id])
        ->post(route('admin.sk-ppdb.store'), [
            'gelombang_ppdb_id' => $gelombang->id,
            'nomor_sk' => '421.3/SK-PPDB.YAY/2026',
            'tanggal_terbit' => now()->toDateString(),
        ]);

    $response->assertRedirect();
    $sk = SkPpdb::first();
    expect($sk)->not->toBeNull();
    expect($diterima->fresh()->sk_ppdb_id)->toBe($sk->id);
});

it('redirects back with a lembaga_id error, not a 500, for a yayasan-scoped user with no active lembaga selected', function () {
    [, , $gelombang] = buatPendaftaranUntukAdmin(status: 'diterima');
    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('yayasan_super_admin');

    $response = $this->actingAs($user)->post(route('admin.sk-ppdb.store'), [
        'gelombang_ppdb_id' => $gelombang->id,
        'nomor_sk' => '421.3/SK-PPDB.YAY/2026',
        'tanggal_terbit' => now()->toDateString(),
    ]);

    $response->assertSessionHasErrors('lembaga_id');
    expect(SkPpdb::count())->toBe(0);
});

it('excludes pendaftaran already linked to a prior sk from the create-form summary count', function () {
    Storage::fake('public');
    [$lembaga, , $gelombang] = buatPendaftaranUntukAdmin(namaCalon: 'Batch Pertama', status: 'diterima');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');
    $this->actingAs($user)->post(route('admin.sk-ppdb.store'), [
        'gelombang_ppdb_id' => $gelombang->id, 'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
    ]);

    $response = $this->actingAs($user)->get(route('admin.sk-ppdb.create', ['gelombang_ppdb_id' => $gelombang->id]));

    $response->assertOk();
    $response->assertViewHas('ringkasan', fn ($ringkasan) => $ringkasan['final'] === 0);
});
