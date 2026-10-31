<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\JsonResponse;

/** Problem details (RFC 9457): every error answer has the same three keys. */
final class Problem
{
    public static function response(int $status, string $title, string $detail): JsonResponse
    {
        return response()->json(
            ['title' => $title, 'status' => $status, 'detail' => $detail],
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
