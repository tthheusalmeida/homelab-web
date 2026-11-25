<?php
declare(strict_types=1);

namespace Homelab;

/** Cliente mínimo do Stripe (chave restrita). */
final class Stripe
{
    private const API = 'https://api.stripe.com/v1';

    public function __construct(private string $secretKey, private HttpClient $http)
    {
    }

    /** @param array<string,mixed> $params @return array<string,mixed> */
    public function post(string $path, array $params): array
    {
        $res = $this->http->request('POST', self::API . $path, $this->headers(), http_build_query($params));
        return $this->decode($res);
    }

    /** @return array<string,mixed> */
    public function get(string $path): array
    {
        return $this->decode($this->http->request('GET', self::API . $path, $this->headers()));
    }

    /** @return array<int,string> */
    private function headers(): array
    {
        return [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/x-www-form-urlencoded',
        ];
    }

    /** @param array{status:int,body:string} $res @return array<string,mixed> */
    private function decode(array $res): array
    {
        $body = json_decode($res['body'], true);
        if ($res['status'] < 200 || $res['status'] >= 300 || !is_array($body)) {
            throw new \RuntimeException('stripe error ' . $res['status']);
        }
        return $body;
    }
}
