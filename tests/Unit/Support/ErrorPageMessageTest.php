<?php
// tests/Unit/Support/ErrorPageMessageTest.php

use App\Support\ErrorPageMessage;

it('returns the exception message when present', function () {
    $exception = new \Exception('Data kepegawaian Anda tidak ditemukan.');

    expect(ErrorPageMessage::resolve($exception, 'Fallback default.'))
        ->toBe('Data kepegawaian Anda tidak ditemukan.');
});

it('falls back when there is no exception', function () {
    expect(ErrorPageMessage::resolve(null, 'Fallback default.'))->toBe('Fallback default.');
});

it('falls back when the exception message is empty', function () {
    $exception = new \Exception('');

    expect(ErrorPageMessage::resolve($exception, 'Fallback default.'))->toBe('Fallback default.');
});

it('falls back when the message leaks a ModelNotFoundException-style technical detail', function () {
    $exception = new \Exception('No query results for model [App\\Models\\Lembaga] 5');

    expect(ErrorPageMessage::resolve($exception, 'Fallback default.'))->toBe('Fallback default.');
});

it('falls back when the message is Laravel default route-not-found technical text', function () {
    $exception = new \Exception('The route some/uri could not be found.');

    expect(ErrorPageMessage::resolve($exception, 'Fallback default.'))->toBe('Fallback default.');
});

it('always falls back when allowDynamic is false, even with a safe message', function () {
    $exception = new \Exception('Query error: SQLSTATE[HY000] connection refused on host db-internal');

    expect(ErrorPageMessage::resolve($exception, 'Fallback default.', allowDynamic: false))
        ->toBe('Fallback default.');
});
