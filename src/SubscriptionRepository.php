<?php
declare(strict_types=1);

namespace Homelab;

use Homelab\Store\DocumentStore;

/** Estado da assinatura no registro do usuário e o vínculo cliente do Stripe → usuário (stripeCustomers/{cus}). */
final class SubscriptionRepository
{
    private const RETRIES = 4;

    public function __construct(private DocumentStore $store)
    {
    }

    public function linkCustomer(string $key, string $customerId): void
    {
        $this->store->merge('users/' . $key, ['stripeCustomerId' => $customerId]);
        $this->store->merge('stripeCustomers/' . $customerId, ['userKey' => $key]);
    }

    public function keyForCustomer(string $customerId): ?string
    {
        $key = $this->store->get('stripeCustomers/' . $customerId)['data']['userKey'] ?? null;
        return is_string($key) ? $key : null;
    }

    /**
     * Grava o estado da assinatura. Evento mais antigo que o já gravado é ignorado (eventos chegam fora de ordem).
     * @param array{id:string,status:string,priceId:string,currentPeriodEnd:int,cancelAtPeriodEnd:bool} $sub
     */
    public function applySubscription(string $key, array $sub, int $eventCreated): bool
    {
        for ($i = 0; $i < self::RETRIES; $i++) {
            $doc = $this->store->get('users/' . $key);
            if ($doc === null) {
                return false;
            }
            $saved = (int) ($doc['data']['subscription']['eventCreated'] ?? 0);
            if ($eventCreated < $saved) {
                return true;
            }
            if ($this->store->merge('users/' . $key, ['subscription' => $sub + ['eventCreated' => $eventCreated]], $doc['version'])) {
                return true;
            }
        }
        throw new \RuntimeException('could not save subscription');
    }
}
