<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        return self::respond(true, $message, $data, $status, null, $meta);
    }

    public static function error(
        string $message,
        int $status = 400,
        mixed $errors = null,
        mixed $data = null,
        array $meta = [],
    ): JsonResponse {
        return self::respond(false, $message, $data, $status, $errors, $meta);
    }

    private static function respond(
        bool $success,
        string $message,
        mixed $data,
        int $status,
        mixed $errors,
        array $meta,
    ): JsonResponse {
        $payload = [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }
}
