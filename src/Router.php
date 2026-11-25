<?php
declare(strict_types=1);

namespace Homelab;

/** Despacha a requisição: método, origem, limite de uso, autenticação e erros sem vazar detalhes. */
final class Router
{
    /** @param array<int,Route> $routes @param array<int,string> $allowedOrigins */
    public function __construct(
        private array $routes,
        private FirebaseAuth $auth,
        private RateLimiter $limiter,
        private Logger $log,
        private array $allowedOrigins,
    ) {
    }

    public function dispatch(Request $request, int $now): Response
    {
        $route = $this->find($request);
        if ($route === null) {
            return new Response($this->pathExists($request->path) ? 405 : 404, ['error' => 'not_found']);
        }
        if (!$this->limiter->allow($request->ip . '|' . $route->path(), $route->rateLimitPerMinute(), 60, $now)) {
            return new Response(429, ['error' => 'rate_limited']);
        }
        // Navegadores mandam Origin: só o nosso site pode chamar rotas autenticadas (o app desktop não manda).
        $origin = $request->headers['origin'] ?? null;
        if ($route->requiresAuth() && $origin !== null && !in_array($origin, $this->allowedOrigins, true)) {
            return new Response(403, ['error' => 'forbidden_origin']);
        }

        try {
            $identity = null;
            if ($route->requiresAuth()) {
                $token = $request->bearer();
                if ($token === null) {
                    return new Response(401, ['error' => 'unauthorized']);
                }
                $identity = $this->auth->verify($token, $now);
            }
            return $route->handle($request, $identity, $now);
        } catch (AuthException) {
            return new Response(401, ['error' => 'unauthorized']);
        } catch (\Throwable $e) {
            $this->log->error(get_class($e) . ': ' . $e->getMessage()); // sem token, chave ou corpo
            return new Response(500, ['error' => 'server_error']);
        }
    }

    private function find(Request $request): ?Route
    {
        foreach ($this->routes as $route) {
            if ($route->method() === $request->method && $route->path() === $request->path) {
                return $route;
            }
        }
        return null;
    }

    private function pathExists(string $path): bool
    {
        foreach ($this->routes as $route) {
            if ($route->path() === $path) {
                return true;
            }
        }
        return false;
    }
}
