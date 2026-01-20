<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class ExternalApiDemoController extends Controller
{
    public function demo(): JsonResponse
    {
        // Direkt “rossz” hívás: nem létező domain → tipikus network error
        $url = 'https://this-domain-should-not-exist.example/api';

        try {
            $response = Http::timeout(3)->get($url);

            if ($response->failed()) {
                // ide majd jön a mapping
                return response()->json([
                    'ok' => false,
                    'type' => 'UPSTREAM_HTTP_ERROR',
                    'status' => $response->status(),
                    'body' => $response->json(),
                ], 502);
            }

            return response()->json([
                'ok' => true,
                'data' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            // ide majd jön a mapping (network / timeout / DNS / etc)
            return response()->json([
                'ok' => false,
                'type' => 'UPSTREAM_NETWORK_ERROR',
                'message' => $e->getMessage(),
            ], 502);
        }
    }
}
