<?php

declare(strict_types=1);

namespace App\Helpers;

class Response
{
    public static function success(string $message, array|object|null $data = null, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data ?? new \stdClass(),
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function error(string $message, array $errors = [], int $status = 400): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], JSON_UNESCAPED_UNICODE);
    }
}
