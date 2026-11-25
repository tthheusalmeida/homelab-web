<?php
declare(strict_types=1);

namespace Homelab;

/** Uma rota da API. Rota nova = uma classe + uma linha em App::routes(). */
interface Route
{
    public function method(): string;

    public function path(): string;

    /** Requisições por minuto por IP. */
    public function rateLimitPerMinute(): int;

    /** Rotas autenticadas recebem a identidade já verificada; o webhook (assinado pelo Stripe) recebe null. */
    public function requiresAuth(): bool;

    /** @param array{uid:string,email:string}|null $identity */
    public function handle(Request $request, ?array $identity, int $now): Response;
}
