<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Unit;

use EreborCodeForge\Durin\Installer\Forge\LocalForgeDispatcher;
use EreborCodeForge\Durin\Installer\Forge\LocalForgeLocator;
use EreborCodeForge\Durin\Installer\Forge\LocalForgeRunner;
use EreborCodeForge\Durin\Installer\Support\ExitCode;
use PHPUnit\Framework\TestCase;

final class LocalForgeRunnerTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin-run-' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
    }

    public function testArgumentsStreamsAndExitCodeArePreserved(): void
    {
        $script = $this->tempRoot . DIRECTORY_SEPARATOR . 'child.php';
        file_put_contents($script, <<<'PHP'
<?php
declare(strict_types=1);
$args = array_slice($argv, 1);
$exit = 0;
if ($args !== [] && str_starts_with($args[0], 'exit=')) {
    $exit = (int) substr(array_shift($args), 5);
}
fwrite(STDOUT, json_encode(['cwd' => getcwd(), 'argv' => $args], JSON_THROW_ON_ERROR));
fwrite(STDERR, 'err-marker');
exit($exit);
PHP);

        $cwd = $this->tempRoot . DIRECTORY_SEPARATOR . 'sub dir';
        mkdir($cwd);
        $stdout = $this->tempRoot . DIRECTORY_SEPARATOR . 'stdout.txt';
        $stderr = $this->tempRoot . DIRECTORY_SEPARATOR . 'stderr.txt';
        $runner = $this->runner($stdout, $stderr);

        $code = $runner->run(PHP_BINARY, $script, ['exit=2', 'hello world', 'a && b', '$(rm)'], $cwd);

        self::assertSame(2, $code);
        $payload = json_decode((string) file_get_contents($stdout), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['hello world', 'a && b', '$(rm)'], $payload['argv']);
        self::assertSame($this->canonical($cwd), $this->canonical((string) $payload['cwd']));
        self::assertSame('err-marker', (string) file_get_contents($stderr));
    }

    public function testChildExitCodesPassThrough(): void
    {
        $script = $this->tempRoot . DIRECTORY_SEPARATOR . 'exit.php';
        file_put_contents($script, "<?php\nexit((int) \$argv[1]);\n");
        $stdout = $this->tempRoot . DIRECTORY_SEPARATOR . 'stdout.txt';
        $stderr = $this->tempRoot . DIRECTORY_SEPARATOR . 'stderr.txt';

        foreach ([0, 1, 2] as $expected) {
            $runner = $this->runner($stdout, $stderr);
            self::assertSame($expected, $runner->run(PHP_BINARY, $script, [(string) $expected], $this->tempRoot));
        }
    }

    public function testShutdownTerminatesALongRunningChild(): void
    {
        $child = $this->tempRoot . DIRECTORY_SEPARATOR . 'sleep.php';
        $parent = $this->tempRoot . DIRECTORY_SEPARATOR . 'parent.php';
        $pidFile = $this->tempRoot . DIRECTORY_SEPARATOR . 'child.pid';
        $stdout = $this->tempRoot . DIRECTORY_SEPARATOR . 'stdout.txt';
        $stderr = $this->tempRoot . DIRECTORY_SEPARATOR . 'stderr.txt';
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $autoload = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

        file_put_contents($child, <<<'PHP'
<?php
declare(strict_types=1);
while (true) {
    usleep(200000);
}
PHP);
        file_put_contents($parent, <<<PHP
<?php
declare(strict_types=1);
require {$this->export($autoload)};
use EreborCodeForge\\Durin\\Installer\\Forge\\LocalForgeRunner;

\$stdout = fopen({$this->export($stdout)}, 'wb');
\$stderr = fopen({$this->export($stderr)}, 'wb');
\$stdin = fopen({$this->export($null)}, 'rb');
\$runner = new LocalForgeRunner(\$stdin, \$stdout, \$stderr);
\$open = new ReflectionMethod(LocalForgeRunner::class, 'open');
\$process = \$open->invoke(\$runner, PHP_BINARY, {$this->export($child)}, [], {$this->export($this->tempRoot)});
\$closed = false;
\$arm = new ReflectionMethod(LocalForgeRunner::class, 'armShutdown');
\$arm->invokeArgs(\$runner, [\$process, &\$closed]);
\$status = proc_get_status(\$process);
file_put_contents({$this->export($pidFile)}, (string) \$status['pid']);
exit(0);
PHP);

        $launch = $this->runner(
            $this->tempRoot . DIRECTORY_SEPARATOR . 'parent-out.txt',
            $this->tempRoot . DIRECTORY_SEPARATOR . 'parent-err.txt',
        );
        $code = $launch->run(PHP_BINARY, $parent, [], $this->tempRoot);

        self::assertSame(0, $code);
        $pid = (int) trim((string) file_get_contents($pidFile));
        self::assertGreaterThan(0, $pid);
        $running = true;
        for ($attempt = 0; $attempt < 20; ++$attempt) {
            $running = $this->processIsRunning($pid);
            if (!$running) {
                break;
            }
            usleep(100000);
        }
        self::assertFalse($running, 'Child PID ' . $pid . ' is still running.');
    }

    public function testDispatcherRunsTheLocalScriptFromASubdirectory(): void
    {
        $root = $this->tempRoot . DIRECTORY_SEPARATOR . 'app';
        $packageBin = $root . '/vendor/ereborcodeforge/durins-forge/bin';
        mkdir($packageBin, 0777, true);
        mkdir($root . '/vendor/bin', 0777, true);
        mkdir($root . '/src/Application', 0777, true);
        $script = $packageBin . '/durins-forge';
        file_put_contents($script, <<<'PHP'
<?php
declare(strict_types=1);
fwrite(STDOUT, json_encode(['argv' => array_slice($argv, 1), 'cwd' => getcwd()], JSON_THROW_ON_ERROR));
exit(0);
PHP);
        file_put_contents($root . '/vendor/ereborcodeforge/durins-forge/composer.json', '{"name":"ereborcodeforge/durins-forge"}');
        file_put_contents($root . '/vendor/autoload.php', "<?php\n");
        file_put_contents($root . '/composer.json', '{"name":"app/app","require":{"ereborcodeforge/durins-forge":"^0.4"}}');
        file_put_contents(
            $root . '/vendor/bin/durins-forge',
            "<?php\nreturn include __DIR__ . '/..'.'/ereborcodeforge/durins-forge/bin/durins-forge';\n",
        );

        $cwd = $root . '/src/Application';
        $stdout = $this->tempRoot . DIRECTORY_SEPARATOR . 'dispatch-out.txt';
        $stderr = $this->tempRoot . DIRECTORY_SEPARATOR . 'dispatch-err.txt';
        $dispatcher = new LocalForgeDispatcher(
            locator: new LocalForgeLocator(),
            process: $this->runner($stdout, $stderr),
        );

        $code = $dispatcher->run(['durins-forge', 'doctor', 'hello world'], $cwd);

        self::assertSame(ExitCode::SUCCESS, $code);
        $payload = json_decode((string) file_get_contents($stdout), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['doctor', 'hello world'], $payload['argv']);
        self::assertSame($this->canonical($cwd), $this->canonical((string) $payload['cwd']));
    }

    private function runner(string $stdout, string $stderr): LocalForgeRunner
    {
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

        return new LocalForgeRunner(
            fopen($null, 'rb'),
            fopen($stdout, 'wb'),
            fopen($stderr, 'wb'),
        );
    }

    private function processIsRunning(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = [];
            exec('tasklist /FI ' . escapeshellarg('PID eq ' . $pid) . ' /NH', $output);

            return str_contains(implode("\n", $output), (string) $pid);
        }

        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        $output = [];
        exec('ps -p ' . $pid, $output);

        return count($output) > 1;
    }

    private function export(string $value): string
    {
        return var_export($value, true);
    }

    private function canonical(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        if (PHP_OS_FAMILY === 'Windows') {
            $normalized = strtolower($normalized);
        }

        return rtrim($normalized, '/');
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($path);
    }
}
