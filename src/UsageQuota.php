<?php
declare(strict_types=1);

namespace Homelab;

use Homelab\Store\DocumentStore;

/** Cota diária por recurso: usage/{chave}_{dia}. O dia vira à meia-noite de São Paulo. */
final class UsageQuota
{
    private const RETRIES = 4;

    public function __construct(private DocumentStore $store)
    {
    }

    public static function dayKey(int $now): string
    {
        return (new \DateTimeImmutable('@' . $now))->setTimezone(new \DateTimeZone('America/Sao_Paulo'))->format('Ymd');
    }

    /**
     * Consome 1 da cota do dia. Conta no servidor (precisa de internet), com gravação condicional contra corrida.
     * @return array{allowed:bool,used:int,limit:?int}
     */
    public function consume(string $key, string $feature, ?int $limit, int $now): array
    {
        $path = 'usage/' . $key . '_' . self::dayKey($now);
        for ($i = 0; $i < self::RETRIES; $i++) {
            $doc = $this->store->get($path);
            $used = (int) ($doc['data']['counts'][$feature] ?? 0);
            if ($limit !== null && $used >= $limit) {
                return ['allowed' => false, 'used' => $used, 'limit' => $limit];
            }
            $counts = ($doc['data']['counts'] ?? []);
            $counts[$feature] = $used + 1;
            $ok = $doc === null
                ? $this->store->create($path, ['counts' => $counts, 'day' => self::dayKey($now)])
                : $this->store->merge($path, ['counts' => $counts], $doc['version']);
            if ($ok) {
                return ['allowed' => true, 'used' => $used + 1, 'limit' => $limit];
            }
        }
        throw new \RuntimeException('could not consume quota');
    }

    /** @return array<string,int> */
    public function usageToday(string $key, int $now): array
    {
        $doc = $this->store->get('usage/' . $key . '_' . self::dayKey($now));
        $counts = $doc['data']['counts'] ?? [];
        return is_array($counts) ? array_map('intval', $counts) : [];
    }
}
