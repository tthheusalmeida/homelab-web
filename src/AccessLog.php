<?php
declare(strict_types=1);

namespace Homelab;

/** Registro de acesso à aplicação (Marco Civil da Internet, art. 15): IP, data e hora, guardados em sigilo por 6 meses. */
interface AccessLog
{
    public function record(Request $request, ?string $uid, int $status, int $now): void;
}
