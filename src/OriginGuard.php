<?php
declare(strict_types=1);

namespace Homelab;

/** Navegadores mandam Origin: só o nosso site pode chamar rotas autenticadas (o app desktop não manda). */
final class OriginGuard implements Guard
{
    /** @param array<int,string> $allowedOrigins */
    public function __construct(private array $allowedOrigins)
    {
    }

    public function check(Request $request, Route $route, int $now): ?Response
    {
        $origin = $request->headers['origin'] ?? null;
        if ($route->requiresAuth() && $origin !== null && !in_array($origin, $this->allowedOrigins, true)) {
            return new Response(403, ['error' => 'forbidden_origin']);
        }
        return null;
    }
}
