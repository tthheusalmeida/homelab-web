<?php
declare(strict_types=1);

namespace Homelab;

final class Request
{
    /** @param array<string,string> $headers minúsculas */
    public function __construct(
        public string $method,
        public string $path,
        public array $headers,
        public string $body,
        public string $ip,
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = '/' . trim((string) preg_replace('#^/api#', '', $path), '/');
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = (string) $v;
            }
        }
        // corpo limitado: nenhuma rota precisa de mais que isso
        $body = (string) file_get_contents('php://input', false, null, 0, 65536);
        return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', $path, $headers, $body, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function bearer(): ?string
    {
        $h = $this->headers['authorization'] ?? '';
        return preg_match('/^Bearer\s+(\S+)$/i', $h, $m) === 1 ? $m[1] : null;
    }

    /** @return array<string,mixed> */
    public function json(): array
    {
        $data = json_decode($this->body, true);
        return is_array($data) ? $data : [];
    }
}
