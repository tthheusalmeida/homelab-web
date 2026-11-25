<?php
declare(strict_types=1);

namespace Homelab;

/** Cache simples em arquivo (fora da pasta pública) com validade em segundos. */
final class FileCache
{
    public function __construct(private string $dir)
    {
    }

    public function get(string $key, int $now): mixed
    {
        $file = $this->file($key);
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || ($data['expires'] ?? 0) <= $now) {
            return null;
        }
        return $data['value'] ?? null;
    }

    public function put(string $key, mixed $value, int $ttl, int $now): void
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0700, true);
        }
        $file = $this->file($key);
        $tmp = $file . '.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, json_encode(['expires' => $now + $ttl, 'value' => $value]), LOCK_EX);
        chmod($tmp, 0600);
        rename($tmp, $file);
    }

    private function file(string $key): string
    {
        return $this->dir . '/' . hash('sha256', $key) . '.json';
    }
}
