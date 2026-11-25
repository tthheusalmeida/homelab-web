<?php
declare(strict_types=1);

// Gera o par Ed25519 que assina a resposta de /api/me. Rode UMA vez, localmente.
require __DIR__ . '/../src/bootstrap.php';
$pair = Homelab\Signer::generate();
echo "PUBLIC (vai embutida no app):   {$pair['publicKey']}\n";
echo "SECRET (vira SIGNING_SECRET_KEY, só no servidor): {$pair['secretKey']}\n";
