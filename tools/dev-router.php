<?php
// Roteador do servidor embutido do PHP (SÓ desenvolvimento): /api/* vai para a API, o resto serve o site.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (str_starts_with($path, '/api')) {
    require __DIR__ . '/../api/index.php';
    return true;
}
return false; // o php -S entrega o arquivo estático (ou index.html da pasta)
