<?php
declare(strict_types=1);

namespace Homelab;

/** JWT RS256: verificação dos tokens do Firebase e assinatura da conta de serviço. */
final class Jwt
{
    public static function b64e(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function b64d(string $text): string|false
    {
        $padded = strtr($text, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        return base64_decode($padded, true);
    }

    /**
     * @param array<string,string> $keys kid => PEM (certificado ou chave pública)
     * @return array<string,mixed> claims
     */
    public static function verifyRs256(string $jwt, array $keys, string $audience, string $issuer, int $now, int $leeway = 60): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new AuthException('malformed');
        }
        [$h, $p, $s] = $parts;
        $header = json_decode((string) self::b64d($h), true);
        $claims = json_decode((string) self::b64d($p), true);
        $signature = self::b64d($s);
        if (!is_array($header) || !is_array($claims) || $signature === false) {
            throw new AuthException('malformed');
        }
        if (($header['alg'] ?? null) !== 'RS256') {
            throw new AuthException('alg');
        }
        $kid = $header['kid'] ?? null;
        if (!is_string($kid) || !isset($keys[$kid])) {
            throw new AuthException('kid');
        }
        $key = openssl_pkey_get_public($keys[$kid]);
        if ($key === false || openssl_verify($h . '.' . $p, $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new AuthException('signature');
        }
        if (($claims['iss'] ?? null) !== $issuer || ($claims['aud'] ?? null) !== $audience) {
            throw new AuthException('issuer or audience');
        }
        $exp = $claims['exp'] ?? null;
        $iat = $claims['iat'] ?? null;
        if (!is_int($exp) || $exp < $now - $leeway || !is_int($iat) || $iat > $now + $leeway) {
            throw new AuthException('time');
        }
        if (!is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw new AuthException('subject');
        }
        return $claims;
    }

    /** @param array<string,mixed> $claims */
    public static function signRs256(array $claims, string $privateKeyPem, string $kid = ''): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        if ($kid !== '') {
            $header['kid'] = $kid;
        }
        $input = self::b64e((string) json_encode($header)) . '.' . self::b64e((string) json_encode($claims));
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false || !openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('could not sign');
        }
        return $input . '.' . self::b64e($signature);
    }
}
