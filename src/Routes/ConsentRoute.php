<?php
declare(strict_types=1);

namespace Homelab\Routes;

use Homelab\Request;
use Homelab\Response;
use Homelab\Route;
use Homelab\Store\DocumentStore;
use Homelab\Store\Instant;

/** Prova da escolha feita no banner de cookies (LGPD, art. 8º, §2º). Anônima: o IP e a hora ficam no registro de acesso. */
final class ConsentRoute implements Route
{
    public function __construct(private DocumentStore $store)
    {
    }

    public function method(): string { return 'POST'; }
    public function path(): string { return '/consent'; }
    public function rateLimitPerMinute(): int { return 20; }
    public function requiresAuth(): bool { return false; }

    public function handle(Request $request, ?array $identity, int $now): Response
    {
        $body = $request->json();
        $version = $body['version'] ?? null;
        if (!is_int($version) || $version < 1 || $version > 1000 || !is_bool($body['analytics'] ?? null) || !is_bool($body['marketing'] ?? null)) {
            return new Response(400, ['error' => 'invalid_consent']);
        }
        $this->store->create('consents/' . bin2hex(random_bytes(12)), [
            'at' => new Instant($now),
            'version' => $version,
            'analytics' => $body['analytics'],
            'marketing' => $body['marketing'],
        ]);
        return new Response(200, ['saved' => true]);
    }
}
