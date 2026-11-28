<?php
declare(strict_types=1);

namespace Homelab;

use Homelab\Store\DocumentStore;
use Homelab\Store\Instant;

/**
 * Grava o acesso no Firebase (coleção accessLogs). O campo expireAt é um timestamp: a política de TTL do
 * Firestore apaga o documento depois do prazo (ver docs/conformidade.md). Nunca grava token, corpo ou cabeçalhos.
 */
final class StoreAccessLog implements AccessLog
{
    /** 6 meses completos mais uma folga, porque o TTL do Firestore apaga até 72 h depois do prazo. */
    public const RETENTION_DAYS = 186;

    /** @param array<int,string> $ignoredPaths rotas só de servidor (webhook do Stripe) */
    public function __construct(private DocumentStore $store, private array $ignoredPaths = [])
    {
    }

    public function record(Request $request, ?string $uid, int $status, int $now): void
    {
        if (in_array($request->path, $this->ignoredPaths, true)) {
            return;
        }
        $entry = [
            'at' => new Instant($now),
            'ip' => $request->ip,
            'method' => $request->method,
            'path' => $request->path,
            'status' => $status,
            'expireAt' => new Instant($now + self::RETENTION_DAYS * 86400),
        ];
        if ($uid !== null) {
            $entry['uid'] = $uid;
        }
        $this->store->create('accessLogs/' . bin2hex(random_bytes(12)), $entry);
    }
}
