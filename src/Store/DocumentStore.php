<?php
declare(strict_types=1);

namespace Homelab\Store;

/** Banco de documentos (Firestore em produção, memória nos testes). */
interface DocumentStore
{
    /** @return array{data:array<string,mixed>, version:string}|null */
    public function get(string $path): ?array;

    /** Cria só se não existir. Devolve false se já existe (sem sobrescrever). @param array<string,mixed> $data */
    public function create(string $path, array $data): bool;

    /**
     * Mescla campos de topo. Com $version, só grava se o documento não mudou desde a leitura.
     * @param array<string,mixed> $data
     */
    public function merge(string $path, array $data, ?string $version = null): bool;
}
