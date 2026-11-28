<?php
declare(strict_types=1);

namespace Homelab;

/** Pré-requisitos legais para usar o serviço: aceite dos termos vigentes e (quando ligada) idade verificada. */
final class Compliance
{
    /** Subir esta versão quando os Termos ou a Política mudarem: todos precisam aceitar de novo. */
    public const TERMS_VERSION = '2026-11-28';

    /** Autodeclaração ("tenho 18 anos") não vale: só um método de verificação registrado no servidor (ver AgeVerifier). */
    public function __construct(private bool $requireAgeVerification)
    {
    }

    /** @param array<string,mixed> $user @return array{termsVersion:string,termsAccepted:bool,termsAcceptedAt:?int,ageRequired:bool,ageVerified:bool} */
    public function status(array $user): array
    {
        $accepted = ($user['termsVersion'] ?? null) === self::TERMS_VERSION;
        $age = $user['ageVerified'] ?? null;
        return [
            'termsVersion' => self::TERMS_VERSION,
            'termsAccepted' => $accepted,
            'termsAcceptedAt' => $accepted && is_int($user['termsAcceptedAt'] ?? null) ? $user['termsAcceptedAt'] : null,
            'ageRequired' => $this->requireAgeVerification,
            'ageVerified' => is_array($age) && is_string($age['method'] ?? null) && $age['method'] !== '',
        ];
    }

    /** Código de erro se o usuário ainda não pode usar o serviço, ou null. @param array<string,mixed> $user */
    public function blocker(array $user): ?string
    {
        $status = $this->status($user);
        if (!$status['termsAccepted']) {
            return 'terms_required';
        }
        if ($status['ageRequired'] && !$status['ageVerified']) {
            return 'age_required';
        }
        return null;
    }
}
