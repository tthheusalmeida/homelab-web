<?php
declare(strict_types=1);

namespace Homelab\Routes;

use Homelab\Request;
use Homelab\Response;
use Homelab\Route;
use Homelab\Stripe;
use Homelab\UserRepository;

/** Portal do Stripe para trocar cartão, ver faturas e cancelar. O cliente é achado pelo servidor. */
final class PortalRoute implements Route
{
    public function __construct(private UserRepository $users, private Stripe $stripe, private string $siteUrl)
    {
    }

    public function method(): string { return 'POST'; }
    public function path(): string { return '/portal'; }
    public function rateLimitPerMinute(): int { return 10; }
    public function requiresAuth(): bool { return true; }

    public function handle(Request $request, ?array $identity, int $now): Response
    {
        $user = $this->users->find($identity['uid']);
        $customer = $user['stripeCustomerId'] ?? null;
        if (!is_string($customer)) {
            return new Response(404, ['error' => 'no_subscription']);
        }
        $session = $this->stripe->post('/billing_portal/sessions', ['customer' => $customer, 'return_url' => $this->siteUrl . '/assinaturas/']);
        $url = $session['url'] ?? null;
        if (!is_string($url) || !str_starts_with($url, 'https://billing.stripe.com/')) {
            throw new \RuntimeException('unexpected portal url');
        }
        return new Response(200, ['url' => $url]);
    }
}
