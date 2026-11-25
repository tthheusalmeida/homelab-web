<?php
declare(strict_types=1);

use Homelab\AuthException;
use Homelab\Config;
use Homelab\Entitlement;
use Homelab\Plans;
use Homelab\RateLimiter;
use Homelab\Signer;
use Homelab\Store\FirestoreStore;
use Homelab\Store\MemoryStore;
use Homelab\Stripe;
use Homelab\UserRepository;

$plans = new Plans(new Config(['stripe' => ['proPriceActive' => PRICE_NEW, 'proPricesAccepted' => [PRICE_OLD]]]));
$ent = new Entitlement($plans);
$day = 86400;
$sub = static fn (string $status, string $price = PRICE_NEW, int $end = NOW + 10 * 86400, bool $cancel = false): array => [
    'trialStartedAt' => NOW - 30 * 86400,
    'subscription' => ['status' => $status, 'priceId' => $price, 'currentPeriodEnd' => $end, 'cancelAtPeriodEnd' => $cancel],
];

test('when the trial started 3 days ago, should be trial', function () use ($ent, $day) {
    eq('trial', $ent->compute(['trialStartedAt' => NOW - 3 * $day], NOW)['plan']);
});
test('when the trial started 8 days ago, should be expired', function () use ($ent, $day) {
    eq('expired', $ent->compute(['trialStartedAt' => NOW - 8 * $day], NOW)['plan']);
});
test('when the subscription is active, should be pro with all permissions', function () use ($ent, $sub) {
    $r = $ent->compute($sub('active'), NOW);
    eq('pro', $r['plan']);
    ok(in_array('books.access', $r['permissions'], true));
});
test('when the subscription uses an older accepted price, should still be pro', function () use ($ent, $sub) {
    eq('pro', $ent->compute($sub('active', PRICE_OLD), NOW)['plan']);
});
test('when the price is unknown, should not be pro', function () use ($ent, $sub) {
    eq('expired', $ent->compute($sub('active', 'price_other'), NOW)['plan']);
});
test('when the subscription is canceled, should not be pro', function () use ($ent, $sub) {
    eq('expired', $ent->compute($sub('canceled'), NOW)['plan']);
});
test('when payment is past due inside the grace period, should keep pro', function () use ($ent, $sub, $day) {
    eq('pro', $ent->compute($sub('past_due', PRICE_NEW, NOW - 2 * $day), NOW)['plan']);
});
test('when payment is past due after the grace period, should drop pro', function () use ($ent, $sub, $day) {
    eq('expired', $ent->compute($sub('past_due', PRICE_NEW, NOW - 4 * $day), NOW)['plan']);
});
test('when the user cancels at period end, should stay pro and flag it', function () use ($ent, $sub) {
    $r = $ent->compute($sub('active', PRICE_NEW, NOW + 5 * 86400, true), NOW);
    eq('pro', $r['plan']);
    eq(true, $r['cancelsAtPeriodEnd']);
});
test('when pro, should expire the offline permission within 30 days', function () use ($ent, $sub, $day) {
    eq(NOW + 30 * $day, $ent->compute($sub('active', PRICE_NEW, NOW + 90 * $day), NOW)['expiresAt']);
});
test('when trial, should require internet within one day', function () use ($ent, $day) {
    eq(NOW + $day, $ent->compute(['trialStartedAt' => NOW], NOW)['expiresAt']);
});
test('when expired, should have no permissions', function () use ($ent, $day) {
    eq([], $ent->compute(['trialStartedAt' => NOW - 9 * $day], NOW)['permissions']);
});

test('when accepted prices are empty, should still accept the active price', function () {
    $p = new Plans(new Config(['stripe' => ['proPriceActive' => 'price_x']]));
    eq(['price_x'], $p->acceptedPriceIds());
});
test('when the feature is unknown, should refuse a daily limit', function () use ($plans) {
    throws(InvalidArgumentException::class, fn () => $plans->dailyLimit('trial', 'hack'));
});

test('when verifying a Stripe webhook with the right secret, should return the event', function () {
    $p = '{"type":"x.y"}';
    $sig = hash_hmac('sha256', NOW . '.' . $p, 's');
    eq('x.y', Stripe::verifyWebhook($p, 't=' . NOW . ',v1=' . $sig, 's', NOW)['type']);
});
test('when the Stripe signature uses another secret, should reject it', function () {
    $p = '{"type":"x.y"}';
    $sig = hash_hmac('sha256', NOW . '.' . $p, 'errado');
    throws(AuthException::class, fn () => Stripe::verifyWebhook($p, 't=' . NOW . ',v1=' . $sig, 's', NOW));
});
test('when the Stripe timestamp is older than 5 minutes, should reject it', function () {
    $p = '{"type":"x.y"}';
    $t = NOW - 600;
    throws(AuthException::class, fn () => Stripe::verifyWebhook($p, "t=$t,v1=" . hash_hmac('sha256', $t . '.' . $p, 's'), 's', NOW));
});
test('when the body changes after signing, should reject it', function () {
    $sig = hash_hmac('sha256', NOW . '.{"type":"a"}', 's');
    throws(AuthException::class, fn () => Stripe::verifyWebhook('{"type":"b"}', 't=' . NOW . ',v1=' . $sig, 's', NOW));
});
test('when the signature header is empty, should reject it', function () {
    throws(AuthException::class, fn () => Stripe::verifyWebhook('{}', '', 's', NOW));
});

test('when a token is signed, should verify with the public key', function () {
    $k = Signer::generate();
    $t = (new Signer($k['secretKey']))->sign(['plan' => 'pro', 'exp' => NOW + 100]);
    eq('pro', Signer::verify($t, $k['publicKey'], NOW)['plan']);
});
test('when the signed payload is edited, should fail verification', function () {
    $k = Signer::generate();
    [, $s] = explode('.', (new Signer($k['secretKey']))->sign(['plan' => 'trial', 'exp' => NOW + 100]));
    $fake = Homelab\Jwt::b64e('{"plan":"pro","exp":' . (NOW + 100) . '}') . '.' . $s;
    eq(null, Signer::verify($fake, $k['publicKey'], NOW));
});
test('when the signed token was made with another key, should fail verification', function () {
    $a = Signer::generate();
    $b = Signer::generate();
    $t = (new Signer($a['secretKey']))->sign(['plan' => 'pro', 'exp' => NOW + 100]);
    eq(null, Signer::verify($t, $b['publicKey'], NOW));
});
test('when the signed token is expired, should fail verification', function () {
    $k = Signer::generate();
    $t = (new Signer($k['secretKey']))->sign(['plan' => 'pro', 'exp' => NOW - 1]);
    eq(null, Signer::verify($t, $k['publicKey'], NOW));
});

test('when the e-mail differs only by case or spaces, should map to the same user key', function () {
    eq(UserRepository::keyFor('Ana@Example.com '), UserRepository::keyFor('ana@example.com'));
});
test('when the user already exists, should keep the original trial start', function () {
    $repo = new UserRepository(new MemoryStore());
    $id = ['uid' => 'u1', 'email' => 'ana@example.com'];
    $repo->ensure($id, NOW);
    eq(NOW, $repo->ensure($id, NOW + 5 * 86400)['trialStartedAt']);
});
test('when the same e-mail logs in with a new account id, should not renew the trial', function () {
    $repo = new UserRepository(new MemoryStore());
    $repo->ensure(['uid' => 'u1', 'email' => 'ana@example.com'], NOW);
    eq(NOW, $repo->ensure(['uid' => 'u2-nova', 'email' => 'ana@example.com'], NOW + 3 * 86400)['trialStartedAt']);
});
test('when consuming past the daily limit, should refuse', function () {
    $repo = new UserRepository(new MemoryStore());
    eq(true, $repo->consume('u_a', 'summary', 1, NOW)['allowed']);
    eq(false, $repo->consume('u_a', 'summary', 1, NOW)['allowed']);
});
test('when a new day starts in Sao Paulo, should reset the quota', function () {
    $repo = new UserRepository(new MemoryStore());
    $repo->consume('u_a', 'summary', 1, NOW);          // 31/12 21:00 em São Paulo
    eq(true, $repo->consume('u_a', 'summary', 1, NOW + 4 * 3600)['allowed']); // 01/01 01:00 em São Paulo
});
test('when 02:30 UTC, should still be the previous day in Sao Paulo', function () {
    eq('20251231', UserRepository::dayKey(NOW + 2 * 3600 + 1800));
});
test('when limits are per feature, should not share quota between features', function () {
    $repo = new UserRepository(new MemoryStore());
    $repo->consume('u_a', 'summary', 1, NOW);
    eq(true, $repo->consume('u_a', 'mindmap', 1, NOW)['allowed']);
});
test('when a subscription event is older than the saved one, should ignore it', function () {
    $store = new MemoryStore();
    $repo = new UserRepository($store);
    $repo->ensure(['uid' => 'u', 'email' => 'ana@example.com'], NOW);
    $k = userKey();
    $new = ['id' => 's', 'status' => 'active', 'priceId' => 'p', 'currentPeriodEnd' => NOW + 100, 'cancelAtPeriodEnd' => false];
    $repo->applySubscription($k, $new, NOW + 50);
    $repo->applySubscription($k, ['status' => 'canceled'] + $new, NOW + 10);
    eq('active', $repo->find($k)['subscription']['status']);
});

test('when requests exceed the limit inside a window, should block them', function () {
    $rl = new RateLimiter(tmpDir());
    eq(true, $rl->allow('k', 2, 60, NOW));
    eq(true, $rl->allow('k', 2, 60, NOW));
    eq(false, $rl->allow('k', 2, 60, NOW));
});
test('when the next window starts, should allow again', function () {
    $rl = new RateLimiter(tmpDir());
    $rl->allow('k', 1, 60, NOW);
    eq(true, $rl->allow('k', 1, 60, NOW + 60));
});
test('when keys differ, should limit each one separately', function () {
    $rl = new RateLimiter(tmpDir());
    $rl->allow('a', 1, 60, NOW);
    eq(true, $rl->allow('b', 1, 60, NOW));
});

test('when encoding and decoding Firestore fields, should round-trip nested data', function () {
    $data = ['a' => 1, 'b' => 'x', 'c' => true, 'd' => null, 'e' => ['n' => ['m' => 2]], 'f' => ['u', 'v'], 'g' => 1.5];
    eq($data, FirestoreStore::decodeFields(FirestoreStore::encodeFields($data)));
});
test('when the document already exists, should report create as false', function () {
    $http = new FakeHttp();
    $http->reply('oauth2.googleapis.com', 200, ['access_token' => 'tok', 'expires_in' => 3600]);
    $http->reply('firestore.googleapis.com', 409, '{"error":{"status":"ALREADY_EXISTS"}}');
    $res = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($res, $pem);
    $token = new Homelab\Store\ServiceAccountToken('sa@x.iam.gserviceaccount.com', $pem, $http, new Homelab\FileCache(tmpDir()));
    $store = new FirestoreStore('p', $token, $http, fn () => NOW);
    eq(false, $store->create('users/u_1', ['a' => 1]));
});
test('when merging with a stale version, should report false and not throw', function () {
    $http = new FakeHttp();
    $http->reply('oauth2.googleapis.com', 200, ['access_token' => 'tok', 'expires_in' => 3600]);
    $http->reply('firestore.googleapis.com', 400, '{"error":{"status":"FAILED_PRECONDITION"}}');
    $res = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($res, $pem);
    $token = new Homelab\Store\ServiceAccountToken('sa@x.iam.gserviceaccount.com', $pem, $http, new Homelab\FileCache(tmpDir()));
    $store = new FirestoreStore('p', $token, $http, fn () => NOW);
    eq(false, $store->merge('users/u_1', ['a' => 1], '2026-01-01T00:00:00Z'));
});
test('when Firestore answers with a server error, should throw', function () {
    $http = new FakeHttp();
    $http->reply('oauth2.googleapis.com', 200, ['access_token' => 'tok', 'expires_in' => 3600]);
    $http->reply('firestore.googleapis.com', 500, 'boom');
    $res = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($res, $pem);
    $token = new Homelab\Store\ServiceAccountToken('sa@x.iam.gserviceaccount.com', $pem, $http, new Homelab\FileCache(tmpDir()));
    throws(RuntimeException::class, fn () => (new FirestoreStore('p', $token, $http, fn () => NOW))->get('users/u_1'));
});
