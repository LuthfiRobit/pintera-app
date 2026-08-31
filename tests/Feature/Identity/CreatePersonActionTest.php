<?php

use App\Domains\Identity\Actions\CreatePersonAction;
use App\Domains\Identity\Exceptions\PersonAlreadyExistsException;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('creates a Person deriving yayasan_id from the given lembaga_id', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $person = app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Siti Aminah', 'nik' => '9999999999999999'],
        lembagaId: $lembaga->id,
        actingYayasanId: null,
    );

    expect($person->yayasan_id)->toBe($yayasan->id);
});

it('creates a Person using actingYayasanId when there is no lembaga (pool entity)', function () {
    $yayasan = Yayasan::factory()->create();

    $person = app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Andi Wijaya'],
        lembagaId: null,
        actingYayasanId: $yayasan->id,
    );

    expect($person->yayasan_id)->toBe($yayasan->id);
});

it('aborts with 422 when neither lembagaId nor actingYayasanId is given', function () {
    app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Tanpa Konteks'],
        lembagaId: null,
        actingYayasanId: null,
    );
})->throws(HttpException::class);

it('rejects a duplicate NIK within the same yayasan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Orang Pertama', 'nik' => '1010101010101010'],
        lembagaId: $lembaga->id,
        actingYayasanId: null,
    );

    app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Orang Kedua Nik Sama', 'nik' => '1010101010101010'],
        lembagaId: $lembaga->id,
        actingYayasanId: null,
    );
})->throws(PersonAlreadyExistsException::class);

it('allows the same NIK across two different yayasan -- this is a contract, not a bug', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);

    app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Orang A', 'nik' => '2020202020202020'],
        lembagaId: $lembagaA->id,
        actingYayasanId: null,
    );

    $personB = app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Orang B', 'nik' => '2020202020202020'],
        lembagaId: $lembagaB->id,
        actingYayasanId: null,
    );

    expect($personB->yayasan_id)->toBe($yayasanB->id);
});

// --- Regression coverage for Person::YayasanScope interaction ---
//
// Person carries its own YayasanScope global scope, which filters queries by
// the *acting authenticated user's* own yayasan_id (see
// app/Models/Scopes/YayasanScope.php). The dedup lookup in CreatePersonAction
// also filters explicitly by the resolved $yayasanId. These two filters are
// ANDed together by Eloquent when both apply to the same query, so if they
// ever disagree, the dedup lookup would incorrectly find nothing even when a
// real duplicate exists. The action bypasses YayasanScope on that lookup
// (Person::withoutGlobalScope(YayasanScope::class)) specifically to avoid
// this. The tests below prove the dedup check is safe under an authenticated
// actor, both when the actor's own yayasan matches the target yayasan (the
// expected, common case) and when it does not (the edge case that would
// silently break without the withoutGlobalScope call).

it('still rejects a duplicate NIK when the acting user belongs to the same yayasan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $actingUser = User::factory()->create(['yayasan_id' => $yayasan->id]);

    $this->actingAs($actingUser);

    app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Orang Pertama', 'nik' => '3030303030303030'],
        lembagaId: $lembaga->id,
        actingYayasanId: null,
    );

    app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Orang Kedua Nik Sama', 'nik' => '3030303030303030'],
        lembagaId: $lembaga->id,
        actingYayasanId: null,
    );
})->throws(PersonAlreadyExistsException::class);

it('still rejects a duplicate NIK in the target yayasan even when the acting user belongs to a different yayasan', function () {
    $targetYayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $targetYayasan->id]);

    $otherYayasan = Yayasan::factory()->create();
    $actingUser = User::factory()->create(['yayasan_id' => $otherYayasan->id]);

    app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Orang Pertama', 'nik' => '4040404040404040'],
        lembagaId: $lembaga->id,
        actingYayasanId: null,
    );

    $this->actingAs($actingUser);

    app(CreatePersonAction::class)->execute(
        identityData: ['nama_lengkap' => 'Orang Kedua Nik Sama', 'nik' => '4040404040404040'],
        lembagaId: $lembaga->id,
        actingYayasanId: null,
    );
})->throws(PersonAlreadyExistsException::class);
