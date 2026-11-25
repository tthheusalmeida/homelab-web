<?php
declare(strict_types=1);

namespace Homelab;

/** Verificação feita antes da rota. Devolve a resposta que barra a requisição, ou null para deixá-la passar. */
interface Guard
{
    public function check(Request $request, Route $route, int $now): ?Response;
}
