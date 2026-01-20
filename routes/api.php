<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExternalApiDemoController;
use App\Support\UpstreamErrorMapper;

Route::get('/external/demo', [ExternalApiDemoController::class, 'demo']);

Route::get('/external/simulate-timeout', function () {
    abort_unless(app()->environment('local'), 404);
    $mapped = UpstreamErrorMapper::fromThrowable(new \RuntimeException('Operation timed out'));

    return response()->json($mapped, $mapped['error']['http_status']);
});

Route::get('/external/simulate-connection', function () {
    abort_unless(app()->environment('local'), 404);
    $mapped = UpstreamErrorMapper::fromThrowable(new \Illuminate\Http\Client\ConnectionException('Could not resolve host'));

    return response()->json($mapped, $mapped['error']['http_status']);
});