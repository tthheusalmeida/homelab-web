<?php
declare(strict_types=1);

namespace Homelab;

use Homelab\Store\DocumentStore;

/** Registro do usuário: users/{uid}. O uid é o id da conta no Firebase, vindo do token já verificado. */
final class UserRepository
{
    public function __construct(private DocumentStore $store)
    {
    }

    /** O uid vira parte do caminho no Firestore: só letras e números, nada de "/" ou "." que mudem o caminho. */
    public static function isKey(string $key): bool
    {
        return preg_match('/^[A-Za-z0-9]{1,128}$/', $key) === 1;
    }

    /** @param array{uid:string,email:string} $identity @return array<string,mixed> */
    public function ensure(array $identity, int $now): array
    {
        $key = $identity['uid'];
        $found = $this->store->get('users/' . $key);
        if ($found !== null) {
            return $found['data'];
        }
        $data = ['email' => $identity['email'], 'trialStartedAt' => $now, 'createdAt' => $now];
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
