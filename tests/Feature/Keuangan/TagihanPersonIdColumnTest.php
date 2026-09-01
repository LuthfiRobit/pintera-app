<?php

use App\Domains\Keuangan\Models\Tagihan;

it('tagihan table has a nullable person_id column', function () {
    $tagihan = Tagihan::factory()->create();

    expect($tagihan->getAttributes())->toHaveKey('person_id');
    expect(Tagihan::factory()->make(['person_id' => null])->person_id)->toBeNull();
});
