<?php

namespace NishangSystems\Passkeys\Http\Controllers;

use Illuminate\Http\JsonResponse;

trait RespondsWithJson
{
    protected function success(
        mixed $data = null,
        string $message = 'Operation successful',
        int $statusCode = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    protected function failure(
        string $message = 'Operation failed',
        int $statusCode = 400,
        array $errors = []
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }
}
