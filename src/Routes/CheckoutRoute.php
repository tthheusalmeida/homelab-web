<?php
declare(strict_types=1);

namespace Homelab\Routes;

use Homelab\Compliance;
use Homelab\Entitlement;
use Homelab\Plans;
use Homelab\Request;
use Homelab\Response;
use Homelab\Route;
use Homelab\Stripe;
use Homelab\UserRepository;

/** Cria a sessão de pagamento. Preço e usuário vêm do servidor, nunca do corpo da requisição. */
final class CheckoutRoute implements Route
{
    private const SUBMIT_NOTICE = 'A assinatura renova automaticamente todo mês até você cancelar, pelo portal em /assinaturas. Você pode desistir em até 7 dias após a compra, sem justificar, com reembolso integral.';

    public function __construct(
        private UserRepository $users,
        private Entitlement $entitlement,
        private Plans $plans,
        private Stripe $stripe,
        private string $siteUrl,
        private Compliance $compliance,
        private bool $requireTermsConsent = false,
    ) {
    }

    public function method(): string { return 'POST'; }
    public function path(): string { return '/checkout'; }
    public function rateLimitPerMinute(): int { return 10; }
    public function requiresAuth(): bool { return true; }

    public function handle(Request $request, ?array $identity, int $now): Response
    {
        $user = $this->users->ensure($identity, $now);
        $blocker = $this->compliance->blocker($user);
        if ($blocker !== null) {
            return new Response(403, ['error' => $blocker]);
        }
        if ($this->entitlement->compute($user, $now)['plan'] === 'pro') {
            return new Response(409, ['error' => 'already_pro']);
        }
        $key = $identity['uid'];
        $params = [
            'mode' => 'subscription',
            'line_items' => [['price' => $this->plans->activePriceId(), 'quantity' => 1]],
            'client_reference_id' => $key,
            'subscription_data' => ['metadata' => ['userKey' => $key]],
            'allow_promotion_codes' => 'true',
            'locale' => 'pt-BR',
            // Termos de renovação automática e de arrependimento ao lado do botão de pagar do Stripe.
            'custom_text' => ['submit' => ['message' => self::SUBMIT_NOTICE]],
            'success_url' => $this->siteUrl . '/assinaturas/?status=ok',
            'cancel_url' => $this->siteUrl . '/assinaturas/?status=cancel',
        ];
        if (is_string($user['stripeCustomerId'] ?? null)) {
            $params['customer'] = $user['stripeCustomerId'];
        } else {
            $params['customer_email'] = $identity['email'];
        }
        if ($this->requireTermsConsent) {
            $params['consent_collection'] = ['terms_of_service' => 'required']; // exige a URL dos Termos no painel do Stripe
        }
        $session = $this->stripe->post('/checkout/sessions', $params);
        $url = $session['url'] ?? null;
        if (!is_string($url) || !str_starts_with($url, 'https://checkout.stripe.com/')) {
            throw new \RuntimeException('unexpected checkout url');
        }
        return new Response(200, ['url' => $url]);
    }
}
