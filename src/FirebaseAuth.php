<?php
declare(strict_types=1);

namespace Homelab;

/** Valida o ID token do Firebase (RS256) com as chaves públicas do Google. Sem Admin SDK. */
final class FirebaseAuth
{
    private const CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';
    private const CERTS_TTL = 3600;

    public function __construct(
        private string $projectId,
        private HttpClient $http,
        private FileCache $cache,
    ) {
    }

    /** @return array{uid:string,email:string} */
    public function verify(string $idToken, int $now): array
    {
        $claims = Jwt::verifyRs256(
            $idToken,
            $this->certs($now),
            $this->projectId,
            'https://securetoken.google.com/' . $this->projectId,
            $now,
        );
        $email = $claims['email'] ?? null;
        $provider = $claims['firebase']['sign_in_provider'] ?? null;
        if (!is_string($email) || ($claims['email_verified'] ?? false) !== true || $provider !== 'google.com') {
            throw new AuthException('email not verified by Google');
        }
        $uid = $claims['sub'] ?? null;
        if (!is_string($uid) || !UserRepository::isKey($uid)) {
            throw new AuthException('invalid uid');
        }
        return ['uid' => $uid, 'email' => strtolower($email)];
    }

    /** @return array<string,string> */
    private function certs(int $now): array
    {
        $cached = $this->cache->get('google-certs', $now);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }
        $res = $this->http->request('GET', self::CERTS_URL);
        $certs = json_decode($res['body'], true);
        if ($res['status'] !== 200 || !is_array($certs) || $certs === []) {
            throw new \RuntimeException('could not load Google certificates');
        }
        $this->cache->put('google-certs', $certs, self::CERTS_TTL, $now);
        return $certs;
    }
}
