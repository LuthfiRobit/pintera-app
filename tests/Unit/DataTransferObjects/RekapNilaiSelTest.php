<?php

use App\Domains\Akademik\DataTransferObjects\RekapNilaiSel;
use App\Domains\Akademik\Enums\AssessmentType;

it('holds assessmentType, label, and tuntas as readonly properties', function () {
    $sel = new RekapNilaiSel(
        assessmentType: AssessmentType::Numeric,
        label: '88',
        tuntas: true,
    );

    expect($sel->assessmentType)->toBe(AssessmentType::Numeric);
    expect($sel->label)->toBe('88');
    expect($sel->tuntas)->toBeTrue();
});

it('allows tuntas to be null for non-numeric assessment types', function () {
    $sel = new RekapNilaiSel(
        assessmentType: AssessmentType::Predicate,
        label: 'BSH',
        tuntas: null,
    );

    expect($sel->tuntas)->toBeNull();
});
