<?php

namespace App\Http\Controllers;

use App\Support\UpstreamErrorMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class ExternalApiDemoController extends Controller
{
    public function demo(): JsonResponse
    {
        $url = 'https://this-domain-should-not-exist.example/api';

        try {
            $response = Http::timeout(3)->get($url);

            if ($response->failed()) {
                $mapped = UpstreamErrorMapper::fromResponse($response);
                return response()->json($mapped, $mapped['error']['http_status']);
            }

            return response()->json([
                'ok' => true,
                'data' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            $mapped = UpstreamErrorMapper::fromThrowable($e);
            return response()->json($mapped, $mapped['error']['http_status']);
        }
    }
}