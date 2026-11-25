<?php
declare(strict_types=1);

namespace Homelab\Routes;

use Homelab\PriceCatalog;
use Homelab\Request;
use Homelab\Response;
use Homelab\Route;

/** Público: o preço do Pro vem do Stripe, então trocar a variável do preço atualiza site e checkout juntos. */
final class PlansRoute implements Route
{
    public function __construct(private PriceCatalog $catalog)
    {
    }

    public function method(): string { return 'GET'; }
    public function path(): string { return '/plans'; }
    public function rateLimitPerMinute(): int { return 60; }
    public function requiresAuth(): bool { return false; }

    public function handle(Request $request, ?array $identity, int $now): Response
    {
        return new Response(200, ['pro' => $this->catalog->pro($now), 'trialDays' => \Homelab\Plans::TRIAL_DAYS]);
    }
}
