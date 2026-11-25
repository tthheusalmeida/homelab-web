<?php
// MODELO. O config.php de verdade é gerado no deploy (tools/build-config.php) a partir das variáveis de
// ambiente, fica FORA de public_html e NUNCA entra no Git. Em desenvolvimento local, gere com:
//   php tools/build-config.php private-dev/config.php   (lendo o arquivo .env.local, também fora do Git)
return [
    'siteUrl' => 'http://localhost:8080',
    'allowedOrigins' => ['http://localhost:8080'],
    'firebase' => [
        'projectId' => 'seu-projeto',
        'serviceAccount' => ['client_email' => '...@seu-projeto.iam.gserviceaccount.com', 'private_key' => '<private_key do JSON da conta de serviço>'],
    ],
    'stripe' => [
        'secretKey' => 'rk_test_...',          // chave RESTRITA, não a sk_
        'webhookSecret' => 'whsec_...',
        'proPriceActive' => 'price_...',       // usado em novos checkouts
        'proPricesAccepted' => ['price_...'],  // todos que valem como Pro (inclui os antigos)
    ],
    'signing' => ['secretKey' => '<base64 Ed25519>'],
];
