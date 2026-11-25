<?php
declare(strict_types=1);

namespace Homelab;

use Homelab\Store\DocumentStore;

/** Registro do usuário: users/{chave}. A chave vem do e-mail verificado. */
final class UserRepository
{
    public function __construct(private DocumentStore $store)
    {
    }

    /** Mesmo e-mail = mesmo registro, então apagar e recriar a conta não renova o trial. */
    public static function keyFor(string $email): string
    {
        return 'u_' . hash('sha256', strtolower(trim($email)));
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
}
