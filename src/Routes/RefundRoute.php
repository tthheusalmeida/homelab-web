<?php
declare(strict_types=1);

namespace Homelab\Routes;

use Homelab\Request;
use Homelab\Response;
use Homelab\Route;
use Homelab\Stripe;
use Homelab\SubscriptionRepository;
use Homelab\UserRepository;
use Homelab\WithdrawalPolicy;

/** Desistência em até 7 dias (CDC, art. 49): reembolso integral e cancelamento imediato, sem pedir motivo. */
final class RefundRoute implements Route
{
    public function __construct(
        private UserRepository $users,
        private SubscriptionRepository $subscriptions,
        private WithdrawalPolicy $policy,
        private Stripe $stripe,
    ) {
    }

    public function method(): string { return 'POST'; }
    public function path(): string { return '/refund'; }
    public function rateLimitPerMinute(): int { return 5; }
    public function requiresAuth(): bool { return true; }

    public function handle(Request $request, ?array $identity, int $now): Response
    {
        $key = $identity['uid'];
        $user = $this->users->find($key);
        $sub = is_array($user['subscription'] ?? null) ? $user['subscription'] : null;
        $customer = $user['stripeCustomerId'] ?? null;
        if ($user === null || $sub === null || !is_string($customer) || !is_string($sub['id'] ?? null)) {
            return new Response(404, ['error' => 'no_subscription']);
        }
        if ($this->policy->eligibleUntil($user, $now) === null) {
            $done = ($user['withdrawal']['subscriptionId'] ?? null) === $sub['id'];
            return new Response($done ? 409 : 403, ['error' => $done ? 'already_withdrawn' : 'withdrawal_period_over']);
        }

        $charge = $this->latestRefundableCharge($customer);
        if ($charge === null) {
            return new Response(404, ['error' => 'no_charge']);
        }
        // Reembolso primeiro (com chave de idempotência: repetir não reembolsa duas vezes) e só depois cancela.
        $this->stripe->post('/refunds', [
            'charge' => $charge,
            'reason' => 'requested_by_customer',
            'metadata' => ['userKey' => $key, 'basis' => 'cdc-art-49'],
        ], 'withdrawal-' . $sub['id']);
        $this->stripe->delete('/subscriptions/' . rawurlencode($sub['id']));

        unset($sub['eventCreated']);
        $this->users->recordWithdrawal($key, $sub['id'], $now);
        $this->subscriptions->applySubscription($key, ['status' => 'canceled', 'cancelAtPeriodEnd' => false] + $sub, $now);
        return new Response(200, ['refunded' => true]);
    }

    private function latestRefundableCharge(string $customer): ?string
    {
        $list = $this->stripe->get('/charges?customer=' . rawurlencode($customer) . '&limit=5');
        foreach ($list['data'] ?? [] as $charge) {
            if (($charge['paid'] ?? false) === true && ($charge['refunded'] ?? true) === false && is_string($charge['id'] ?? null)) {
                return $charge['id'];
            }
        }
        return null;
    }
}
