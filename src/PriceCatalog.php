<?php
declare(strict_types=1);

namespace Homelab;

/** Valor do plano Pro lido do próprio Stripe (a variável do preço é a única fonte da verdade). */
final class PriceCatalog
{
    public function __construct(private Plans $plans, private Stripe $stripe, private FileCache $cache)
    {
    }

    /** @return array{amount:int,currency:string,interval:string}|null */
    public function pro(int $now): ?array
    {
        $key = 'price-' . $this->plans->activePriceId();
        $cached = $this->cache->get($key, $now);
        if (is_array($cached)) {
            return $cached;
        }
        try {
            $price = $this->stripe->get('/prices/' . rawurlencode($this->plans->activePriceId()));
        } catch (\Throwable) {
            return null;
        }
        $info = [
            'amount' => (int) ($price['unit_amount'] ?? 0),
            'currency' => (string) ($price['currency'] ?? 'brl'),
            'interval' => (string) ($price['recurring']['interval'] ?? 'month'),
        ];
        $this->cache->put($key, $info, 3600, $now);
        return $info;
    }
}
