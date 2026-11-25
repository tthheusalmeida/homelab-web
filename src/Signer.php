<?php
declare(strict_types=1);

namespace Homelab;

/**
 * Assina a resposta de /api/me com Ed25519: o app confere com a chave PÚBLICA embutida,
 * então editar o arquivo local não vira Pro. A chave privada fica só no servidor.
 */
final class Signer
{
    public function __construct(private string $secretKeyB64)
    {
    }

    /** @return array{publicKey:string,secretKey:string} em base64 */
    public static function generate(): array
    {
        $pair = sodium_crypto_sign_keypair();
        return [
            'publicKey' => base64_encode(sodium_crypto_sign_publickey($pair)),
            'secretKey' => base64_encode(sodium_crypto_sign_secretkey($pair)),
        ];
    }

    /** @param array<string,mixed> $payload */
    public function sign(array $payload): string
    {
        $json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = sodium_crypto_sign_detached($json, (string) base64_decode($this->secretKeyB64, true));
        return Jwt::b64e($json) . '.' . Jwt::b64e($signature);
    }

    /** @return array<string,mixed>|null null se a assinatura ou o formato não conferem */
    public static function verify(string $token, string $publicKeyB64, int $now): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        $json = Jwt::b64d($parts[0]);
        $signature = Jwt::b64d($parts[1]);
        $publicKey = base64_decode($publicKeyB64, true);
        if ($json === false || $signature === false || $publicKey === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }
        if (!sodium_crypto_sign_verify_detached($signature, $json, $publicKey)) {
            return null;
        }
        $payload = json_decode($json, true);
        if (!is_array($payload) || ($payload['exp'] ?? 0) <= $now) {
            return null;
        }
        return $payload;
    }
}
