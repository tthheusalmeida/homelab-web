<?php
declare(strict_types=1);

// Gera o config.php a partir de variáveis de ambiente (no deploy: secrets do GitHub; local: .env.local).
// Uso: php tools/build-config.php <destino> [arquivo.env]
$dest = $argv[1] ?? null;
if ($dest === null) {
    fwrite(STDERR, "uso: php tools/build-config.php <destino> [arquivo.env]\n");
    exit(2);
}
if (isset($argv[2])) {
    foreach (file($argv[2], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if ($line[0] !== '#' && str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            putenv(trim($k) . '=' . trim($v, " \t\"'"));
        }
    }
}
function need(string $name): string
{
    $v = getenv($name);
    if ($v === false || $v === '') {
        fwrite(STDERR, "variável ausente: $name\n");
        exit(1);
    }
    return $v;
}
$sa = json_decode((string) base64_decode(need('FIREBASE_SERVICE_ACCOUNT_B64'), true), true);
if (!is_array($sa) || !isset($sa['client_email'], $sa['private_key'])) {
    fwrite(STDERR, "FIREBASE_SERVICE_ACCOUNT_B64 inválida\n");
    exit(1);
}
$siteUrl = rtrim(need('SITE_URL'), '/');
$config = [
    'siteUrl' => $siteUrl,
    'allowedOrigins' => array_values(array_filter(array_map('trim', explode(',', getenv('ALLOWED_ORIGINS') ?: $siteUrl)))),
    'firebase' => [
        'projectId' => need('FIREBASE_PROJECT_ID'),
        'serviceAccount' => ['client_email' => $sa['client_email'], 'private_key' => $sa['private_key']],
    ],
    'stripe' => [
        'secretKey' => need('STRIPE_SECRET_KEY'),
        'webhookSecret' => need('STRIPE_WEBHOOK_SECRET'),
        'proPriceActive' => need('STRIPE_PRO_PRICE_ACTIVE'),
        'proPricesAccepted' => array_values(array_filter(array_map('trim', explode(',', getenv('STRIPE_PRO_PRICES_ACCEPTED') ?: '')))),
        // true só depois de cadastrar a URL dos Termos no painel do Stripe (senão o checkout falha)
        'requireTermsConsent' => getenv('STRIPE_REQUIRE_TERMS_CONSENT') === 'true',
    ],
    // true só quando houver um método de verificação de idade em produção (ver docs/conformidade.md)
    'compliance' => ['requireAgeVerification' => getenv('REQUIRE_AGE_VERIFICATION') === 'true'],
    'signing' => ['secretKey' => need('SIGNING_SECRET_KEY')],
];
$dir = dirname($dest);
if (!is_dir($dir)) {
    mkdir($dir, 0700, true);
}
file_put_contents($dest, "<?php\nreturn " . var_export($config, true) . ";\n");
chmod($dest, 0600);
echo "config gerado em $dest (permissão 600)\n";
