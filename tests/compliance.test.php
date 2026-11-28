<?php
declare(strict_types=1);

use Homelab\Compliance;
use Homelab\Signer;
use Homelab\Store\FirestoreStore;
use Homelab\Store\Instant;
use Homelab\StoreAccessLog;

$call = static fn (array $app, $request, int $now = NOW) => $app['router']->dispatch($request, $now);
$me = static fn (array $app, string $email = 'ana@example.com') => $app['router']->dispatch(authed('GET', '/me', $email), NOW);

/** Assinatura criada pelo Stripe (webhook) com a data de início da contratação. */
$subscribe = static function (array $app, int $startedAt = NOW): void {
    $event = subscriptionEvent('customer.subscription.created', 'ana@example.com', PRICE_NEW);
    $event['data']['object']['start_date'] = $startedAt;
    $app['router']->dispatch(stripeWebhook($event), NOW);
};

/** Stripe falso para o fluxo de desistência. */
$stripeForRefund = static function (array $app): void {
    $app['http']->reply('api.stripe.com/v1/charges', 200, ['data' => [['id' => 'ch_1', 'paid' => true, 'refunded' => false]]]);
    $app['http']->reply('api.stripe.com/v1/refunds', 200, ['id' => 're_1']);
    $app['http']->reply('api.stripe.com/v1/subscriptions/sub_1', 200, ['id' => 'sub_1', 'status' => 'canceled']);
};

// ───────── Consentimento de cookies ─────────
test('when the banner choice arrives, should store it without any personal data', function () use ($call) {
    $app = makeApp();
    $r = $call($app, req('POST', '/consent', [], '{"version":1,"analytics":false,"marketing":true}'));
    eq(200, $r->status);
    $saved = array_values($app['store']->all('consents'));
    eq(1, count($saved));
    eq(false, $saved[0]['analytics']);
    eq(true, $saved[0]['marketing']);
    eq(['at', 'version', 'analytics', 'marketing'], array_keys($saved[0]));
});
test('when the consent body is not strict booleans, should answer 400 and store nothing', function () use ($call) {
    $app = makeApp();
    eq(400, $call($app, req('POST', '/consent', [], '{"version":1,"analytics":"yes","marketing":false}'))->status);
    eq(400, $call($app, req('POST', '/consent', [], '{"analytics":true,"marketing":true}'))->status);
    eq([], $app['store']->all('consents'));
});

// ───────── Termos ─────────
test('when the user has not accepted the terms, should block usage and checkout', function () use ($call) {
    $app = makeApp();
    $r = $call($app, authed('POST', '/usage', 'ana@example.com', '{"feature":"summary"}'));
    eq(403, $r->status);
    eq(['error' => 'terms_required'], $r->body);
    eq(['error' => 'terms_required'], $call($app, authed('POST', '/checkout'))->body);
});
test('when accepting an old terms version, should answer 409 and keep it unaccepted', function () use ($call, $me) {
    $app = makeApp();
    $r = $call($app, authed('POST', '/terms/accept', 'ana@example.com', '{"version":"2020-01-01"}'));
    eq(409, $r->status);
    eq(false, $me($app)->body['terms']['accepted']);
});
test('when accepting the current terms, should record version and time and unblock usage', function () use ($call, $me) {
    $app = makeApp();
    eq(200, $call($app, authed('POST', '/terms/accept', 'ana@example.com', '{"version":"' . Compliance::TERMS_VERSION . '"}'))->status);
    $body = $me($app)->body;
    eq(['currentVersion' => Compliance::TERMS_VERSION, 'accepted' => true, 'acceptedAt' => NOW], $body['terms']);
    eq(200, $call($app, authed('POST', '/usage', 'ana@example.com', '{"feature":"summary"}'))->status);
});
test('when /me is signed, should carry the terms and age flags for the desktop app', function () use ($me) {
    $app = makeApp();
    $before = Signer::verify($me($app)->body['signed'], $app['signer']['publicKey'], NOW);
    eq(false, $before['termsOk']);
    withTerms($app);
    $after = Signer::verify($me($app)->body['signed'], $app['signer']['publicKey'], NOW);
    eq(true, $after['termsOk']);
    eq(true, $after['ageOk']);
});

// ───────── Idade ─────────
test('when age verification is required and missing, should block usage with age_required', function () use ($call) {
    $app = makeApp(null, ['compliance' => ['requireAgeVerification' => true]]);
    withTerms($app);
    $r = $call($app, authed('POST', '/usage', 'ana@example.com', '{"feature":"summary"}'));
    eq(403, $r->status);
    eq(['error' => 'age_required'], $r->body);
});
test('when age was verified by a method, should allow usage', function () use ($call, $me) {
    $app = makeApp(null, ['compliance' => ['requireAgeVerification' => true]]);
    withTerms($app);
    $app['store']->merge('users/' . userKey(), ['ageVerified' => ['method' => 'test-provider', 'at' => NOW]]);
    eq(200, $call($app, authed('POST', '/usage', 'ana@example.com', '{"feature":"summary"}'))->status);
    eq(true, $me($app)->body['age']['verified']);
});
test('when only a self-declaration is stored, should not count as verified', function () use ($call) {
    $app = makeApp(null, ['compliance' => ['requireAgeVerification' => true]]);
    withTerms($app);
    $app['store']->merge('users/' . userKey(), ['ageVerified' => true, 'over18' => true]);
    eq(403, $call($app, authed('POST', '/usage', 'ana@example.com', '{"feature":"summary"}'))->status);
});

// ───────── Checkout: renovação automática e arrependimento ─────────
test('when creating a checkout, should send the renewal and withdrawal notice for the pay button', function () use ($call) {
    $app = makeApp();
    withTerms($app);
    $app['http']->reply('api.stripe.com/v1/checkout/sessions', 200, ['url' => 'https://checkout.stripe.com/c/pay/cs_1']);
    eq(200, $call($app, authed('POST', '/checkout'))->status);
    parse_str($app['http']->calls[array_key_last($app['http']->calls)]['body'], $form);
    ok(str_contains($form['custom_text']['submit']['message'], 'renova automaticamente'));
    ok(str_contains($form['custom_text']['submit']['message'], '7 dias'));
    eq('pt-BR', $form['locale']);
    ok(!isset($form['consent_collection']), 'o checkbox do Stripe só liga com a URL dos Termos cadastrada');
});
test('when the terms checkbox is enabled in config, should ask Stripe to collect the consent', function () use ($call) {
    $app = makeApp(null, ['stripe' => ['requireTermsConsent' => true]]);
    withTerms($app);
    $app['http']->reply('api.stripe.com/v1/checkout/sessions', 200, ['url' => 'https://checkout.stripe.com/c/pay/cs_1']);
    $call($app, authed('POST', '/checkout'));
    parse_str($app['http']->calls[array_key_last($app['http']->calls)]['body'], $form);
    eq('required', $form['consent_collection']['terms_of_service']);
});

// ───────── Desistência em 7 dias ─────────
test('when the subscription is new, should tell /me until when the user can withdraw', function () use ($me, $subscribe) {
    $app = makeApp();
    withTerms($app);
    $subscribe($app);
    eq(NOW + 7 * 86400, $me($app)->body['withdrawalUntil']);
});
test('when withdrawing inside 7 days, should refund the full charge, cancel and drop pro', function () use ($call, $me, $subscribe, $stripeForRefund) {
    $app = makeApp();
    withTerms($app);
    $subscribe($app);
    $stripeForRefund($app);
    $r = $call($app, authed('POST', '/refund'));
    eq(200, $r->status);
    $refund = array_values(array_filter($app['http']->calls, fn (array $c) => str_contains($c['url'], '/v1/refunds')))[0];
    parse_str($refund['body'], $form);
    eq('ch_1', $form['charge']);
    ok(!isset($form['amount']), 'reembolso integral: sem valor parcial');
    ok(in_array('Idempotency-Key: withdrawal-sub_1', $refund['headers'], true), 'chave de idempotência');
    eq('DELETE', $app['http']->calls[array_key_last($app['http']->calls)]['method']);
    $body = $me($app)->body;
    ok($body['plan'] !== 'pro', 'o plano Pro acaba na hora');
    eq(null, $body['withdrawalUntil']);
});
test('when withdrawing twice, should answer 409 and not refund again', function () use ($call, $subscribe, $stripeForRefund) {
    $app = makeApp();
    withTerms($app);
    $subscribe($app);
    $stripeForRefund($app);
    $call($app, authed('POST', '/refund'));
    $refunds = fn () => count(array_filter($app['http']->calls, fn (array $c) => str_contains($c['url'], '/v1/refunds')));
    eq(1, $refunds());
    $r = $call($app, authed('POST', '/refund'));
    eq(409, $r->status);
    eq(['error' => 'already_withdrawn'], $r->body);
    eq(1, $refunds());
});
test('when the 7 days are over, should answer 403 and not touch Stripe', function () use ($call, $subscribe, $stripeForRefund) {
    $app = makeApp();
    withTerms($app);
    $subscribe($app, NOW - 8 * 86400);
    $stripeForRefund($app);
    $r = $call($app, authed('POST', '/refund'));
    eq(403, $r->status);
    eq(['error' => 'withdrawal_period_over'], $r->body);
    eq(0, $app['http']->countCalls('/v1/refunds'));
});
test('when the user has no subscription, should answer 404 on refund', function () use ($call) {
    $app = makeApp();
    eq(404, $call($app, authed('POST', '/refund'))->status);
});
test('when the refund endpoint has no token, should answer 401', function () use ($call) {
    eq(401, $call(makeApp(), req('POST', '/refund'))->status);
});

// ───────── Registro de acesso (Marco Civil, art. 15) ─────────
test('when an authenticated request is made, should log ip, time and user for 6 months', function () use ($me) {
    $app = makeApp();
    $me($app);
    $logs = array_values($app['store']->all('accessLogs'));
    eq(1, count($logs));
    eq('1.2.3.4', $logs[0]['ip']);
    eq('/me', $logs[0]['path']);
    eq(200, $logs[0]['status']);
    eq(userKey(), $logs[0]['uid']);
    eq(NOW, $logs[0]['at']->seconds);
    eq(NOW + StoreAccessLog::RETENTION_DAYS * 86400, $logs[0]['expireAt']->seconds);
    ok(StoreAccessLog::RETENTION_DAYS >= 183, 'mínimo de 6 meses');
});
test('when a request has no valid token, should still log the access without a user', function () use ($call) {
    $app = makeApp();
    $call($app, req('GET', '/me'));
    $logs = array_values($app['store']->all('accessLogs'));
    eq(401, $logs[0]['status']);
    ok(!array_key_exists('uid', $logs[0]));
});
test('when a log entry is written, should never hold tokens, headers or body', function () use ($call) {
    $app = makeApp();
    $call($app, req('POST', '/consent', ['Authorization' => 'Bearer segredo-xyz', 'User-Agent' => 'x'], '{"version":1,"analytics":true,"marketing":true}'));
    foreach ($app['store']->all('accessLogs') as $entry) {
        eq(['at', 'ip', 'method', 'path', 'status', 'expireAt'], array_keys($entry));
        ok(!str_contains((string) json_encode($entry, JSON_PARTIAL_OUTPUT_ON_ERROR), 'segredo-xyz'));
    }
});
test('when the Stripe webhook calls, should not be logged as a user access', function () use ($subscribe) {
    $app = makeApp();
    $subscribe($app);
    eq([], $app['store']->all('accessLogs'));
});
test('when the rate limit blocks a request, should not fill the database with it', function () use ($call) {
    $app = makeApp();
    for ($i = 0; $i < 25; $i++) {
        $call($app, req('POST', '/consent', [], '{"version":1,"analytics":true,"marketing":true}', '8.8.8.8'));
    }
    eq(20, count($app['store']->all('accessLogs')));
});
test('when the log write fails, should still answer the request', function () {
    $dir = tmpDir();
    $app = makeApp($dir);
    $http = new FakeHttp();
    $failing = new class implements Homelab\Store\DocumentStore {
        public function get(string $path): ?array { return null; }
        public function create(string $path, array $data): bool { throw new RuntimeException('down'); }
        public function merge(string $path, array $data, ?string $version = null): bool { return true; }
    };
    $log = new StoreAccessLog($failing);
    $router = new Homelab\Router([], [], new Homelab\FirebaseAuth(PROJECT, $http, new Homelab\FileCache($dir . '/c2')), new Homelab\Logger($dir . '/log2'), $log);
    eq(404, $router->dispatch(req('GET', '/nada'), NOW)->status);
    ok(str_contains((string) file_get_contents($dir . '/log2'), 'access log'));
});

// ───────── Firestore: timestamp para a política de TTL ─────────
test('when an Instant is encoded, should become a Firestore timestamp and decode back to seconds', function () {
    $fields = FirestoreStore::encodeFields(['expireAt' => new Instant(NOW)]);
    eq(['timestampValue' => '2026-01-01T00:00:00Z'], $fields['expireAt']);
    eq(['expireAt' => NOW], FirestoreStore::decodeFields($fields));
});
