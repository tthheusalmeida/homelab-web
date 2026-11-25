<?php
declare(strict_types=1);

use Homelab\App;
use Homelab\Config;
use Homelab\FileCache;
use Homelab\Jwt;
use Homelab\Logger;
use Homelab\RateLimiter;
use Homelab\Request;
use Homelab\Router;
use Homelab\Signer;
use Homelab\Store\MemoryStore;
use Homelab\UserRepository;

const NOW = 1767225600; // 2026-01-01 00:00 UTC
const PROJECT = 'homelab-test';
const WEBHOOK_SECRET = 'whsec_test';
const PRICE_NEW = 'price_new';
const PRICE_OLD = 'price_old';

/** Chaves RSA de mentira: fazem o papel do Google (token do Firebase). */
function rsaPair(): array
{
    static $pair = null;
    if ($pair === null) {
        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($res, $private);
        $pair = ['private' => $private, 'public' => openssl_pkey_get_details($res)['key']];
    }
    return $pair;
}

function tmpDir(): string
{
    $dir = sys_get_temp_dir() . '/homelab-test-' . bin2hex(random_bytes(6));
    mkdir($dir, 0700, true);
    return $dir;
}

/** @param array<string,mixed> $override */
function firebaseToken(string $email = 'ana@example.com', array $override = []): string
{
    $claims = $override + [
        'iss' => 'https://securetoken.google.com/' . PROJECT,
        'aud' => PROJECT,
        'sub' => 'uid-' . $email,
        'iat' => NOW - 10,
        'exp' => NOW + 3600,
        'email' => $email,
        'email_verified' => true,
        'firebase' => ['sign_in_provider' => 'google.com'],
    ];
    return Jwt::signRs256($claims, rsaPair()['private'], 'k1');
}

/** @return array{router:Router,http:FakeHttp,store:MemoryStore,signer:array{publicKey:string,secretKey:string}} */
function makeApp(?string $dir = null): array
{
    $dir ??= tmpDir();
    $http = new FakeHttp();
    $http->reply('securetoken@system.gserviceaccount.com', 200, ['k1' => rsaPair()['public']]);
    $keys = Signer::generate();
    $config = new Config([
        'siteUrl' => 'https://homelab.test',
        'allowedOrigins' => ['https://homelab.test'],
        'firebase' => ['projectId' => PROJECT],
        'stripe' => ['secretKey' => 'rk_test', 'webhookSecret' => WEBHOOK_SECRET, 'proPriceActive' => PRICE_NEW, 'proPricesAccepted' => [PRICE_OLD]],
        'signing' => ['secretKey' => $keys['secretKey']],
    ]);
    $store = new MemoryStore();
    $router = App::build($config, $store, $http, new FileCache($dir . '/cache'), new RateLimiter($dir . '/rl'), new Logger($dir . '/log'));
    return ['router' => $router, 'http' => $http, 'store' => $store, 'signer' => $keys];
}

/** @param array<string,string> $headers */
function req(string $method, string $path, array $headers = [], string $body = '', string $ip = '1.2.3.4'): Request
{
    return new Request($method, $path, array_change_key_case($headers, CASE_LOWER), $body, $ip);
}

function authed(string $method, string $path, string $email = 'ana@example.com', string $body = ''): Request
{
    return req($method, $path, ['Authorization' => 'Bearer ' . firebaseToken($email)], $body);
}

function userKey(string $email = 'ana@example.com'): string
{
    return UserRepository::keyFor($email);
}

/** Webhook com assinatura válida do Stripe. @param array<string,mixed> $event */
function stripeWebhook(array $event, ?string $secret = null, ?int $at = null): Request
{
    $payload = (string) json_encode($event);
    $t = $at ?? NOW;
    $sig = hash_hmac('sha256', $t . '.' . $payload, $secret ?? WEBHOOK_SECRET);
    return req('POST', '/webhook', ['Stripe-Signature' => "t=$t,v1=$sig"], $payload);
}

/** @return array<string,mixed> */
function subscriptionEvent(string $type, string $email, string $priceId, string $status = 'active', int $created = NOW, int $periodEnd = NOW + 30 * 86400): array
{
    return [
        'id' => 'evt_' . bin2hex(random_bytes(4)),
        'type' => $type,
        'created' => $created,
        'data' => ['object' => [
            'id' => 'sub_1',
            'customer' => 'cus_1',
            'status' => $status,
            'cancel_at_period_end' => false,
            'metadata' => ['userKey' => userKey($email)],
            'items' => ['data' => [['price' => ['id' => $priceId], 'current_period_end' => $periodEnd]]],
        ]],
    ];
}
