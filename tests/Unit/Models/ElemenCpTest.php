<?php

use App\Domains\Akademik\Contracts\SubjekPenilaian;
use App\Domains\Akademik\Models\ElemenCp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('implements the SubjekPenilaian marker interface', function () {
    $elemen = ElemenCp::factory()->create();

    expect($elemen)->toBeInstanceOf(SubjekPenilaian::class);
});
