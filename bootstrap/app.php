<?php

use App\Http\Middleware\AuthenticateDeviceApiKey;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureSchoolLicenseIsValid;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'device.key' => AuthenticateDeviceApiKey::class,
            'role' => EnsureUserHasRole::class,
            'license.valid' => EnsureSchoolLicenseIsValid::class,
        ]);
        $middleware->appendToGroup('web', EnsurePasswordIsChanged::class);
        $middleware->appendToGroup('web', EnsureSchoolLicenseIsValid::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
