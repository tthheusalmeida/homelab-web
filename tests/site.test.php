<?php
declare(strict_types=1);

// Conformidade do site estático (cookies, terceiros, identificação da empresa, avisos do checkout).
use Homelab\Compliance;

$siteDir = dirname(__DIR__) . '/site';

/** @return array<int,string> caminhos relativos a site/ */
$siteFiles = static function (array $extensions) use ($siteDir): array {
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($siteDir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (in_array(strtolower($file->getExtension()), $extensions, true)) {
            $out[] = str_replace('\\', '/', substr($file->getPathname(), strlen($siteDir) + 1));
        }
    }
    sort($out);
    return $out;
};
$read = static fn (string $rel): string => (string) file_get_contents($siteDir . '/' . $rel);

test('when scanning the site, should load no font or script from third-party CDNs', function () use ($siteFiles, $read) {
    foreach ($siteFiles(['html', 'css', 'js']) as $rel) {
        $text = $read($rel);
        foreach (['fonts.googleapis.com', 'fonts.gstatic.com', 'cdnjs.cloudflare.com', 'cdn.jsdelivr.net', 'unpkg.com', 'ajax.googleapis.com'] as $host) {
            ok(!str_contains($text, $host), "$rel carrega de $host (vaza o IP do visitante)");
        }
    }
});
test('when reading fonts.css, should point only to font files that exist', function () use ($siteDir, $read) {
    preg_match_all('#url\((/assets/fonts/[^)]+)\)#', $read('assets/css/fonts.css'), $m);
    ok(count($m[1]) >= 4, 'fontes locais declaradas');
    foreach ($m[1] as $path) {
        ok(is_file($siteDir . $path), "arquivo de fonte ausente: $path");
    }
});
test('when scanning the site, should have no analytics or pixel snippet outside the consent registry', function () use ($siteFiles, $read) {
    $banned = ['googletagmanager.com', 'google-analytics.com', 'gtag(', 'fbq(', 'connect.facebook.net', 'clarity.ms', 'hotjar', 'segment.com/analytics', 'plausible.io', 'matomo', 'ttq.load', 'snaptr('];
    foreach ($siteFiles(['html', 'js']) as $rel) {
        if ($rel === 'assets/js/trackers-config.js' || str_starts_with($rel, 'assets/vendor/')) {
            continue;
        }
        $text = $read($rel);
        foreach ($banned as $needle) {
            ok(!str_contains($text, $needle), "$rel tem '$needle' fora de trackers-config.js: só pode carregar depois do consentimento");
        }
    }
});
test('when every page loads, should include the consent banner and the cookie preferences link', function () use ($siteFiles, $read) {
    foreach ($siteFiles(['html']) as $rel) {
        $html = $read($rel);
        ok(str_contains($html, 'assets/js/consent.js'), "$rel sem banner de cookies");
        ok(str_contains($html, 'assets/js/trackers-config.js'), "$rel sem o registro de rastreadores");
        ok(str_contains($html, 'data-consent-open'), "$rel sem o link Preferências de cookies");
        ok(strpos($html, 'trackers-config.js') < strpos($html, 'consent.js'), "$rel: trackers-config.js precisa vir antes do consent.js");
    }
});
test('when reading the consent script, should give reject and accept the same button style', function () use ($read) {
    $js = $read('assets/js/consent.js');
    preg_match('/button\("Rejeitar tudo", "reject", "([^"]+)"\)/', $js, $reject);
    preg_match('/button\("Aceitar tudo", "accept", "([^"]+)"\)/', $js, $accept);
    ok(isset($reject[1], $accept[1]), 'botões Rejeitar tudo e Aceitar tudo');
    eq($accept[1], $reject[1], 'classes de Rejeitar e Aceitar');
});
test('when reading the consent script, should never treat scrolling or browsing as consent', function () use ($read) {
    $js = $read('assets/js/consent.js');
    foreach (['"scroll"', '"keydown"', '"mousemove"', '"beforeunload"', 'setTimeout'] as $needle) {
        ok(!str_contains($js, $needle), "consent.js reage a $needle: consentimento implícito não vale");
    }
    ok(substr_count($js, 'decide(') === 4, 'só os botões chamam decide() (1 definição + rejeitar, aceitar e salvar)');
});
test('when the consent script builds the banner, should not use innerHTML', function () use ($siteFiles, $read) {
    foreach (['assets/js/consent.js', 'assets/js/empresa.js'] as $rel) {
        $code = preg_replace('#/\*.*?\*/#s', '', $read($rel));
        ok(!str_contains((string) $code, 'innerHTML'), "innerHTML em $rel");
    }
});
test('when checking company data, should define the four fields in empresa.js', function () use ($read) {
    $js = $read('assets/js/empresa.js');
    foreach (['razaoSocial' => 'RAZAO_SOCIAL_DEFAULT', 'cnpj' => 'CNPJ_DEFAULT', 'endereco' => 'ENDERECO_DEFAULT', 'email' => 'EMAIL_DEFAULT'] as $field => $default) {
        ok(preg_match('/\b' . $field . ': "[^"]+"/', $js) === 1, "campo $field em empresa.js");
    }
    // o teste vale tanto com os padrões quanto com os dados reais: só exige que cada campo exista e não esteja vazio
});
test('when every page loads, should show the company identification (name, CNPJ, address, e-mail)', function () use ($siteFiles, $read) {
    foreach ($siteFiles(['html']) as $rel) {
        $html = $read($rel);
        foreach (['razaoSocial', 'cnpj', 'endereco', 'email'] as $field) {
            ok(str_contains($html, 'data-empresa="' . $field . '"'), "$rel sem o campo $field da identificação");
        }
        ok(str_contains($html, 'assets/js/empresa.js'), "$rel não carrega empresa.js");
    }
});
test('when listing the site, should have the four legal pages', function () use ($siteDir) {
    foreach (['termos', 'privacidade', 'arrependimento', 'contato'] as $page) {
        ok(is_file("$siteDir/$page/index.html"), "falta /$page/");
    }
});
test('when reading the legal pages, should stay in sync with the terms version enforced by the API', function () use ($read) {
    foreach (['termos', 'privacidade'] as $page) {
        ok(str_contains($read("$page/index.html"), 'Versão ' . Compliance::TERMS_VERSION), "/$page/ com versão diferente de Compliance::TERMS_VERSION");
    }
});
test('when reading the withdrawal page, should state 7 days and full refund', function () use ($read) {
    $html = $read('arrependimento/index.html');
    ok(str_contains($html, '7 dias') && str_contains($html, 'integral') && str_contains($html, 'art. 49'));
});
test('when reading the privacy page, should state the 6-month access log retention', function () use ($read) {
    $html = $read('privacidade/index.html');
    ok(str_contains($html, '6 meses') && str_contains($html, 'Marco Civil'));
    ok(str_contains($html, 'id="cookies"'));
});
test('when reading the checkout page, should show renewal and withdrawal terms right next to the buy button', function () use ($read) {
    $html = $read('assinaturas/index.html');
    $button = strpos($html, 'id="subscribe"');
    $notice = strpos($html, 'id="checkout-terms"');
    $cardEnd = strpos($html, '</article>', (int) $button);
    ok($button !== false && $notice !== false && $notice > $button && $notice < $cardEnd, 'aviso dentro do mesmo cartão, logo depois do botão');
    $text = substr($html, (int) $notice, 900);
    foreach (['renovação automática', 'cancele', '7 dias', 'reembolso integral'] as $needle) {
        ok(stripos($text, $needle) !== false, "aviso do checkout sem '$needle'");
    }
    ok(str_contains($html, 'id="withdraw-btn"'), 'botão de desistência no mesmo canal da compra');
});
test('when reading the sign-in page, should ask for explicit terms acceptance with an unchecked box', function () use ($read) {
    $html = $read('entrar/index.html');
    ok(str_contains($html, 'id="terms-check"') && !preg_match('/id="terms-check"[^>]*\schecked/', $html), 'caixa de aceite começa desmarcada');
    $js = $read('assets/js/entrar.js');
    ok(str_contains($js, '/terms/accept'));
    ok(!preg_match('/over18|maior de 18|tenho (mais de )?18/i', $html . $js), 'nada de autodeclaração de idade');
});
test('when reading CSP headers, should allow only our own fonts and styles', function () use ($siteDir) {
    foreach (['entrar', 'assinaturas', 'termos', 'privacidade', 'arrependimento', 'contato'] as $dir) {
        $csp = (string) file_get_contents("$siteDir/$dir/.htaccess");
        ok(str_contains($csp, "font-src 'self'") && str_contains($csp, "style-src 'self'"), "CSP de /$dir/ sem fontes e estilos só locais");
        ok(!str_contains($csp, 'googleapis.com/css') && !str_contains($csp, 'fonts.g'), "CSP de /$dir/ ainda libera fontes do Google");
    }
});
test('when checking the new pages, should have no inline script, style or handler', function () use ($read) {
    foreach (['termos', 'privacidade', 'arrependimento', 'contato'] as $page) {
        $html = $read("$page/index.html");
        ok(preg_match('/<script(?![^>]*\bsrc=)[^>]*>/i', $html) !== 1, "script inline em $page");
        ok(preg_match('/\sstyle="/i', $html) !== 1, "style inline em $page");
        ok(preg_match('/\son[a-z]+="/i', $html) !== 1, "handler inline em $page");
    }
});
