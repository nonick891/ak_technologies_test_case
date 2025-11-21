<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class Controller
{
    protected function errorResponse(
        string $code,
        string $message,
        array $details = [],
        int $status = Response::HTTP_BAD_REQUEST
    ): JsonResponse {
        return response()->json([
            'error'   => $code,
            'message' => $message,
            'details' => $details,
        ], $status);
    }

}
