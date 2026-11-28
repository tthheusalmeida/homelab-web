<?php
declare(strict_types=1);

namespace Homelab\Routes;

use Homelab\Compliance;
use Homelab\Request;
use Homelab\Response;
use Homelab\Route;
use Homelab\UserRepository;

/** Aceite explícito dos Termos e da Política de Privacidade vigentes. Aceitar uma versão antiga não vale. */
final class TermsRoute implements Route
{
    public function __construct(private UserRepository $users)
    {
    }

    public function method(): string { return 'POST'; }
    public function path(): string { return '/terms/accept'; }
    public function rateLimitPerMinute(): int { return 20; }
    public function requiresAuth(): bool { return true; }

    public function handle(Request $request, ?array $identity, int $now): Response
    {
        if (($request->json()['version'] ?? null) !== Compliance::TERMS_VERSION) {
            return new Response(409, ['error' => 'stale_terms_version', 'currentVersion' => Compliance::TERMS_VERSION]);
        }
        $this->users->ensure($identity, $now);
        $this->users->acceptTerms($identity['uid'], Compliance::TERMS_VERSION, $now);
        return new Response(200, ['termsVersion' => Compliance::TERMS_VERSION, 'termsAcceptedAt' => $now]);
    }
}
