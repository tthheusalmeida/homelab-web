<?php
declare(strict_types=1);

// Lista os dados da empresa que ainda estão como <DADO>_DEFAULT no site (ver site/assets/js/empresa.js).
// Uso: php tools/check-company.php   (sai com erro se houver algum; o deploy roda isto antes de enviar)
$root = dirname(__DIR__) . '/site';
$found = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (!preg_match('/\.(html|js|css|txt|xml)$/i', $file->getFilename())) {
        continue;
    }
    $text = (string) file_get_contents($file->getPathname());
    if (preg_match_all('/\b[A-Z][A-Z_]*_DEFAULT\b/', $text, $m) > 0) {
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        foreach (array_unique($m[0]) as $token) {
            $found[] = "$rel: $token";
        }
    }
}
if ($found === []) {
    echo "Dados da empresa preenchidos.\n";
    exit(0);
}
fwrite(STDERR, "Ainda há dados da empresa por preencher (troque em site/assets/js/empresa.js):\n  " . implode("\n  ", $found) . "\n");
exit(1);
