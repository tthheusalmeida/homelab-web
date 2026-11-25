<?php
declare(strict_types=1);

namespace Homelab;

/** Só aceita eventos do Stripe com assinatura válida e recente. */
final class WebhookVerifier
{
    private const TOLERANCE_SECONDS = 300;

    public function __construct(private string $secret)
    {
    }

    /**
     * Confere o cabeçalho Stripe-Signature (HMAC-SHA256 do "tempo.corpo") com tolerância de tempo.
     * @return array<string,mixed> evento
     */
    public function verify(string $payload, string $header, int $now): array
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
        if ($timestamp === null || $signatures === [] || abs($now - $timestamp) > self::TOLERANCE_SECONDS) {
            throw new AuthException('bad signature header');
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $this->secret);
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
