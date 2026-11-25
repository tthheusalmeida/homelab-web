<?php
declare(strict_types=1);

namespace Homelab;

final class Response
{
    /** @param array<string,mixed> $body */
    public function __construct(public int $status, public array $body = [])
    {
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($this->body, JSON_UNESCAPED_SLASHES);
    }
}
