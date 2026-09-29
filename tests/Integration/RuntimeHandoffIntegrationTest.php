<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Integration;

use EreborCodeForge\Durin\Installer\Application;
use EreborCodeForge\Durin\Installer\Composer\ComposerLocator;
use EreborCodeForge\Durin\Installer\Process\ProcessObserver;
use EreborCodeForge\Durin\Installer\Process\ProcessResult;
use EreborCodeForge\Durin\Installer\Process\ProcessRunner;
use EreborCodeForge\Durin\Installer\Support\ExitCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuntimeHandoffIntegrationTest extends TestCase
{
    private string $tempRoot;
    private string $composerBinary;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin-handoff-' . bin2hex(random_bytes(4));
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
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function completePayloadProvider(): iterable
    {
        yield 'worker without supervisor' => [
            'worker',
            '{"type":"complete","preset":"worker","runtime":{"mode":"job","execution":"mithril-job","supervisor":null}}',
            'mithril-job',
            'none',
        ];
        yield 'worker with supervisor' => [
            'worker',
            '{"type":"complete","preset":"worker","runtime":{"mode":"job","execution":"mithril-job","supervisor":"eregion"}}',
            'mithril-job',
            'eregion',
        ];
        yield 'http with eregion' => [
            'service',
            '{"type":"complete","preset":"service","runtime":{"mode":"http","execution":"mithril-http","supervisor":"eregion"}}',
            'mithril-http',
            'eregion',
        ];
        yield 'unknown execution without inference' => [
            'worker',
            '{"type":"complete","preset":"worker","runtime":{"mode":"job","execution":"custom-runtime-x","supervisor":null}}',
            'custom-runtime-x',
            'none',
        ];
    }

    #[DataProvider('completePayloadProvider')]
    public function testSuccessDisplaysRuntimeFromForgePayload(
        string $preset,
        string $completeLine,
        string $expectedRuntime,
        string $expectedSupervisor,
    ): void {
        [$stdout, $stderr, $code] = $this->runNew($preset, $completeLine . "\n");

        self::assertSame(ExitCode::SUCCESS, $code, $stderr);
        self::assertStringContainsString("Preset: {$preset}", $stdout);
        self::assertStringContainsString("Runtime: {$expectedRuntime}", $stdout);
        self::assertStringContainsString("Supervisor: {$expectedSupervisor}", $stdout);
        self::assertStringNotContainsString('Runner:', $stdout);
    }

    public function testMissingRuntimePayloadFailsClearly(): void
    {
        [$stdout, $stderr, $code] = $this->runNew(
            'worker',
            "{\"type\":\"complete\",\"preset\":\"worker\"}\n",
        );

        self::assertSame(ExitCode::POST_CREATE_VALIDATION_FAILURE, $code);
        self::assertStringContainsString('Preset: worker', $stderr);
        self::assertStringContainsString('Stage: complete', $stderr);
        self::assertStringContainsString('Forge complete payload missing required runtime', $stderr);
        self::assertStringNotContainsString('Runtime:', $stdout);
    }

    public function testLegacyRunnerPayloadDoesNotFallBackToEregion(): void
    {
        [$stdout, $stderr, $code] = $this->runNew(
            'minimal',
            "{\"type\":\"complete\",\"preset\":\"minimal\",\"runner\":\"eregion\"}\n",
        );

        self::assertSame(ExitCode::POST_CREATE_VALIDATION_FAILURE, $code);
        self::assertStringContainsString('Stage: complete', $stderr);
        self::assertStringNotContainsString('Supervisor: eregion', $stdout);
        self::assertStringNotContainsString('Runner: eregion', $stdout);
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    private function runNew(string $preset, string $completeJsonl): array
    {
        $runner = new class ($this, $completeJsonl) implements ProcessRunner {
            public function __construct(
                private RuntimeHandoffIntegrationTest $test,
                private string $completeJsonl,
            ) {
            }

            public function run(array $command, ?string $cwd = null, ?ProcessObserver $observer = null): ProcessResult
            {
                $observer?->onStart();

                if (($command[1] ?? null) === 'create-project') {
                    $this->test->scaffoldFakeApp($command[3] ?? '');
                    $result = new ProcessResult(0, "Created\n", '');
                    $observer?->onFinish($result);

                    return $result;
                }

                if (($command[1] ?? null) === 'init') {
                    $observer?->onStdout("{\"type\":\"progress\",\"stage\":\"runtime.resolve\",\"message\":\"Resolving\"}\n");
                    $observer?->onStdout($this->completeJsonl);
                    $result = new ProcessResult(0, '', '');
                    $observer?->onFinish($result);

                    return $result;
                }

                if (($command[1] ?? null) === 'doctor') {
                    $result = new ProcessResult(0, "OK\n", '');
                    $observer?->onFinish($result);

                    return $result;
                }

                $result = new ProcessResult(1, '', 'unexpected command');
                $observer?->onFinish($result);

                return $result;
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
            cwd: $this->tempRoot,
            installerRoot: $this->tempRoot . DIRECTORY_SEPARATOR . 'installer-pkg',
            interactive: false,
        );

        $name = 'app-' . bin2hex(random_bytes(3));
        $code = $app->run(['durin', 'new', $name, '--preset=' . $preset]);

        rewind($stdout);
        rewind($stderr);

        return [
            stream_get_contents($stdout) ?: '',
            stream_get_contents($stderr) ?: '',
            $code,
        ];
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
  preset: uninitialized

architecture:
  modules: false

YAML);

        file_put_contents($target . DIRECTORY_SEPARATOR . '.env.example', "APP_NAME=durin-app\nAPP_ENV=development\n");
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
