<?php
declare(strict_types=1);

namespace Homelab\Store;

final class MemoryStore implements DocumentStore
{
    /** @var array<string,array{data:array<string,mixed>,version:int}> */
    private array $docs = [];

    /** Documentos de uma coleção (só para conferir nos testes). @return array<string,array<string,mixed>> */
    public function all(string $collection): array
    {
        $out = [];
        foreach ($this->docs as $path => $doc) {
            if (str_starts_with($path, $collection . '/')) {
                $out[$path] = $doc['data'];
            }
        }
        return $out;
    }

    public function get(string $path): ?array
    {
        if (!isset($this->docs[$path])) {
            return null;
        }
        return ['data' => $this->docs[$path]['data'], 'version' => (string) $this->docs[$path]['version']];
    }

    public function create(string $path, array $data): bool
    {
        if (isset($this->docs[$path])) {
            return false;
        }
        $this->docs[$path] = ['data' => $data, 'version' => 1];
        return true;
    }

    public function merge(string $path, array $data, ?string $version = null): bool
    {
        $current = $this->docs[$path] ?? null;
        if ($version !== null && ($current === null || (string) $current['version'] !== $version)) {
            return false;
        }
        $this->docs[$path] = [
            'data' => array_merge($current['data'] ?? [], $data),
            'version' => ($current['version'] ?? 0) + 1,
        ];
        return true;
    }
}
