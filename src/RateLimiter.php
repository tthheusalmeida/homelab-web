<?php
declare(strict_types=1);

namespace Homelab;

/** Janela fixa por chave (IP + rota), em arquivo. Protege o servidor compartilhado de abuso. */
final class RateLimiter
{
    public function __construct(private string $dir)
    {
    }

    public function allow(string $key, int $limit, int $windowSeconds, int $now): bool
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0700, true);
        }
        $window = intdiv($now, $windowSeconds);
        $file = $this->dir . '/' . hash('sha256', $key) . '.rl';
        $handle = fopen($file, 'c+');
        if ($handle === false) {
            return true; // sem disco, não derruba o serviço
        }
        flock($handle, LOCK_EX);
        $raw = stream_get_contents($handle);
        [$savedWindow, $count] = $raw !== '' && $raw !== false ? array_map('intval', explode(':', $raw)) : [$window, 0];
        $count = $savedWindow === $window ? $count + 1 : 1;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $window . ':' . $count);
        flock($handle, LOCK_UN);
        fclose($handle);
        return $count <= $limit;
    }
}
