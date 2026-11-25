<?php
declare(strict_types=1);

namespace Homelab;

/** Limite de requisições por IP e rota. */
final class RateLimitGuard implements Guard
{
    private const WINDOW_SECONDS = 60;

    public function __construct(private RateLimiter $limiter)
    {
    }

    public function check(Request $request, Route $route, int $now): ?Response
    {
        $allowed = $this->limiter->allow($request->ip . '|' . $route->path(), $route->rateLimitPerMinute(), self::WINDOW_SECONDS, $now);
        return $allowed ? null : new Response(429, ['error' => 'rate_limited']);
    }
}
