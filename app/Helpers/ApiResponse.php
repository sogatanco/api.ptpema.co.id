<?php

namespace App\Helpers;

use Illuminate\Http\Exceptions\HttpResponseException;

class ApiResponse
{
    public static function success($data = null, $message = 'Success', int $code = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public static function error($message = 'Terjadi kesalahan', $errorMessage = null, int $code = 400): \Illuminate\Http\JsonResponse
    {
        throw new HttpResponseException(response([
            "status" => false,
            "message" => $message,
            "error" => $errorMessage
        ], $code));
    }
}
