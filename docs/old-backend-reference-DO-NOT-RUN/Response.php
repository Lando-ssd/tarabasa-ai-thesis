<?php

class Response
{
    public static function json(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(int $status, string $message, array $extra = []): void
    {
        self::json($status, array_merge(['error' => $message], $extra));
    }
}
