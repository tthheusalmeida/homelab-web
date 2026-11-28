<?php
declare(strict_types=1);

namespace Homelab;

/** Direito de arrependimento (CDC, art. 49): 7 dias a partir da contratação, sem justificar, com reembolso integral. */
final class WithdrawalPolicy
{
    public const DAYS = 7;
    private const ACTIVE = ['active', 'trialing'];

    /** Até quando o usuário pode desistir, ou null se não pode (sem assinatura, prazo vencido ou já desistiu). @param array<string,mixed> $user */
    public function eligibleUntil(array $user, int $now): ?int
    {
        $sub = is_array($user['subscription'] ?? null) ? $user['subscription'] : [];
        $started = $sub['startedAt'] ?? null;
        $done = is_array($user['withdrawal'] ?? null) ? ($user['withdrawal']['subscriptionId'] ?? null) : null;
        if (($done !== null && $done === ($sub['id'] ?? null)) || !is_int($started) || !in_array($sub['status'] ?? '', self::ACTIVE, true)) {
            return null;
        }
        $until = $started + self::DAYS * 86400;
        return $now <= $until ? $until : null;
    }
}
