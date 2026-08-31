<?php

use App\Domains\Identity\Actions\UpdatePersonAction;
use App\Domains\Identity\Models\Person;
use App\Models\Yayasan;

it('updates identity fields but ignores yayasan_id if present in the payload', function () {
    $yayasan = Yayasan::factory()->create();
    $other = Yayasan::factory()->create();
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => 'Nama Lama']);

    $updated = app(UpdatePersonAction::class)->execute($person, [
        'nama_lengkap' => 'Nama Baru',
        'yayasan_id' => $other->id,
    ]);

    expect($updated->nama_lengkap)->toBe('Nama Baru');
    expect($updated->yayasan_id)->toBe($yayasan->id);
});
