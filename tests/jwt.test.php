<?php
declare(strict_types=1);

use Homelab\AuthException;
use Homelab\FileCache;
use Homelab\FirebaseAuth;
use Homelab\Jwt;

$keys = ['k1' => rsaPair()['public']];
$iss = 'https://securetoken.google.com/' . PROJECT;
$verify = static fn (string $t) => Jwt::verifyRs256($t, $keys, PROJECT, $iss, NOW);

test('when the token is valid, should return its claims', function () use ($verify) {
    eq('ana@example.com', $verify(firebaseToken())['email']);
});
test('when the token is expired, should reject it', function () use ($verify) {
    throws(AuthException::class, fn () => $verify(firebaseToken('a@b.c', ['exp' => NOW - 3600])));
});
test('when the audience is another project, should reject it', function () use ($verify) {
    throws(AuthException::class, fn () => $verify(firebaseToken('a@b.c', ['aud' => 'outro-projeto'])));
});
test('when the issuer is wrong, should reject it', function () use ($verify) {
    throws(AuthException::class, fn () => $verify(firebaseToken('a@b.c', ['iss' => 'https://evil.example'])));
});
test('when the algorithm is none, should reject it', function () use ($verify) {
    $h = Jwt::b64e('{"alg":"none","kid":"k1"}');
    $p = Jwt::b64e((string) json_encode(['iss' => 'https://securetoken.google.com/' . PROJECT, 'aud' => PROJECT, 'sub' => 'x', 'iat' => NOW, 'exp' => NOW + 99]));
    throws(AuthException::class, fn () => $verify("$h.$p."));
});
test('when the payload is tampered, should reject the signature', function () use ($verify) {
    [$h, , $s] = explode('.', firebaseToken('ana@example.com'));
    $evil = Jwt::b64e((string) json_encode(['iss' => 'https://securetoken.google.com/' . PROJECT, 'aud' => PROJECT, 'sub' => 'x', 'iat' => NOW, 'exp' => NOW + 99, 'email' => 'admin@example.com']));
    throws(AuthException::class, fn () => $verify("$h.$evil.$s"));
});
test('when the key id is unknown, should reject it', function () {
    $t = firebaseToken();
    throws(AuthException::class, fn () => Jwt::verifyRs256($t, ['outra' => rsaPair()['public']], PROJECT, 'https://securetoken.google.com/' . PROJECT, NOW));
});
test('when the token has no subject, should reject it', function () use ($verify) {
    throws(AuthException::class, fn () => $verify(firebaseToken('a@b.c', ['sub' => ''])));
});

$auth = static function (): FirebaseAuth {
    $http = new FakeHttp();
    $http->reply('securetoken@system.gserviceaccount.com', 200, ['k1' => rsaPair()['public']]);
    return new FirebaseAuth(PROJECT, $http, new FileCache(tmpDir()));
};
test('when the e-mail is not verified, should reject the login', function () use ($auth) {
    throws(AuthException::class, fn () => $auth()->verify(firebaseToken('a@b.c', ['email_verified' => false]), NOW));
});
test('when the provider is not Google, should reject the login', function () use ($auth) {
    throws(AuthException::class, fn () => $auth()->verify(firebaseToken('a@b.c', ['firebase' => ['sign_in_provider' => 'password']]), NOW));
});
test('when the token subject is not a plain uid, should reject the login', function () use ($auth) {
    throws(AuthException::class, fn () => $auth()->verify(firebaseToken('a@b.c', ['sub' => '../users/x']), NOW));
});
test('when the login is a verified Google account, should normalize the e-mail', function () use ($auth) {
    eq('ana@example.com', $auth()->verify(firebaseToken('Ana@Example.com'), NOW)['email']);
});
test('when verifying twice, should fetch Google certificates only once', function () {
    $http = new FakeHttp();
    $http->reply('securetoken@system.gserviceaccount.com', 200, ['k1' => rsaPair()['public']]);
    $a = new FirebaseAuth(PROJECT, $http, new FileCache(tmpDir()));
    $a->verify(firebaseToken(), NOW);
    $a->verify(firebaseToken(), NOW);
    eq(1, $http->countCalls('securetoken'));
});
