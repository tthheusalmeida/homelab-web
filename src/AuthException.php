<?php
declare(strict_types=1);

namespace Homelab;

/** Token ausente, inválido ou expirado. Vira 401, sem detalhes para quem chamou. */
final class AuthException extends \RuntimeException
{
}
