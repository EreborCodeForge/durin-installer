<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Unit;

use EreborCodeForge\Durin\Installer\Application;
use EreborCodeForge\Durin\Installer\Composer\ComposerLocator;
use EreborCodeForge\Durin\Installer\Process\ProcessResult;
use EreborCodeForge\Durin\Installer\Process\ProcessRunner;
use EreborCodeForge\Durin\Installer\Support\ExitCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NewCommandPathOutputTest extends TestCase
{
    private string $tempRoot;
    private string $composerBinary;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin-new-out-' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot);

        $this->composerBinary = $this->tempRoot . DIRECTORY_SEPARATOR . 'fake-composer';
        file_put_contents($this->composerBinary, "#!/bin/sh\nexit 0\n");
        chmod($this->composerBinary, 0755);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function projectInputProvider(): iterable
    {
        yield 'basename' => ['billing', 'billing'];
        yield 'relative nested' => ['./apps/billing', 'apps' . DIRECTORY_SEPARATOR . 'billing'];
        yield 'absolute nested' => [
            // Resolved against a dedicated absolute root in the test body.
            'ABS',
            'apps' . DIRECTORY_SEPARATOR . 'billing',
        ];
    }

    #[DataProvider('projectInputProvider')]
    public function testSuccessNextCdUsesCreatedAbsolutePath(string $inputKind, string $relativeExpected): void
    {
        $cwd = $this->tempRoot . DIRECTORY_SEPARATOR . 'cwd';
        mkdir($cwd);

        if ($inputKind === 'ABS') {
            $absoluteRoot = $this->tempRoot . DIRECTORY_SEPARATOR . 'abs-root';
            mkdir($absoluteRoot);
            $input = $absoluteRoot . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . 'billing';
            $expectedPath = $absoluteRoot . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . 'billing';
        } else {
            $input = $inputKind;
            $expectedPath = $cwd . DIRECTORY_SEPARATOR . $relativeExpected;
        }

        $runner = new class ($this) implements ProcessRunner {
            /** @var list<list<string>> */
            public array $commands = [];

            public function __construct(private NewCommandPathOutputTest $test)
            {
            }

            public function run(array $command, ?string $cwd = null): ProcessResult
            {
                $this->commands[] = $command;

                if (($command[1] ?? null) === 'create-project') {
                    $this->test->scaffoldFakeApp($command[3] ?? '');

                    return new ProcessResult(0, "Created\n", '');
                }

                if (($command[1] ?? null) === 'doctor') {
                    return new ProcessResult(0, "OK\n", '');
                }

                return new ProcessResult(1, '', 'unexpected command');
            }
        };

        [$stdout, $stderr] = [fopen('php://memory', 'r+'), fopen('php://memory', 'r+')];

        $app = new Application(
            processRunner: $runner,
            composerLocator: new ComposerLocator(
                env: ['COMPOSER_BINARY' => $this->composerBinary],
                osFamily: 'Linux',
            ),
            stdout: $stdout,
            stderr: $stderr,
            cwd: $cwd,
            installerRoot: $this->tempRoot . DIRECTORY_SEPARATOR . 'installer-pkg',
        );

        $code = $app->run(['durin', 'new', $input]);

        rewind($stderr);
        $err = stream_get_contents($stderr) ?: '';
        self::assertSame(ExitCode::SUCCESS, $code, $err);
        self::assertDirectoryExists($expectedPath);

        rewind($stdout);
        $out = stream_get_contents($stdout) ?: '';

        self::assertStringContainsString('Durin application created: billing', $out);
        self::assertStringContainsString('Path:' . PHP_EOL . '  ' . $expectedPath, $out);
        self::assertStringContainsString('Next:' . PHP_EOL . '  cd ' . $expectedPath, $out);
        self::assertDoesNotMatchRegularExpression('/^  cd billing$/m', $out);
        self::assertStringContainsString('Creating application via Composer create-project...', $out);
    }

    public function scaffoldFakeApp(string $target): void
    {
        mkdir($target . DIRECTORY_SEPARATOR . 'src', 0777, true);
        mkdir($target . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin', 0777, true);

        file_put_contents($target . DIRECTORY_SEPARATOR . 'composer.json', json_encode([
            'name' => 'ereborcodeforge/durin-app',
            'type' => 'project',
            'require' => ['php' => '^8.5'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        file_put_contents($target . DIRECTORY_SEPARATOR . 'durin.yaml', <<<'YAML'
application:
  name: durin-app
  preset: minimal

architecture:
  modules: false

YAML);

        file_put_contents($target . DIRECTORY_SEPARATOR . '.env.example', "APP_NAME=durin-app\nAPP_ENV=development\n");
        file_put_contents($target . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Kernel.php', "<?php\n");
        file_put_contents(
            $target . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durin',
            "#!/bin/sh\nexit 0\n",
        );
        chmod($target . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durin', 0755);
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
