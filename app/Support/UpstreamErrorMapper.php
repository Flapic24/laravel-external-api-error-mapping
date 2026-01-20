<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

final class UpstreamErrorMapper
{
    /**
     * Upstream HTTP válasz hibára mapping (pl 401/404/422/500)
     */
    public static function fromResponse(Response $response): array
    {
        $status = $response->status();

        // alapértelmezés: upstream hiba -> 502 Bad Gateway
        $httpStatus = 502;

        // 4xx: upstream "elutasítás" vagy validáció -> legtöbbször nem retry
        if ($status >= 400 && $status < 500) {
            $code = match ($status) {
                401 => 'UPSTREAM_UNAUTHORIZED',
                403 => 'UPSTREAM_FORBIDDEN',
                404 => 'UPSTREAM_NOT_FOUND',
                409 => 'UPSTREAM_CONFLICT',
                422 => 'UPSTREAM_VALIDATION_FAILED',
                429 => 'UPSTREAM_RATE_LIMITED',
                default => 'UPSTREAM_CLIENT_ERROR',
            };

            $message = match ($status) {
                401 => 'A külső szolgáltató elutasította a kérést (auth).',
                403 => 'A külső szolgáltató tiltja a kérést.',
                404 => 'A külső szolgáltatónál nem található az erőforrás.',
                422 => 'A külső szolgáltató validációs hibát jelzett.',
                429 => 'A külső szolgáltató túl sok kérést kapott (rate limit).',
                default => 'A külső szolgáltató 4xx hibát jelzett.',
            };

            $retryable = ($status === 429);
            return self::shape($code, $message, $httpStatus, $retryable, [
                'upstream_status' => $status,
                'upstream_body' => self::safeBody($response),
            ]);
        }

        // 5xx: upstream szerverhiba -> általában retryable
        if ($status >= 500) {
            $code = match ($status) {
                502 => 'UPSTREAM_BAD_GATEWAY',
                503 => 'UPSTREAM_UNAVAILABLE',
                504 => 'UPSTREAM_TIMEOUT',
                default => 'UPSTREAM_SERVER_ERROR',
            };

            $message = 'A külső szolgáltató hibát jelzett. Kérjük, próbáld újra később.';
            $retryable = true;

            return self::shape($code, $message, $httpStatus, $retryable, [
                'upstream_status' => $status,
                'upstream_body' => self::safeBody($response),
            ]);
        }

        // Ide elvileg nem jutunk, de legyen stabil
        return self::shape(
            'UPSTREAM_UNKNOWN_ERROR',
            'Ismeretlen hiba történt a külső szolgáltatóval.',
            $httpStatus,
            false,
            ['upstream_status' => $status]
        );
    }

    /**
     * Throwable mapping (timeout, DNS, connection, stb.)
     */
    public static function fromThrowable(Throwable $e): array
    {
        $httpStatus = 502;

        // Tipikus hálózati / connection hibák (DNS, connect fail, timeout jelleg)
        if ($e instanceof ConnectionException) {
            return self::shape(
                'UPSTREAM_CONNECTION_FAILED',
                'Nem sikerült kapcsolódni a külső szolgáltatóhoz.',
                $httpStatus,
                true,
                ['exception' => class_basename($e)]
            );
        }

        // Ha valaki Http::throw()-t használna, ez jöhet
        if ($e instanceof RequestException) {
            $response = $e->response;
            return self::fromResponse($response);
        }

        // “best effort” timeout felismerés üzenetből (nem tökéletes, de hasznos)
        $msg = strtolower($e->getMessage());
        $looksLikeTimeout =
            str_contains($msg, 'timed out') ||
            str_contains($msg, 'timeout') ||
            str_contains($msg, 'operation timed out');

        if ($looksLikeTimeout) {
            return self::shape(
                'UPSTREAM_TIMEOUT',
                'A külső szolgáltató nem válaszol időben.',
                $httpStatus,
                true,
                ['exception' => class_basename($e)]
            );
        }

        return self::shape(
            'UPSTREAM_UNEXPECTED_EXCEPTION',
            'Váratlan hiba történt a külső szolgáltató hívásakor.',
            $httpStatus,
            false,
            ['exception' => class_basename($e)]
        );
    }

    private static function shape(
        string $code,
        string $message,
        int $httpStatus,
        bool $retryable,
        array $meta = []
    ): array {
        return [
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'http_status' => $httpStatus,
                'retryable' => $retryable,
                'meta' => $meta,
            ],
        ];
    }

    private static function safeBody(Response $response): array|string|null
    {
        // Ne dobjon exception-t ha nem JSON
        $json = $response->json();
        if ($json !== null) return $json;

        $body = $response->body();
        if ($body === '') return null;

        // Ne öntsünk ki megabájtot logikába
        return mb_substr($body, 0, 1000);
    }
}