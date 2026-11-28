<?php
declare(strict_types=1);

namespace Homelab\Routes;

use Homelab\Compliance;
use Homelab\Entitlement;
use Homelab\Plans;
use Homelab\Request;
use Homelab\Response;
use Homelab\Route;
use Homelab\Signer;
use Homelab\UsageQuota;
use Homelab\UserRepository;
use Homelab\WithdrawalPolicy;

/** Primeiro acesso cria o registro (e inicia o trial). Devolve o plano e a cota do dia, assinados. */
final class MeRoute implements Route
{
    public function __construct(
        private UserRepository $users,
        private UsageQuota $quota,
        private Entitlement $entitlement,
        private Plans $plans,
        private Signer $signer,
        private Compliance $compliance,
        private WithdrawalPolicy $withdrawal,
    ) {
    }

    public function method(): string { return 'GET'; }
    public function path(): string { return '/me'; }
    public function rateLimitPerMinute(): int { return 60; }
    public function requiresAuth(): bool { return true; }

    public function handle(Request $request, ?array $identity, int $now): Response
    {
        $user = $this->users->ensure($identity, $now);
        $key = $identity['uid'];
        $ent = $this->entitlement->compute($user, $now);
        $used = $this->quota->usageToday($key, $now);
        $legal = $this->compliance->status($user);

        $quotas = [];
        foreach (Plans::DAILY_FEATURES as $feature) {
            $quotas[$feature] = ['limit' => $this->plans->dailyLimit($ent['plan'], $feature), 'used' => $used[$feature] ?? 0];
        }

        $body = [
            'plan' => $ent['plan'],
            'trialEndsAt' => $ent['trialEndsAt'],
            'proValidUntil' => $ent['proValidUntil'],
            'cancelsAtPeriodEnd' => $ent['cancelsAtPeriodEnd'],
            'permissions' => $ent['permissions'],
            'quotas' => $quotas,
            'terms' => ['currentVersion' => $legal['termsVersion'], 'accepted' => $legal['termsAccepted'], 'acceptedAt' => $legal['termsAcceptedAt']],
            'age' => ['required' => $legal['ageRequired'], 'verified' => $legal['ageVerified']],
            'withdrawalUntil' => $this->withdrawal->eligibleUntil($user, $now),
            'serverTime' => $now,
        ];
        $body['signed'] = $this->signer->sign([
            'email' => $identity['email'],
            'plan' => $ent['plan'],
            'permissions' => $ent['permissions'],
            'trialEndsAt' => $ent['trialEndsAt'],
            'proValidUntil' => $ent['proValidUntil'],
            'termsOk' => $legal['termsAccepted'],
            'ageOk' => !$legal['ageRequired'] || $legal['ageVerified'],
            'iat' => $now,
            'exp' => $ent['expiresAt'],
        ]);
        return new Response(200, $body);
    }
}
