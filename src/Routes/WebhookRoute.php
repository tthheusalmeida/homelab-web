<?php
declare(strict_types=1);

namespace Homelab\Routes;

use Homelab\AuthException;
use Homelab\Logger;
use Homelab\Request;
use Homelab\Response;
use Homelab\Route;
use Homelab\SubscriptionRepository;
use Homelab\UserRepository;
use Homelab\WebhookVerifier;

/** O Stripe avisa aqui que a assinatura mudou. Só vale com a assinatura do Stripe conferida. */
final class WebhookRoute implements Route
{
    public function __construct(private UserRepository $users, private SubscriptionRepository $subscriptions, private WebhookVerifier $verifier, private Logger $log)
    {
    }

    public function method(): string { return 'POST'; }
    public function path(): string { return '/webhook'; }
    public function rateLimitPerMinute(): int { return 300; }
    public function requiresAuth(): bool { return false; }

    public function handle(Request $request, ?array $identity, int $now): Response
    {
        try {
            $event = $this->verifier->verify($request->body, $request->headers['stripe-signature'] ?? '', $now);
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
            $this->subscriptions->linkCustomer($key, $customer);
        }
    }

    /** @param array<string,mixed> $sub */
    private function onSubscription(array $sub, int $created): void
    {
        $customer = is_string($sub['customer'] ?? null) ? $sub['customer'] : null;
        $key = $sub['metadata']['userKey'] ?? ($customer !== null ? $this->subscriptions->keyForCustomer($customer) : null);
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
            $this->subscriptions->linkCustomer($key, $customer);
        }
        $record = [
            'id' => $sub['id'],
            'status' => (string) ($sub['status'] ?? 'canceled'),
            'priceId' => $priceId,
            'currentPeriodEnd' => $periodEnd,
            'cancelAtPeriodEnd' => ($sub['cancel_at_period_end'] ?? false) === true,
        ];
        if (is_int($sub['start_date'] ?? null)) {
            $record['startedAt'] = $sub['start_date']; // início da contratação: conta os 7 dias de arrependimento
        }
        $this->subscriptions->applySubscription($key, $record, $created);
    }

    private function isUserKey(string $key): bool
    {
        return UserRepository::isKey($key);
    }
}
