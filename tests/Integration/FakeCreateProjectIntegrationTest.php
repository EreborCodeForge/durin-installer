<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Integration;

use EreborCodeForge\Durin\Installer\Application;
use EreborCodeForge\Durin\Installer\Composer\ComposerLocator;
use EreborCodeForge\Durin\Installer\Process\ProcessObserver;
use EreborCodeForge\Durin\Installer\Process\ProcessResult;
use EreborCodeForge\Durin\Installer\Process\ProcessRunner;
use EreborCodeForge\Durin\Installer\Support\AppPackage;
use EreborCodeForge\Durin\Installer\Support\ExitCode;
use PHPUnit\Framework\TestCase;

final class FakeCreateProjectIntegrationTest extends TestCase
{
    private string $tempRoot;
    private string $composerBinary;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin-int-' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot);

        $this->composerBinary = $this->tempRoot . DIRECTORY_SEPARATOR . 'fake-composer';
        file_put_contents($this->composerBinary, "#!/bin/sh\nexit 0\n");
        chmod($this->composerBinary, 0755);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
    }

    public function testNewOrchestratesCreateCustomizeInitAndDoctor(): void
    {
        $runner = new RecordingProcessRunner(function (array $command, ?string $cwd, ?ProcessObserver $observer) {
            $observer?->onStart();

            if (($command[1] ?? null) === 'create-project') {
                $target = $command[3] ?? '';
                $this->scaffoldFakeApp($target);
                $result = new ProcessResult(0, "Created\n", '');
                $observer?->onFinish($result);

                return $result;
            }

            if (($command[1] ?? null) === 'init') {
                $observer?->onStdout("{\"type\":\"progress\",\"stage\":\"scaffold.apply\",\"message\":\"Applying scaffold\"}\n");
                $observer?->onStdout("{\"type\":\"complete\",\"preset\":\"minimal\",\"runtime\":{\"mode\":\"http\",\"execution\":\"mithril-http\",\"supervisor\":\"eregion\"}}\n");
                $result = new ProcessResult(0, '', '');
                $observer?->onFinish($result);

                return $result;
            }

            if (($command[1] ?? null) === 'doctor') {
                self::assertNotNull($cwd);
                self::assertFileExists($cwd . DIRECTORY_SEPARATOR . '.env');
                $result = new ProcessResult(0, "OK\n", '');
                $observer?->onFinish($result);

                return $result;
            }

            $result = new ProcessResult(1, '', 'unexpected command');
            $observer?->onFinish($result);

            return $result;
        });

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

        $code = $app->run(['durin', 'new', 'smoke-app']);

        self::assertSame(ExitCode::SUCCESS, $code);

        $created = $this->tempRoot . DIRECTORY_SEPARATOR . 'smoke-app';
        self::assertDirectoryExists($created);

        $composer = json_decode(
            (string) file_get_contents($created . DIRECTORY_SEPARATOR . 'composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame('app/smoke-app', $composer['name']);

        // Installer must not mutate durin.yaml.
        $yaml = (string) file_get_contents($created . DIRECTORY_SEPARATOR . 'durin.yaml');
        self::assertStringContainsString('name: durin-app', $yaml);

        $env = (string) file_get_contents($created . DIRECTORY_SEPARATOR . '.env');
        self::assertMatchesRegularExpression('/^APP_NAME=smoke-app$/m', $env);

        self::assertCount(3, $runner->commands);
        self::assertSame('create-project', $runner->commands[0][1]);
        self::assertSame(AppPackage::createProjectArgument(), $runner->commands[0][2]);
        self::assertSame($created, $runner->commands[0][3]);
        self::assertSame('init', $runner->commands[1][1]);
        self::assertSame('--preset=minimal', $runner->commands[1][2]);
        self::assertSame('doctor', $runner->commands[2][1]);

        rewind($stdout);
        $out = stream_get_contents($stdout) ?: '';
        self::assertStringContainsString('Preparing project...', $out);
        self::assertStringContainsString('Created smoke-app', $out);
        self::assertStringContainsString('Path: ' . $created, $out);
        self::assertStringContainsString('Preset: minimal', $out);
        self::assertStringContainsString('Runtime: mithril-http', $out);
        self::assertStringContainsString('Supervisor: eregion', $out);
        self::assertStringNotContainsString('Runner:', $out);
        self::assertStringContainsString('cd ' . $created, $out);
    }

    public function testTargetConflictExitCode(): void
    {
        $existing = $this->tempRoot . DIRECTORY_SEPARATOR . 'taken';
        mkdir($existing);
        file_put_contents($existing . DIRECTORY_SEPARATOR . 'x', '1');

        [$stdout, $stderr] = [fopen('php://memory', 'r+'), fopen('php://memory', 'r+')];

        $app = new Application(
            processRunner: new RecordingProcessRunner(fn () => new ProcessResult(0, '', '')),
            composerLocator: new ComposerLocator(
                env: ['COMPOSER_BINARY' => $this->composerBinary],
                osFamily: 'Linux',
            ),
            stdout: $stdout,
            stderr: $stderr,
            cwd: $this->tempRoot,
            interactive: false,
        );

        $code = $app->run(['durin', 'new', 'taken']);

        self::assertSame(ExitCode::TARGET_CONFLICT, $code);
        rewind($stderr);
        self::assertStringContainsString('Target already exists', stream_get_contents($stderr) ?: '');
    }

    private function scaffoldFakeApp(string $target): void
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

/**
 * @internal
 */
final class RecordingProcessRunner implements ProcessRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    /**
     * @param callable(list<string>, ?string, ?ProcessObserver): ProcessResult $handler
     */
    public function __construct(private $handler)
    {
    }

    public function run(array $command, ?string $cwd = null, ?ProcessObserver $observer = null): ProcessResult
    {
        $this->commands[] = $command;

        return ($this->handler)($command, $cwd, $observer);
    }
}
