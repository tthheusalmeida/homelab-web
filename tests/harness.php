<?php
declare(strict_types=1);

// Mini framework de testes (sem Composer). Nome de cada teste: "when <situação>, should <resultado>".
require __DIR__ . '/../src/bootstrap.php';

use Homelab\HttpClient;

final class TestFailure extends Exception
{
}

/** @var array<int,array{string,callable}> */
$GLOBALS['__tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$name, $fn];
}

function eq(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new TestFailure(($msg !== '' ? $msg . ': ' : '') . 'esperado ' . json_encode($expected) . ' mas veio ' . json_encode($actual));
    }
}

function ok(bool $cond, string $msg = 'esperava verdadeiro'): void
{
    if (!$cond) {
        throw new TestFailure($msg);
    }
}

function throws(string $class, callable $fn): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return;
        }
        throw new TestFailure("esperava $class mas veio " . get_class($e) . ': ' . $e->getMessage());
    }
    throw new TestFailure("esperava $class, mas nada foi lançado");
}

/** HTTP falso: respostas por trecho da URL; guarda as chamadas para conferir. */
final class FakeHttp implements HttpClient
{
    /** @var array<int,array{string,callable}> */
    private array $handlers = [];
    /** @var array<int,array{method:string,url:string,headers:array<int,string>,body:?string}> */
    public array $calls = [];

    public function on(string $urlPart, callable $handler): void
    {
        array_unshift($this->handlers, [$urlPart, $handler]);
    }

    /** @param array<string,mixed>|string $body */
    public function reply(string $urlPart, int $status, array|string $body): void
    {
        $this->on($urlPart, static fn (): array => ['status' => $status, 'body' => is_array($body) ? (string) json_encode($body) : $body]);
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 10): array
    {
        $this->calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        foreach ($this->handlers as [$part, $handler]) {
            if (str_contains($url, $part)) {
                return $handler($method, $url, $headers, $body);
            }
        }
        throw new RuntimeException('chamada de rede inesperada nos testes: ' . $url);
    }

    public function countCalls(string $urlPart): int
    {
        return count(array_filter($this->calls, static fn (array $c): bool => str_contains($c['url'], $urlPart)));
    }
}

function runTests(): int
{
    $failed = 0;
    foreach ($GLOBALS['__tests'] as [$name, $fn]) {
        try {
            $fn();
            echo "  ok    $name\n";
        } catch (Throwable $e) {
            $failed++;
            echo "  FALHA $name\n        " . $e->getMessage() . "\n";
        }
    }
    $total = count($GLOBALS['__tests']);
    echo "\n" . ($total - $failed) . " de $total testes passaram.\n";
    return $failed === 0 ? 0 : 1;
}
