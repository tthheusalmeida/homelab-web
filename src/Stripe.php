<?php
declare(strict_types=1);

namespace Homelab;

/** Cliente mínimo do Stripe (chave restrita) e conferência da assinatura do webhook. */
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

    /**
     * Confere o cabeçalho Stripe-Signature (HMAC-SHA256 do "tempo.corpo") com tolerância de tempo.
     * @return array<string,mixed> evento
     */
    public static function verifyWebhook(string $payload, string $header, string $secret, int $now, int $tolerance = 300): array
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $item) {
            $pair = explode('=', trim($item), 2);
            if (count($pair) !== 2) {
                continue;
            }
            if ($pair[0] === 't') {
                $timestamp = (int) $pair[1];
            } elseif ($pair[0] === 'v1') {
                $signatures[] = $pair[1];
            }
        }
        if ($timestamp === null || $signatures === [] || abs($now - $timestamp) > $tolerance) {
            throw new AuthException('bad signature header');
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                $event = json_decode($payload, true);
                if (!is_array($event) || !is_string($event['type'] ?? null)) {
                    throw new AuthException('bad payload');
                }
                return $event;
            }
        }
        throw new AuthException('signature mismatch');
    }
}
