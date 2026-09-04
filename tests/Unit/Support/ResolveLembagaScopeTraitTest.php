<?php

declare(strict_types=1);

use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function objekPakaiResolveLembagaScope(): object
{
    return new class
    {
        use ResolveLembagaScopeTrait;

        public function panggilResolve(User $actor, ?int $lembagaIdDiminta): ?int
        {
            return $this->resolveLembagaId($actor, $lembagaIdDiminta);
        }
    };
}

it('platform bebas memilih lembaga_id apapun, termasuk null (global)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $platform = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => null]);
    $platform->assignRole(Role::firstOrCreate(['name' => 'platform_admin_uji', 'guard_name' => 'web'], ['scope_level' => 'platform']));

    $obj = objekPakaiResolveLembagaScope();

    expect($obj->panggilResolve($platform, $lembaga->id))->toBe($lembaga->id);
    expect($obj->panggilResolve($platform, null))->toBeNull();
});

it('yayasan memakai session active_lembaga_id, MENGABAIKAN lembagaIdDiminta sama sekali', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaAktif = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $actor = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $actor->assignRole(Role::firstOrCreate(['name' => 'yayasan_admin_uji', 'guard_name' => 'web'], ['scope_level' => 'yayasan']));
    session(['active_lembaga_id' => $lembagaAktif->id]);

    $obj = objekPakaiResolveLembagaScope();

    // lembagaIdDiminta = $lembagaLain->id sengaja BEDA dari session -- harus diabaikan total.
    expect($obj->panggilResolve($actor, $lembagaLain->id))->toBe($lembagaAktif->id);
});

it('yayasan tanpa active_lembaga_id di sesi ditolak dengan 422', function () {
    $yayasan = Yayasan::factory()->create();
    $actor = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $actor->assignRole(Role::firstOrCreate(['name' => 'yayasan_admin_uji2', 'guard_name' => 'web'], ['scope_level' => 'yayasan']));
    session()->forget('active_lembaga_id');

    $obj = objekPakaiResolveLembagaScope();

    expect(fn () => $obj->panggilResolve($actor, null))
        ->toThrow(HttpException::class);
});

it('yayasan dengan active_lembaga_id stale (lembaga di luar yayasannya) ditolak dengan 422', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaMilikYayasanLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $actor = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $actor->assignRole(Role::firstOrCreate(['name' => 'yayasan_admin_uji3', 'guard_name' => 'web'], ['scope_level' => 'yayasan']));
    session(['active_lembaga_id' => $lembagaMilikYayasanLain->id]);

    $obj = objekPakaiResolveLembagaScope();

    expect(fn () => $obj->panggilResolve($actor, null))
        ->toThrow(HttpException::class);
});

it('lembaga-scope selalu memakai lembaga_id miliknya sendiri, mengabaikan lembagaIdDiminta', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $actor = User::factory()->create(['lembaga_id' => $lembagaSaya->id]);

    $obj = objekPakaiResolveLembagaScope();

    expect($obj->panggilResolve($actor, $lembagaLain->id))->toBe($lembagaSaya->id);
});
