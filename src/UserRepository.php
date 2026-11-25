<?php
declare(strict_types=1);

namespace Homelab;

use Homelab\Store\DocumentStore;

/** Documentos: users/{chave}, usage/{chave}_{dia}, stripeCustomers/{cus}. A chave vem do e-mail verificado. */
final class UserRepository
{
    private const RETRIES = 4;

    public function __construct(private DocumentStore $store)
    {
    }

    /** Mesmo e-mail = mesmo registro, então apagar e recriar a conta não renova o trial. */
    public static function keyFor(string $email): string
    {
        return 'u_' . hash('sha256', strtolower(trim($email)));
    }

    public static function dayKey(int $now): string
    {
        return (new \DateTimeImmutable('@' . $now))->setTimezone(new \DateTimeZone('America/Sao_Paulo'))->format('Ymd');
    }

    /** @param array{uid:string,email:string} $identity @return array<string,mixed> */
    public function ensure(array $identity, int $now): array
    {
        $key = self::keyFor($identity['email']);
        $found = $this->store->get('users/' . $key);
        if ($found !== null) {
            return $found['data'];
        }
        $data = ['email' => $identity['email'], 'uid' => $identity['uid'], 'trialStartedAt' => $now, 'createdAt' => $now];
        if (!$this->store->create('users/' . $key, $data)) {
            $found = $this->store->get('users/' . $key); // outra requisição criou primeiro
            return $found['data'] ?? $data;
        }
        return $data;
    }

    /** @return array<string,mixed>|null */
    public function find(string $key): ?array
    {
        return $this->store->get('users/' . $key)['data'] ?? null;
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
