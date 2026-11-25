<?php
declare(strict_types=1);

// Diagnóstico da hospedagem. Rode por SSH: php tools/check-env.php
// (CLI de propósito: nunca exponha um diagnóstico em URL pública.)
$ok = true;
function check(string $label, bool $pass, string $hint = ''): void
{
    global $ok;
    $ok = $ok && $pass;
    echo ($pass ? '[ok]   ' : '[FALHA]') . ' ' . $label . ($pass || $hint === '' ? '' : "  -> $hint") . "\n";
}
check('PHP >= 8.2 (' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.2.0', '>='), 'escolha a versão no hPanel');
foreach (['openssl', 'curl', 'sodium', 'json', 'mbstring'] as $ext) {
    check("extensão $ext", extension_loaded($ext), 'peça ao suporte ou ative no hPanel');
}
check('sodium_crypto_sign_detached', function_exists('sodium_crypto_sign_detached'));
check('allow_url_fopen/curl para HTTPS de saída', function_exists('curl_init'));
$ch = curl_init('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
curl_exec($ch);
check('saída HTTPS até o Google', curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200, 'a hospedagem bloqueia conexões de saída?');
$ch = curl_init('https://api.stripe.com/v1/prices');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
curl_exec($ch);
check('saída HTTPS até o Stripe', in_array(curl_getinfo($ch, CURLINFO_RESPONSE_CODE), [200, 401], true));
$private = getenv('HOMELAB_PRIVATE') ?: dirname(__DIR__, 2) . '/private';
check("pasta privada gravável fora de public_html ($private)", is_dir($private) && is_writable($private), 'crie a pasta e ajuste a permissão');
check('config.php com permissão 600', is_file("$private/config.php") && (fileperms("$private/config.php") & 0777) === 0600);
echo $ok ? "\nTudo certo.\n" : "\nHá itens a resolver antes do deploy.\n";
exit($ok ? 0 : 1);
