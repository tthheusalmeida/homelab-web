<?php
declare(strict_types=1);

namespace Homelab;

use Homelab\Routes\CheckoutRoute;
use Homelab\Routes\ConsentRoute;
use Homelab\Routes\MeRoute;
use Homelab\Routes\PlansRoute;
use Homelab\Routes\PortalRoute;
use Homelab\Routes\RefundRoute;
use Homelab\Routes\TermsRoute;
use Homelab\Routes\UsageRoute;
use Homelab\Routes\WebhookRoute;
use Homelab\Store\DocumentStore;
use Homelab\Store\FirestoreStore;
use Homelab\Store\ServiceAccountToken;

/** Raiz de composição: monta tudo a partir da configuração. */
final class App
{
    public static function fromConfig(Config $config, string $privateDir): Router
    {
        $http = new CurlHttpClient();
        $cache = new FileCache($privateDir . '/cache');
        $clock = static fn (): int => time();

        $sa = $config->get('firebase.serviceAccount');
        $store = new FirestoreStore(
            $config->string('firebase.projectId'),
            new ServiceAccountToken((string) ($sa['client_email'] ?? ''), (string) ($sa['private_key'] ?? ''), $http, $cache),
            $http,
            $clock,
        );
        return self::build($config, $store, $http, $cache, new RateLimiter($privateDir . '/ratelimit'), new Logger($privateDir . '/logs/api.log'));
    }

    public static function build(Config $config, DocumentStore $store, HttpClient $http, FileCache $cache, RateLimiter $limiter, Logger $log): Router
    {
        $plans = new Plans($config);
        $users = new UserRepository($store);
        $quota = new UsageQuota($store);
        $subscriptions = new SubscriptionRepository($store);
        $entitlement = new Entitlement($plans);
        $signer = new Signer($config->string('signing.secretKey'));
        $stripe = new Stripe($config->string('stripe.secretKey'), $http);
        $siteUrl = rtrim($config->string('siteUrl'), '/');
        $compliance = new Compliance($config->get('compliance.requireAgeVerification', false) === true);
        $withdrawal = new WithdrawalPolicy();

        $routes = [
            new MeRoute($users, $quota, $entitlement, $plans, $signer, $compliance, $withdrawal),
            new PlansRoute(new PriceCatalog($plans, $stripe, $cache)),
            new UsageRoute($users, $quota, $entitlement, $plans, $compliance),
            new CheckoutRoute($users, $entitlement, $plans, $stripe, $siteUrl, $compliance, $config->get('stripe.requireTermsConsent', false) === true),
            new PortalRoute($users, $stripe, $siteUrl),
            new RefundRoute($users, $subscriptions, $withdrawal, $stripe),
            new TermsRoute($users),
            new ConsentRoute($store),
            new WebhookRoute($users, $subscriptions, new WebhookVerifier($config->string('stripe.webhookSecret')), $log),
        ];
        $origins = $config->get('allowedOrigins', [$siteUrl]);
        $guards = [new RateLimitGuard($limiter), new OriginGuard(is_array($origins) ? $origins : [$siteUrl])];
        return new Router($routes, $guards, new FirebaseAuth($config->string('firebase.projectId'), $http, $cache), $log, new StoreAccessLog($store, ['/webhook']));
    }
}
