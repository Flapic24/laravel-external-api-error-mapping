<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
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

Route::get('/external/simulate/{status}', function (int $status) {
    abort_unless(app()->environment('local'), 404);

    $headers = [];
    $body = null;

    if ($status === 422) {
        $body = ['errors' => ['email' => ['Required']]];
    }

    if ($status === 429) {
        $body = ['error' => 'Too many requests'];
        $headers['Retry-After'] = '30';
    }

    if ($status === 503) {
        $body = ['error' => 'Service unavailable'];
        $headers['Retry-After'] = '60';
    }

    if ($status >= 500 && $body === null) {
        $body = ['error' => 'Upstream server error'];
    }

    $psr7 = new \GuzzleHttp\Psr7\Response(
        $status,
        $headers,
        is_array($body) ? json_encode($body) : (string) $body
    );

    $clientResponse = new \Illuminate\Http\Client\Response($psr7);

    $mapped = UpstreamErrorMapper::fromResponse($clientResponse);

    return response()->json($mapped, $mapped['error']['http_status']);
});