<?php

declare(strict_types=1);

use App\Domains\Akademik\Support\ResolveAnakOrangTuaTrait;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function objekPakaiResolveAnakOrangTua(): object
{
    return new class
    {
        use ResolveAnakOrangTuaTrait;

        public function panggilResolveList(User $actor)
        {
            return $this->resolveAnakList($actor);
        }

        public function panggilResolveTerpilih($anakList, ?int $siswaIdDiminta): ?Siswa
        {
            return $this->resolveAnakTerpilih($anakList, $siswaIdDiminta);
        }
    };
}

it('resolveAnakList: mengembalikan collection kosong kalau user bukan orang tua', function () {
    $user = User::factory()->create();
    $obj = objekPakaiResolveAnakOrangTua();

    expect($obj->panggilResolveList($user))->toHaveCount(0);
});

it('resolveAnakList: mengembalikan semua anak yang terhubung lewat pivot siswa_orang_tua', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create();
    $orangTua = OrangTua::factory()->create(['user_id' => $user->id]);
    $anakSatu = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $anakDua = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua->siswa()->attach([$anakSatu->id => ['hubungan' => 'ayah'], $anakDua->id => ['hubungan' => 'ayah']]);

    $obj = objekPakaiResolveAnakOrangTua();

    $anakList = $obj->panggilResolveList($user->fresh());
    expect($anakList->pluck('id')->sort()->values()->all())->toBe(collect([$anakSatu->id, $anakDua->id])->sort()->values()->all());
});

it('resolveAnakTerpilih: mengembalikan anak sesuai siswa_id kalau ada di daftar', function () {
    $anakSatu = Siswa::factory()->make(['id' => 1]);
    $anakDua = Siswa::factory()->make(['id' => 2]);
    $anakList = collect([$anakSatu, $anakDua]);
    $obj = objekPakaiResolveAnakOrangTua();

    expect($obj->panggilResolveTerpilih($anakList, 2)->id)->toBe(2);
});

it('resolveAnakTerpilih: diam-diam fallback ke anak pertama kalau siswa_id tidak ada di daftar (IDOR guard)', function () {
    $anakSatu = Siswa::factory()->make(['id' => 1]);
    $anakDua = Siswa::factory()->make(['id' => 2]);
    $anakList = collect([$anakSatu, $anakDua]);
    $obj = objekPakaiResolveAnakOrangTua();

    // 999 = ID anak orang tua LAIN (bukan milik actor ini) -- harus diabaikan, bukan error.
    expect($obj->panggilResolveTerpilih($anakList, 999)->id)->toBe(1);
});

it('resolveAnakTerpilih: mengembalikan null kalau anakList kosong', function () {
    $obj = objekPakaiResolveAnakOrangTua();

    expect($obj->panggilResolveTerpilih(collect(), null))->toBeNull();
});
