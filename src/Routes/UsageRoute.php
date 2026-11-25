<?php
declare(strict_types=1);

namespace Homelab\Routes;

use Homelab\Entitlement;
use Homelab\Plans;
use Homelab\Request;
use Homelab\Response;
use Homelab\Route;
use Homelab\UsageQuota;
use Homelab\UserRepository;

/** Consome 1 da cota diária de um recurso. Contada no servidor: gerar com limite exige internet. */
final class UsageRoute implements Route
{
    public function __construct(private UserRepository $users, private UsageQuota $quota, private Entitlement $entitlement, private Plans $plans)
    {
    }

    public function method(): string { return 'POST'; }
    public function path(): string { return '/usage'; }
    public function rateLimitPerMinute(): int { return 60; }
    public function requiresAuth(): bool { return true; }

    public function handle(Request $request, ?array $identity, int $now): Response
    {
        $feature = $request->json()['feature'] ?? null;
        if (!is_string($feature) || !in_array($feature, Plans::DAILY_FEATURES, true)) {
            return new Response(400, ['error' => 'invalid_feature']);
        }
        $user = $this->users->ensure($identity, $now);
        $ent = $this->entitlement->compute($user, $now);
        if ($ent['plan'] === 'expired') {
            return new Response(403, ['error' => 'plan_expired']);
        }
        $result = $this->quota->consume($identity['uid'], $feature, $this->plans->dailyLimit($ent['plan'], $feature), $now);
        return new Response($result['allowed'] ? 200 : 429, $result['allowed'] ? $result : ['error' => 'quota_exceeded'] + $result);
    }
}
