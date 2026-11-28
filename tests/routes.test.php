<?php
declare(strict_types=1);

use Homelab\Compliance;
use Homelab\Signer;
use Homelab\UserRepository;

$call = static fn (array $app, $request, int $now = NOW) => $app['router']->dispatch($request, $now);
$me = static fn (array $app, string $email = 'ana@example.com') => $app['router']->dispatch(authed('GET', '/me', $email), NOW);
$hook = static fn (array $app, array $event) => $app['router']->dispatch(stripeWebhook($event), NOW);

test('when the route does not exist, should answer 404', function () use ($call) {
    eq(404, $call(makeApp(), req('GET', '/nada'))->status);
});
test('when the method is wrong, should answer 405', function () use ($call) {
    eq(405, $call(makeApp(), req('DELETE', '/me'))->status);
});
test('when /me has no token, should answer 401', function () use ($call) {
    eq(401, $call(makeApp(), req('GET', '/me'))->status);
});
test('when /me has a garbage token, should answer 401 without details', function () use ($call) {
    $r = $call(makeApp(), req('GET', '/me', ['Authorization' => 'Bearer lixo.lixo.lixo']));
    eq(401, $r->status);
    eq(['error' => 'unauthorized'], $r->body);
});
test('when a browser calls from another origin, should answer 403', function () use ($call) {
    $r = $call(makeApp(), req('GET', '/me', ['Authorization' => 'Bearer ' . firebaseToken(), 'Origin' => 'https://evil.example']));
    eq(403, $r->status);
});
test('when the site origin calls, should be allowed', function () use ($call) {
    $r = $call(makeApp(), req('GET', '/me', ['Authorization' => 'Bearer ' . firebaseToken(), 'Origin' => 'https://homelab.test']));
    eq(200, $r->status);
});
test('when too many requests come from one IP, should answer 429', function () use ($call) {
    $app = makeApp();
    $last = 200;
    for ($i = 0; $i < 12; $i++) {
        $last = $call($app, req('POST', '/checkout', ['Authorization' => 'Bearer lixo'], '', '9.9.9.9'))->status;
    }
    eq(429, $last);
});

test('when logging in for the first time, should start a 7-day trial on the server clock', function () use ($me) {
    $app = makeApp();
    $r = $me($app);
    eq('trial', $r->body['plan']);
    eq(NOW + 7 * 86400, $r->body['trialEndsAt']);
    eq(NOW, $app['store']->get('users/' . userKey())['data']['trialStartedAt']);
});
test('when /me answers, should include a signature the app can verify with the public key', function () use ($me) {
    $app = makeApp();
    $signed = Signer::verify($me($app)->body['signed'], $app['signer']['publicKey'], NOW);
    eq('trial', $signed['plan']);
    eq('ana@example.com', $signed['email']);
});
test('when /me is the trial, should list one use per day for each feature', function () use ($me) {
    $q = $me(makeApp())->body['quotas'];
    eq(['limit' => 1, 'used' => 0], $q['summary']);
    eq(5, count($q));
});

test('when the trial user uses a feature once, should allow and then block', function () use ($call) {
    $app = makeApp();
    withTerms($app);
    eq(200, $call($app, authed('POST', '/usage', 'ana@example.com', '{"feature":"summary"}'))->status);
    eq(429, $call($app, authed('POST', '/usage', 'ana@example.com', '{"feature":"summary"}'))->status);
});
test('when the feature is invalid, should answer 400', function () use ($call) {
    eq(400, $call(makeApp(), authed('POST', '/usage', 'ana@example.com', '{"feature":"drop table"}'))->status);
});
test('when the trial is over, should answer 403 on usage', function () use ($call) {
    $app = makeApp();
    $app['store']->create('users/' . userKey(), ['email' => 'ana@example.com', 'uid' => 'x', 'trialStartedAt' => NOW - 9 * 86400, 'termsVersion' => Compliance::TERMS_VERSION]);
    eq(403, $call($app, authed('POST', '/usage', 'ana@example.com', '{"feature":"summary"}'))->status);
});
test('when the user is pro, should allow unlimited uses', function () use ($call, $me, $hook) {
    $app = makeApp();
    withTerms($app);
    $me($app);
    $hook($app, subscriptionEvent('customer.subscription.created', 'ana@example.com', PRICE_NEW));
    for ($i = 0; $i < 5; $i++) {
        eq(200, $call($app, authed('POST', '/usage', 'ana@example.com', '{"feature":"summary"}'))->status);
    }
});

test('when creating a checkout, should use the configured price and ignore the body', function () use ($call) {
    $app = makeApp();
    withTerms($app);
    $app['http']->on('api.stripe.com/v1/checkout/sessions', fn () => ['status' => 200, 'body' => '{"url":"https://checkout.stripe.com/c/pay/cs_1"}']);
    $r = $call($app, authed('POST', '/checkout', 'ana@example.com', '{"price":"price_free","userKey":"u_outro"}'));
    eq(200, $r->status);
    $sent = $app['http']->calls[array_key_last($app['http']->calls)]['body'];
    parse_str($sent, $form);
    eq(PRICE_NEW, $form['line_items'][0]['price']);
    eq(userKey(), $form['client_reference_id']);
    eq(userKey(), $form['subscription_data']['metadata']['userKey']);
    eq('ana@example.com', $form['customer_email']);
});
test('when the user is already pro, should refuse a second checkout', function () use ($call, $me, $hook) {
    $app = makeApp();
    withTerms($app);
    $me($app);
    $hook($app, subscriptionEvent('customer.subscription.created', 'ana@example.com', PRICE_NEW));
    eq(409, $call($app, authed('POST', '/checkout'))->status);
});
test('when Stripe returns an unexpected checkout URL, should fail with 500', function () use ($call) {
    $app = makeApp();
    withTerms($app);
    $app['http']->on('api.stripe.com/v1/checkout/sessions', fn () => ['status' => 200, 'body' => '{"url":"https://evil.example/pay"}']);
    eq(500, $call($app, authed('POST', '/checkout'))->status);
});
test('when the user has no Stripe customer, should answer 404 on the portal', function () use ($call) {
    eq(404, $call(makeApp(), authed('POST', '/portal'))->status);
});
test('when the user has a customer, should open the portal for that customer only', function () use ($call, $me, $hook) {
    $app = makeApp();
    $me($app);
    $hook($app, subscriptionEvent('customer.subscription.created', 'ana@example.com', PRICE_NEW));
    $app['http']->on('billing_portal/sessions', fn () => ['status' => 200, 'body' => '{"url":"https://billing.stripe.com/p/session/x"}']);
    eq(200, $call($app, authed('POST', '/portal', 'ana@example.com', '{"customer":"cus_de_outra_pessoa"}'))->status);
    parse_str($app['http']->calls[array_key_last($app['http']->calls)]['body'], $form);
    eq('cus_1', $form['customer']);
});

test('when the webhook signature is wrong, should answer 400 and change nothing', function () use ($me) {
    $app = makeApp();
    $me($app);
    $r = $app['router']->dispatch(stripeWebhook(subscriptionEvent('customer.subscription.created', 'ana@example.com', PRICE_NEW), 'whsec_errado'), NOW);
    eq(400, $r->status);
    eq('trial', $me($app)->body['plan']);
});
test('when the webhook has no signature, should answer 400', function () use ($call) {
    eq(400, $call(makeApp(), req('POST', '/webhook', [], '{"type":"customer.subscription.created"}'))->status);
});
test('when a paid subscription event arrives, should make the user pro', function () use ($me, $hook) {
    $app = makeApp();
    $me($app);
    eq(200, $hook($app, subscriptionEvent('customer.subscription.created', 'ana@example.com', PRICE_NEW))->status);
    $r = $me($app);
    eq('pro', $r->body['plan']);
    ok(in_array('ai.chat', $r->body['permissions'], true));
});
test('when the price is not one of ours, should not grant pro', function () use ($me, $hook) {
    $app = makeApp();
    $me($app);
    $hook($app, subscriptionEvent('customer.subscription.created', 'ana@example.com', 'price_anything'));
    eq('trial', $me($app)->body['plan']);
});
test('when the subscription is deleted, should drop pro', function () use ($me, $hook) {
    $app = makeApp();
    $me($app);
    $hook($app, subscriptionEvent('customer.subscription.created', 'ana@example.com', PRICE_NEW, 'active', NOW));
    $hook($app, subscriptionEvent('customer.subscription.deleted', 'ana@example.com', PRICE_NEW, 'canceled', NOW + 10));
    eq('trial', $me($app)->body['plan']);
});
test('when events arrive out of order, should keep the newest state', function () use ($me, $hook) {
    $app = makeApp();
    $me($app);
    $hook($app, subscriptionEvent('customer.subscription.updated', 'ana@example.com', PRICE_NEW, 'active', NOW + 100));
    $hook($app, subscriptionEvent('customer.subscription.updated', 'ana@example.com', PRICE_NEW, 'canceled', NOW + 5));
    eq('pro', $me($app)->body['plan']);
});
test('when the same event is delivered twice, should end in the same state', function () use ($me, $hook) {
    $app = makeApp();
    $me($app);
    $event = subscriptionEvent('customer.subscription.created', 'ana@example.com', PRICE_NEW);
    $hook($app, $event);
    eq(200, $hook($app, $event)->status);
    eq('pro', $me($app)->body['plan']);
});
test('when the subscription belongs to an unknown user, should ignore it with 200', function () use ($hook) {
    $app = makeApp();
    eq(200, $hook($app, subscriptionEvent('customer.subscription.created', 'ninguem@example.com', PRICE_NEW))->status);
    eq(null, $app['store']->get('users/' . userKey('ninguem@example.com')));
});
test('when the metadata carries a malformed user key, should ignore it', function () use ($me, $hook) {
    $app = makeApp();
    $me($app);
    $event = subscriptionEvent('customer.subscription.created', 'ana@example.com', PRICE_NEW);
    $event['data']['object']['metadata']['userKey'] = '../../users/x';
    eq(200, $hook($app, $event)->status);
    eq('trial', $me($app)->body['plan']);
});
test('when a later event has no metadata, should find the user by the saved customer', function () use ($me, $hook) {
    $app = makeApp();
    $me($app);
    $hook($app, subscriptionEvent('customer.subscription.created', 'ana@example.com', PRICE_NEW, 'active', NOW));
    $later = subscriptionEvent('customer.subscription.updated', 'ana@example.com', PRICE_NEW, 'canceled', NOW + 20);
    $later['data']['object']['metadata'] = [];
    $hook($app, $later);
    eq('trial', $me($app)->body['plan']);
});
test('when checkout completes, should link the Stripe customer to the user', function () use ($me, $hook) {
    $app = makeApp();
    $me($app);
    $hook($app, ['type' => 'checkout.session.completed', 'created' => NOW, 'data' => ['object' => ['client_reference_id' => userKey(), 'customer' => 'cus_9']]]);
    eq('cus_9', $app['store']->get('users/' . userKey())['data']['stripeCustomerId']);
});
test('when an unrelated event arrives, should answer 200 and do nothing', function () use ($hook) {
    eq(200, $hook(makeApp(), ['type' => 'invoice.created', 'created' => NOW, 'data' => ['object' => []]])->status);
});

test('when asking for plans, should return the price from Stripe and cache it', function () use ($call) {
    $app = makeApp();
    $app['http']->on('api.stripe.com/v1/prices/' . PRICE_NEW, fn () => ['status' => 200, 'body' => '{"unit_amount":2990,"currency":"brl","recurring":{"interval":"month"}}']);
    $r = $call($app, req('GET', '/plans'));
    eq(['amount' => 2990, 'currency' => 'brl', 'interval' => 'month'], $r->body['pro']);
    $call($app, req('GET', '/plans'));
    eq(1, $app['http']->countCalls('/v1/prices/'));
});
test('when Stripe is down, should answer plans with a null price instead of failing', function () use ($call) {
    $app = makeApp();
    $app['http']->reply('api.stripe.com/v1/prices', 500, 'x');
    $r = $call($app, req('GET', '/plans'));
    eq(200, $r->status);
    eq(null, $r->body['pro']);
});
test('when an internal error happens, should answer 500 without leaking details', function () use ($call) {
    $app = makeApp();
    withTerms($app);
    $app['http']->reply('api.stripe.com/v1/checkout/sessions', 500, 'segredo-interno');
    $r = $call($app, authed('POST', '/checkout'));
    eq(500, $r->status);
    eq(['error' => 'server_error'], $r->body);
});
