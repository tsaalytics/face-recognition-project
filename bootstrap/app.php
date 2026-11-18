<?php

use App\Http\Middleware\EnsureActive;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\ProtectBiometricRequests;
use App\Http\Middleware\EnsureKioskDevice;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ProtectBiometricRequests::class);
        $middleware->alias(['role' => EnsureRole::class, 'kiosk.device' => EnsureKioskDevice::class]);
        $middleware->appendToGroup('web', EnsureActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['samples', 'activation_code']);
        $exceptions->respond(function ($response) {
            return ProtectBiometricRequests::applies(request()) ? ProtectBiometricRequests::protect($response) : $response;
        });
    })->create();
