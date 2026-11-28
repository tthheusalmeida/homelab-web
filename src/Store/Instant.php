<?php
declare(strict_types=1);

namespace Homelab\Store;

/** Momento no tempo gravado como timestamp do Firestore (necessário para a política de expiração TTL apagar o documento). */
final class Instant
{
    public function __construct(public readonly int $seconds)
    {
    }
}
