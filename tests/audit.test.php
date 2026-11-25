<?php
declare(strict_types=1);

// Auditoria do repositório: nenhum segredo versionado e nada executável/sensível na pasta pública.
$root = dirname(__DIR__);
$skip = ['/.git/', '/private-dev/', '/tests/', '/docs/'];

/** @return array<int,string> */
$files = (static function () use ($root, $skip): array {
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $p = str_replace('\\', '/', $f->getPathname());
        $rel = substr($p, strlen($root));
        foreach ($skip as $s) {
            if (str_contains($rel . '/', $s) || str_contains($rel, $s)) {
                continue 2;
            }
        }
        if (preg_match('/\.(png|jpg|ico|woff2?)$/i', $p) === 1) {
            continue;
        }
        $out[] = $rel;
    }
    return $out;
})();

test('when scanning tracked files, should find no private keys or live API secrets', function () use ($root, $files) {
    $patterns = ['/-----BEGIN (RSA |EC )?PRIVATE KEY-----/', '/\b(sk|rk)_live_[A-Za-z0-9]{10,}/', '/\b(sk|rk)_test_[A-Za-z0-9]{20,}/', '/whsec_[A-Za-z0-9]{20,}/', '/"private_key"\s*:\s*"-----/'];
    foreach ($files as $rel) {
        $text = (string) file_get_contents($root . $rel);
        foreach ($patterns as $re) {
            ok(preg_match($re, $text) !== 1, "possível segredo em $rel");
        }
    }
});
test('when listing the public site folder, should contain no PHP', function () use ($files) {
    foreach ($files as $rel) {
        ok(!(str_starts_with($rel, '/site/') && str_ends_with($rel, '.php')), "PHP na pasta pública: $rel");
    }
});
test('when listing the public api folder, should contain only the entry point and htaccess', function () {
    $found = array_values(array_diff(scandir(dirname(__DIR__) . '/api') ?: [], ['.', '..']));
    sort($found);
    eq(['.htaccess', 'index.php'], $found);
});
test('when checking the entry point, should not print error details', function () {
    $code = (string) file_get_contents(dirname(__DIR__) . '/api/index.php');
    ok(!str_contains($code, 'getMessage'), 'index.php não pode mostrar mensagens de erro');
    ok(str_contains($code, 'catch (Throwable)'));
});
test('when checking git ignore rules, should ignore config and env files', function () {
    $ignore = (string) file_get_contents(dirname(__DIR__) . '/.gitignore');
    foreach (['config.php', '.env.*', 'private-dev/', 'serviceAccount*.json'] as $needle) {
        ok(str_contains($ignore, $needle), "falta no .gitignore: $needle");
    }
});
test('when checking Firestore rules, should deny every client access', function () {
    $rules = (string) file_get_contents(dirname(__DIR__) . '/firestore.rules');
    ok(str_contains($rules, 'allow read, write: if false;'));
    ok(!str_contains($rules, 'if true'));
});
test('when checking the account pages, should have no inline script or style', function () {
    foreach (['entrar', 'assinaturas'] as $page) {
        $html = (string) file_get_contents(dirname(__DIR__) . "/site/$page/index.html");
        ok(preg_match('/<script(?![^>]*\bsrc=)[^>]*>/i', $html) !== 1, "script inline em $page");
        ok(preg_match('/\sstyle="/i', $html) !== 1, "style inline em $page");
        ok(preg_match('/\son[a-z]+="/i', $html) !== 1, "handler inline em $page");
    }
});
test('when checking the account scripts, should never write server data with innerHTML', function () {
    foreach (['entrar', 'assinaturas', 'account'] as $js) {
        ok(!str_contains((string) file_get_contents(dirname(__DIR__) . "/site/assets/js/$js.js"), 'innerHTML'), "innerHTML em $js.js");
    }
});
