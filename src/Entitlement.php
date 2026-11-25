<?php
declare(strict_types=1);

namespace Homelab;

/** Decide o plano de um usuário a partir do documento dele e da hora do servidor. */
final class Entitlement
{
    private const ACTIVE_STATUSES = ['active', 'trialing', 'past_due'];

    public function __construct(private Plans $plans)
    {
    }

    /**
     * @param array<string,mixed> $user
     * @return array{plan:string,trialEndsAt:int,proValidUntil:?int,cancelsAtPeriodEnd:bool,permissions:array<int,string>,expiresAt:int}
     */
    public function compute(array $user, int $now): array
    {
        $trialEndsAt = (int) ($user['trialStartedAt'] ?? 0) + Plans::TRIAL_DAYS * 86400;
        $sub = is_array($user['subscription'] ?? null) ? $user['subscription'] : [];
        $periodEnd = (int) ($sub['currentPeriodEnd'] ?? 0);
        $validUntil = $periodEnd + Plans::PRO_GRACE_DAYS * 86400;

        $isPro = in_array($sub['status'] ?? '', self::ACTIVE_STATUSES, true)
            && is_string($sub['priceId'] ?? null)
            && $this->plans->isProPrice($sub['priceId'])
            && $now < $validUntil;

        if ($isPro) {
            $plan = 'pro';
            $expires = min($validUntil, $now + Plans::PRO_OFFLINE_DAYS * 86400);
        } elseif ($now < $trialEndsAt) {
            $plan = 'trial';
            $expires = min($trialEndsAt, $now + Plans::TRIAL_ONLINE_SECONDS);
        } else {
            $plan = 'expired';
            $expires = $now + Plans::PRO_OFFLINE_DAYS * 86400;
        }

        return [
            'plan' => $plan,
            'trialEndsAt' => $trialEndsAt,
            'proValidUntil' => $isPro ? $validUntil : null,
            'cancelsAtPeriodEnd' => $isPro && ($sub['cancelAtPeriodEnd'] ?? false) === true,
            'permissions' => $this->plans->permissionsFor($plan),
            'expiresAt' => $expires,
        ];
    }
}
