<?php
declare(strict_types=1);

// Único arquivo PHP acessível pela web. Todo o resto (código, configuração, cache, logs) fica FORA de public_html.
// Produção: <raiz do domínio>/private/{src,config.php}. Local: HOMELAB_PRIVATE aponta para a pasta de desenvolvimento.
$private = getenv('HOMELAB_PRIVATE') ?: dirname(__DIR__, 2) . '/private';

try {
    require $private . '/src/bootstrap.php';
    $config = Homelab\Config::load($private . '/config.php');
    $router = Homelab\App::fromConfig($config, $private);
    $router->dispatch(Homelab\Request::fromGlobals(), time())->send();
} catch (Throwable) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo '{"error":"server_error"}'; // nunca mostra detalhes
}
