<?php

use App\Domains\Identity\Actions\MergePersonsAction;
use App\Domains\Identity\Exceptions\ConflictingUserAccountsException;
use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('re-parents all role-table FKs to the winning person and soft-deletes the losing one', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $losing = Person::factory()->create(['yayasan_id' => $yayasan->id]);
    $winning = Person::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => $losing->id]);
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'person_id' => $losing->id]);

    app(MergePersonsAction::class)->execute($losing, $winning);

    expect($guru->refresh()->person_id)->toBe($winning->id);
    expect($karyawan->refresh()->person_id)->toBe($winning->id);
    expect($losing->refresh()->merged_into_person_id)->toBe($winning->id);
    expect(Person::withoutGlobalScopes()->find($losing->id))->not->toBeNull();
    expect(Person::find($losing->id))->toBeNull(); // soft-deleted, excluded from default query
});

it('rejects merging two persons from different yayasan', function () {
    $losing = Person::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $winning = Person::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);

    app(MergePersonsAction::class)->execute($losing, $winning);
})->throws(HttpException::class);

it('throws ConflictingUserAccountsException when both persons already have a user_id', function () {
    $yayasan = Yayasan::factory()->create();
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $losing = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $userA->id]);
    $winning = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $userB->id]);

    app(MergePersonsAction::class)->execute($losing, $winning);
})->throws(ConflictingUserAccountsException::class);

it('carries the losing user_id onto the winning person when only the losing side has an account', function () {
    $yayasan = Yayasan::factory()->create();
    $user = User::factory()->create();
    $losing = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $user->id]);
    $winning = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => null]);

    app(MergePersonsAction::class)->execute($losing, $winning);

    expect($winning->refresh()->user_id)->toBe($user->id);
});

it('rejects a cross-yayasan merge before checking conflicting user accounts', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $losing = Person::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id, 'user_id' => $userA->id]);
    $winning = Person::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id, 'user_id' => $userB->id]);

    try {
        app(MergePersonsAction::class)->execute($losing, $winning);
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(HttpException::class);
        expect($e)->not->toBeInstanceOf(ConflictingUserAccountsException::class);

        return;
    }

    $this->fail('Expected an HttpException to be thrown.');
});
