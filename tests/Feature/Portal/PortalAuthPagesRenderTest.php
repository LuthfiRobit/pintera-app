<?php

it('renders every pre-login portal auth page without a lembaga in scope', function (string $path) {
    $this->get($path)->assertOk();
})->with([
    '/portal/register',
    '/portal/login',
    '/portal/forgot-password',
    '/portal/verifikasi-otp',
]);
