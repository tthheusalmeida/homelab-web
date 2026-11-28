<?php
declare(strict_types=1);

namespace Homelab\Store;

use Homelab\HttpClient;

/** Firestore pela API REST (a conta de serviço tem só o papel roles/datastore.user). */
final class FirestoreStore implements DocumentStore
{
    private string $base;

    public function __construct(
        string $projectId,
        private ServiceAccountToken $token,
        private HttpClient $http,
        private \Closure $clock,
        ?string $baseUrl = null,
    ) {
        $this->base = ($baseUrl ?? 'https://firestore.googleapis.com') . '/v1/projects/' . rawurlencode($projectId) . '/databases/(default)/documents';
    }

    public function get(string $path): ?array
    {
        $res = $this->call('GET', $this->base . '/' . $path);
        if ($res['status'] === 404) {
            return null;
        }
        $this->assertOk($res, [200]);
        $doc = json_decode($res['body'], true);
        return ['data' => self::decodeFields($doc['fields'] ?? []), 'version' => (string) ($doc['updateTime'] ?? '')];
    }

    public function create(string $path, array $data): bool
    {
        $pos = strrpos($path, '/');
        $collection = substr($path, 0, (int) $pos);
        $id = substr($path, (int) $pos + 1);
        $url = $this->base . '/' . $collection . '?documentId=' . rawurlencode($id);
        $res = $this->call('POST', $url, ['fields' => self::encodeFields($data)]);
        if ($res['status'] === 409) {
            return false;
        }
        $this->assertOk($res, [200]);
        return true;
    }

    public function merge(string $path, array $data, ?string $version = null): bool
    {
        $query = [];
        foreach (array_keys($data) as $field) {
            $query[] = 'updateMask.fieldPaths=' . rawurlencode('`' . $field . '`');
        }
        if ($version !== null) {
            $query[] = 'currentDocument.updateTime=' . rawurlencode($version);
        }
        $res = $this->call('PATCH', $this->base . '/' . $path . '?' . implode('&', $query), ['fields' => self::encodeFields($data)]);
        if ($version !== null && in_array($res['status'], [400, 404, 409], true)) {
            return false; // o documento mudou desde a leitura: quem chamou tenta de novo
        }
        $this->assertOk($res, [200]);
        return true;
    }

    /** @param array<string,mixed>|null $body @return array{status:int,body:string} */
    private function call(string $method, string $url, ?array $body = null): array
    {
        $now = ($this->clock)();
        return $this->http->request($method, $url, [
            'Authorization: Bearer ' . $this->token->get($now),
            'Content-Type: application/json',
        ], $body === null ? null : (string) json_encode($body));
    }

    /** @param array{status:int,body:string} $res @param array<int,int> $ok */
    private function assertOk(array $res, array $ok): void
    {
        if (!in_array($res['status'], $ok, true)) {
            throw new \RuntimeException('firestore error ' . $res['status']);
        }
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public static function encodeFields(array $data): array
    {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[$key] = self::encode($value);
        }
        return $fields;
    }

    /** @return array<string,mixed> */
    private static function encode(mixed $value): array
    {
        return match (true) {
            $value === null => ['nullValue' => null],
            is_bool($value) => ['booleanValue' => $value],
            is_int($value) => ['integerValue' => (string) $value],
            is_float($value) => ['doubleValue' => $value],
            $value instanceof Instant => ['timestampValue' => gmdate('Y-m-d\TH:i:s\Z', $value->seconds)],
            is_string($value) => ['stringValue' => $value],
            is_array($value) && array_is_list($value) => ['arrayValue' => ['values' => array_map(self::encode(...), $value)]],
            is_array($value) => ['mapValue' => ['fields' => self::encodeFields($value)]],
            default => throw new \InvalidArgumentException('unsupported value'),
        };
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public static function decodeFields(array $fields): array
    {
        $out = [];
        foreach ($fields as $key => $value) {
            $out[$key] = self::decode($value);
        }
        return $out;
    }

    private static function decode(array $v): mixed
    {
        return match (true) {
            array_key_exists('nullValue', $v) => null,
            isset($v['booleanValue']) => (bool) $v['booleanValue'],
            isset($v['integerValue']) => (int) $v['integerValue'],
            isset($v['doubleValue']) => (float) $v['doubleValue'],
            isset($v['stringValue']) => (string) $v['stringValue'],
            isset($v['timestampValue']) => (int) strtotime((string) $v['timestampValue']),
            isset($v['arrayValue']) => array_map(self::decode(...), $v['arrayValue']['values'] ?? []),
            isset($v['mapValue']) => self::decodeFields($v['mapValue']['fields'] ?? []),
            default => null,
        };
    }
}
