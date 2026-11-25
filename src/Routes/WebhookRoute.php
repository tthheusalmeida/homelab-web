<?php
declare(strict_types=1);

namespace Homelab\Routes;

use Homelab\AuthException;
use Homelab\Logger;
use Homelab\Request;
use Homelab\Response;
use Homelab\Route;
use Homelab\Stripe;
use Homelab\UserRepository;

/** O Stripe avisa aqui que a assinatura mudou. Só vale com a assinatura do Stripe conferida. */
final class WebhookRoute implements Route
{
    public function __construct(private UserRepository $users, private string $webhookSecret, private Logger $log)
    {
    }

    public function method(): string { return 'POST'; }
    public function path(): string { return '/webhook'; }
    public function rateLimitPerMinute(): int { return 300; }
    public function requiresAuth(): bool { return false; }

    public function handle(Request $request, ?array $identity, int $now): Response
    {
        try {
            $event = Stripe::verifyWebhook($request->body, $request->headers['stripe-signature'] ?? '', $this->webhookSecret, $now);
        } catch (AuthException) {
            return new Response(400, ['error' => 'invalid_signature']);
        }

        $object = $event['data']['object'] ?? [];
        match ($event['type']) {
            'checkout.session.completed' => $this->onCheckout($object),
            'customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted' => $this->onSubscription($object, (int) ($event['created'] ?? $now)),
            default => null, // outros eventos são ignorados de propósito
        };
        return new Response(200, ['received' => true]);
    }

    /** @param array<string,mixed> $session */
    private function onCheckout(array $session): void
    {
        $key = $session['client_reference_id'] ?? null;
        $customer = $session['customer'] ?? null;
        if (is_string($key) && is_string($customer) && $this->isUserKey($key) && $this->users->find($key) !== null) {
            $this->users->linkCustomer($key, $customer);
        }
    }

    /** @param array<string,mixed> $sub */
    private function onSubscription(array $sub, int $created): void
    {
        $customer = is_string($sub['customer'] ?? null) ? $sub['customer'] : null;
        $key = $sub['metadata']['userKey'] ?? ($customer !== null ? $this->users->keyForCustomer($customer) : null);
        if (!is_string($key) || !$this->isUserKey($key) || $this->users->find($key) === null) {
            $this->log->error('webhook: subscription without a known user');
            return;
        }
        $item = $sub['items']['data'][0] ?? [];
        $priceId = $item['price']['id'] ?? null;
        $periodEnd = $item['current_period_end'] ?? $sub['current_period_end'] ?? null;
        if (!is_string($priceId) || !is_int($periodEnd) || !is_string($sub['id'] ?? null)) {
            $this->log->error('webhook: unexpected subscription shape');
            return;
        }
        if ($customer !== null) {
            $this->users->linkCustomer($key, $customer);
        }
        $this->users->applySubscription($key, [
            'id' => $sub['id'],
            'status' => (string) ($sub['status'] ?? 'canceled'),
            'priceId' => $priceId,
            'currentPeriodEnd' => $periodEnd,
            'cancelAtPeriodEnd' => ($sub['cancel_at_period_end'] ?? false) === true,
        ], $created);
    }

    private function isUserKey(string $key): bool
    {
        return preg_match('/^u_[a-f0-9]{64}$/', $key) === 1;
    }
}
