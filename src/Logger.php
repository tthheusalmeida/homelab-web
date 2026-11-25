<?php
declare(strict_types=1);

namespace Homelab;

/** Log em arquivo fora da pasta pública. Nunca recebe token, chave ou corpo de requisição. */
final class Logger
{
    public function __construct(private string $file)
    {
    }

    public function error(string $message): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        @file_put_contents($this->file, gmdate('c') . ' ' . $message . "\n", FILE_APPEND | LOCK_EX);
    }
}
