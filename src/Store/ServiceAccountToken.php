<?php
declare(strict_types=1);

namespace Homelab\Store;

use Homelab\FileCache;
use Homelab\HttpClient;
use Homelab\Jwt;

/** Token de acesso OAuth da conta de serviço (escopo só do Firestore), em cache até perto de expirar. */
final class ServiceAccountToken
{
    private const SCOPE = 'https://www.googleapis.com/auth/datastore';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function __construct(
        private string $clientEmail,
        private string $privateKey,
        private HttpClient $http,
        private FileCache $cache,
    ) {
    }

    public function get(int $now): string
    {
        $cached = $this->cache->get('sa-token', $now);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        $assertion = Jwt::signRs256([
            'iss' => $this->clientEmail,
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ], $this->privateKey);
        $res = $this->http->request('POST', self::TOKEN_URL, ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]));
        $body = json_decode($res['body'], true);
        if ($res['status'] !== 200 || !is_array($body) || !is_string($body['access_token'] ?? null)) {
            throw new \RuntimeException('could not get service account token');
        }
        $this->cache->put('sa-token', $body['access_token'], max(60, (int) ($body['expires_in'] ?? 3600) - 300), $now);
        return $body['access_token'];
    }
}
