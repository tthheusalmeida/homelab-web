<?php
declare(strict_types=1);

namespace Homelab;

/** Configuração lida de um arquivo FORA da pasta pública (gerado no deploy; nunca versionado). */
final class Config
{
    /** @param array<string,mixed> $values */
    public function __construct(private array $values)
    {
    }

    public static function load(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException('config file not found');
        }
        $values = require $path;
        if (!is_array($values)) {
            throw new \RuntimeException('config must return an array');
        }
        return new self($values);
    }

    public function get(string $dotted, mixed $default = null): mixed
    {
        $node = $this->values;
        foreach (explode('.', $dotted) as $part) {
            if (!is_array($node) || !array_key_exists($part, $node)) {
                return $default;
            }
            $node = $node[$part];
        }
        return $node;
    }

    public function string(string $dotted): string
    {
        $value = $this->get($dotted);
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException('missing config: ' . $dotted);
        }
        return $value;
    }
}
