<?php
declare(strict_types=1);

namespace Homelab;

/** Regras dos planos (no código) e preços (na configuração, trocados por variável de ambiente no deploy). */
final class Plans
{
    public const TRIAL_DAYS = 7;
    public const PRO_GRACE_DAYS = 3;
    public const PRO_OFFLINE_DAYS = 30;
    public const TRIAL_ONLINE_SECONDS = 86400;

    /** Recursos com cota diária no trial: 1 por dia cada. */
    public const DAILY_FEATURES = ['summary', 'infographic', 'mindmap', 'videoTranscription', 'videoSummary'];

    public const TRIAL_PERMISSIONS = ['summary.create', 'infographic.create', 'mindmap.create', 'video.transcribe', 'video.summarize'];
    public const PRO_PERMISSIONS = [
        'summary.create', 'infographic.create', 'mindmap.create', 'video.transcribe', 'video.summarize',
        'books.access', 'ai.chat', 'ai.tools',
    ];

    public function __construct(private Config $config)
    {
    }

    /** Preço usado em novos checkouts. */
    public function activePriceId(): string
    {
        return $this->config->string('stripe.proPriceActive');
    }

    /** Todos os preços que valem como Pro (inclui os antigos, para não derrubar quem já assina). @return array<int,string> */
    public function acceptedPriceIds(): array
    {
        $accepted = $this->config->get('stripe.proPricesAccepted', []);
        $ids = is_array($accepted) ? array_values(array_filter($accepted, 'is_string')) : [];
        $ids[] = $this->activePriceId();
        return array_values(array_unique($ids));
    }

    public function isProPrice(string $priceId): bool
    {
        return in_array($priceId, $this->acceptedPriceIds(), true);
    }

    /** @return array<int,string> */
    public function permissionsFor(string $plan): array
    {
        return match ($plan) {
            'pro' => self::PRO_PERMISSIONS,
            'trial' => self::TRIAL_PERMISSIONS,
            default => [],
        };
    }

    /** Limite diário por recurso; null = sem limite. */
    public function dailyLimit(string $plan, string $feature): ?int
    {
        if (!in_array($feature, self::DAILY_FEATURES, true)) {
            throw new \InvalidArgumentException('unknown feature');
        }
        return match ($plan) {
            'trial' => 1,
            'pro' => null,
            default => 0,
        };
    }
}
