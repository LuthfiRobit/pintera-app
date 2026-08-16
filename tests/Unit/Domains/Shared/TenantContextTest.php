<?php

namespace Tests\Unit\Domains\Shared;

use App\Domains\Shared\Context\TenantContext;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_active_lembaga_for_regular_user(): void
    {
        $yayasan = Yayasan::create(['nama' => 'Yayasan Pendidikan Utama']);
        $lembaga = Lembaga::create([
            'yayasan_id' => $yayasan->id,
            'nama' => 'SD Islam Terpadu',
            'jenjang' => 'SD',
            'npsn' => '12345678',
            'status_aktif' => true,
        ]);

        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

        $this->actingAs($user);
        $context = new TenantContext();

        $this->assertEquals($lembaga->id, $context->activeLembagaId());
        $this->assertEquals($yayasan->id, $context->activeYayasanId());
        $this->assertFalse($context->isYayasanScope());
    }

    public function test_resolves_active_lembaga_for_yayasan_user_via_session(): void
    {
        $yayasan = Yayasan::create(['nama' => 'Yayasan Pendidikan Utama']);
        $lembaga = Lembaga::create([
            'yayasan_id' => $yayasan->id,
            'nama' => 'SD Islam Terpadu',
            'jenjang' => 'SD',
            'npsn' => '12345678',
            'status_aktif' => true,
        ]);

        $roleYayasan = Role::create(['name' => 'pengurus_yayasan', 'guard_name' => 'web', 'scope_level' => 'yayasan']);
        $userYayasan = User::factory()->create(['lembaga_id' => null]);
        $userYayasan->assignRole($roleYayasan);

        $this->actingAs($userYayasan);
        session(['active_lembaga_id' => $lembaga->id]);

        $context = new TenantContext();

        $this->assertTrue($context->isYayasanScope());
        $this->assertEquals($lembaga->id, $context->activeLembagaId());
    }
}
