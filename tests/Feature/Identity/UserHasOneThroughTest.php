<?php

use App\Domains\Identity\Actions\MergePersonsAction;
use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;

it('resolves $user->guru through the person hasOneThrough chain', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $user->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => $person->id]);

    expect($user->fresh()->guru?->id)->toBe($guru->id);
});

it('resolves $user->karyawan, orangTua, and siswa the same way, and returns null when absent', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $user->id]);
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'person_id' => $person->id]);

    expect($user->fresh()->karyawan?->id)->toBe($karyawan->id);
    expect($user->fresh()->orangTua)->toBeNull();
    expect($user->fresh()->siswa)->toBeNull();
});

it('resolves $user->guru through the winning person after a merge re-parents the guru row', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $losing = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $user->id]);
    $winning = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => null]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => $losing->id]);

    app(MergePersonsAction::class)->execute($losing, $winning);

    // MergePersonsAction carries user_id onto the winning Person and re-parents the
    // guru row's person_id to $winning in the same transaction, so $user->guru keeps
    // resolving correctly post-merge -- through the surviving Person, not the
    // soft-deleted losing one.
    expect($user->fresh()->guru?->id)->toBe($guru->id);
    expect($guru->refresh()->person_id)->toBe($winning->id);
});

it('does not resolve a role relation through a soft-deleted Person, even when its user_id link was left dangling', function () {
    // Regression guard for the scope-stripping bug: User::guru()/karyawan()/orangTua()/
    // siswa() previously called ->withoutGlobalScopes() (blanket), which also silently
    // stripped Person's SoftDeletingScope from the hasOneThrough join. In the real
    // MergePersonsAction flow this specific dangling state can't arise (user_id is
    // always cleared off the losing side before soft-delete), but the relation itself
    // must independently refuse to resolve through a soft-deleted Person -- this proves
    // the narrower ->withoutGlobalScope(TenantScope::class) fix still respects
    // SoftDeletingScope, unlike the blanket bypass it replaced.
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $user->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => $person->id]);

    $person->delete();

    expect($person->fresh()->trashed())->toBeTrue();
    expect($user->fresh()->guru)->toBeNull();
});
