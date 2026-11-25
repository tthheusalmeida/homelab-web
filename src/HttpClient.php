<?php
declare(strict_types=1);

namespace Homelab;

interface HttpClient
{
    /**
     * @param array<int,string> $headers
     * @return array{status:int, body:string}
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 10): array;
}
