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

    public function acceptTerms(string $key, string $version, int $now): void
    {
        $this->store->merge('users/' . $key, ['termsVersion' => $version, 'termsAcceptedAt' => $now]);
    }

    /** Chamado por quem verifica a idade (ver docs/conformidade.md). Grava só o método e o momento, nunca a data de nascimento. */
    public function markAgeVerified(string $key, string $method, int $now): void
    {
        $this->store->merge('users/' . $key, ['ageVerified' => ['method' => $method, 'at' => $now]]);
    }

    public function recordWithdrawal(string $key, string $subscriptionId, int $now): void
    {
        $this->store->merge('users/' . $key, ['withdrawal' => ['subscriptionId' => $subscriptionId, 'at' => $now]]);
    }

    /** @return array<string,mixed>|null */
    public function find(string $key): ?array
    {
        return $this->store->get('users/' . $key)['data'] ?? null;
    }
}
