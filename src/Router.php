<?php
declare(strict_types=1);

namespace Homelab;

/** Despacha a requisição: acha a rota, passa pelos guards, autentica e traduz erros sem vazar detalhes. */
final class Router
{
    /** @param array<int,Route> $routes @param array<int,Guard> $guards */
    public function __construct(
        private array $routes,
        private array $guards,
        private FirebaseAuth $auth,
        private Logger $log,
        private AccessLog $access,
    ) {
    }

    public function dispatch(Request $request, int $now): Response
    {
        $uid = null;
        $response = $this->handle($request, $now, $uid);
        if ($response->status !== 429) { // bloqueio por excesso de requisições não vira registro (evita encher o banco num ataque)
            try {
                $this->access->record($request, $uid, $response->status, $now);
            } catch (\Throwable $e) {
                $this->log->error('access log: ' . get_class($e));
            }
        }
        return $response;
    }

    private function handle(Request $request, int $now, ?string &$uid): Response
    {
        $route = $this->find($request);
        if ($route === null) {
            return new Response($this->pathExists($request->path) ? 405 : 404, ['error' => 'not_found']);
        }
        foreach ($this->guards as $guard) {
            $blocked = $guard->check($request, $route, $now);
            if ($blocked !== null) {
                return $blocked;
            }
        }

        try {
            $identity = null;
            if ($route->requiresAuth()) {
                $token = $request->bearer();
                if ($token === null) {
                    return new Response(401, ['error' => 'unauthorized']);
                }
                $identity = $this->auth->verify($token, $now);
                $uid = $identity['uid'];
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
