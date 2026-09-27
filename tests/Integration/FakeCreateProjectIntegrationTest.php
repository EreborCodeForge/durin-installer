<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Integration;

use EreborCodeForge\Durin\Installer\Application;
use EreborCodeForge\Durin\Installer\Composer\ComposerLocator;
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

    public function testNewOrchestratesCreateCustomizeAndDoctor(): void
    {
        $runner = new RecordingProcessRunner(function (array $command, ?string $cwd) {
            if (($command[1] ?? null) === 'create-project') {
                $target = $command[3] ?? '';
                $this->scaffoldFakeApp($target);

                return new ProcessResult(0, "Created\n", '');
            }

            if (($command[1] ?? null) === 'doctor') {
                self::assertNotNull($cwd);
                self::assertFileExists($cwd . DIRECTORY_SEPARATOR . '.env');

                return new ProcessResult(0, "OK\n", '');
            }

            return new ProcessResult(1, '', 'unexpected command');
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

        $yaml = (string) file_get_contents($created . DIRECTORY_SEPARATOR . 'durin.yaml');
        self::assertMatchesRegularExpression('/^  name: smoke-app$/m', $yaml);
        self::assertStringContainsString('modules: false', $yaml);

        $env = (string) file_get_contents($created . DIRECTORY_SEPARATOR . '.env');
        self::assertMatchesRegularExpression('/^APP_NAME=smoke-app$/m', $env);

        self::assertCount(2, $runner->commands);
        self::assertSame('create-project', $runner->commands[0][1]);
        self::assertSame(AppPackage::createProjectArgument(), $runner->commands[0][2]);
        self::assertSame($created, $runner->commands[0][3]);
        self::assertSame('doctor', $runner->commands[1][1]);

        rewind($stdout);
        $out = stream_get_contents($stdout) ?: '';
        self::assertStringContainsString('Creating application via Composer create-project...', $out);
        self::assertStringContainsString('Customizing application identity...', $out);
        self::assertStringContainsString('Running Durin Doctor...', $out);
        self::assertStringContainsString('Durin application created: smoke-app', $out);
        self::assertStringContainsString('Path:' . PHP_EOL . '  ' . $created, $out);
        self::assertStringContainsString('Next:' . PHP_EOL . '  cd ' . $created, $out);
        self::assertStringContainsString('vendor/bin/durin doctor', $out);
        self::assertStringContainsString('vendor/bin/forge server:install', $out);
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

/**
 * @internal
 */
final class RecordingProcessRunner implements ProcessRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    /**
     * @param callable(list<string>, ?string): ProcessResult $handler
     */
    public function __construct(private $handler)
    {
    }

    public function run(array $command, ?string $cwd = null): ProcessResult
    {
        $this->commands[] = $command;

        return ($this->handler)($command, $cwd);
    }
}
