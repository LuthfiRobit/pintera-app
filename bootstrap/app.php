<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\ResolveTenant::class,
            \App\Http\Middleware\EnsurePasswordIsChanged::class,
        ]);

        $middleware->alias([
            'portal.verified' => \App\Http\Middleware\EnsureAkunPendaftarVerified::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'resolve.active.siswa' => \App\Http\Middleware\ResolveActiveSiswa::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'snap/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
