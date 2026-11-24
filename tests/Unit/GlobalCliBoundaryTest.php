<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Unit;

use EreborCodeForge\Durin\Installer\Application;
use EreborCodeForge\Durin\Installer\Composer\ComposerLocator;
use EreborCodeForge\Durin\Installer\Process\ProcessResult;
use EreborCodeForge\Durin\Installer\Process\ProcessRunner;
use EreborCodeForge\Durin\Installer\Support\ExitCode;
use PHPUnit\Framework\TestCase;

final class GlobalCliBoundaryTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin-boundary-' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
    }

    public function testDurinNewDoesNotInvokeALocalRuntime(): void
    {
        $runner = new class implements ProcessRunner {
            /** @var list<list<string>> */
            public array $commands = [];

            public function run(array $command, ?string $cwd = null, ?\EreborCodeForge\Durin\Installer\Process\ProcessObserver $observer = null): ProcessResult
            {
                $this->commands[] = $command;

                return new ProcessResult(1, '', 'create failed');
            }
        };

        [$stdout, $stderr] = [fopen('php://memory', 'r+'), fopen('php://memory', 'r+')];
        $composer = $this->tempRoot . DIRECTORY_SEPARATOR . 'composer';
        file_put_contents($composer, "#!/bin/sh\nexit 0\n");
        chmod($composer, 0755);

        $app = new Application(
            processRunner: $runner,
            composerLocator: new ComposerLocator(env: ['COMPOSER_BINARY' => $composer], osFamily: 'Linux'),
            stdout: $stdout,
            stderr: $stderr,
            cwd: $this->tempRoot,
            installerRoot: $this->tempRoot . DIRECTORY_SEPARATOR . 'installer',
            interactive: false,
        );

        $code = $app->run(['durin', 'new', 'orders-api', '--preset=minimal']);

        self::assertSame(ExitCode::CREATE_PROJECT_FAILURE, $code);
        self::assertCount(1, $runner->commands);
        self::assertSame('create-project', $runner->commands[0][1]);
        self::assertStringNotContainsString('durins-forge', implode(' ', $runner->commands[0]));
    }

    public function testDurinPresetsInsideAnAppStaysOnTheInstaller(): void
    {
        $appRoot = $this->tempRoot . DIRECTORY_SEPARATOR . 'orders-api';
        $bin = $appRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        mkdir($bin, 0777, true);
        $marker = $appRoot . DIRECTORY_SEPARATOR . 'executed.txt';
        file_put_contents($bin . DIRECTORY_SEPARATOR . 'durin', "<?php\nfile_put_contents(" . var_export($marker, true) . ", 'alias');\n");
        file_put_contents($bin . DIRECTORY_SEPARATOR . 'durins-forge', "<?php\nfile_put_contents(" . var_export($marker, true) . ", 'local');\n");

        [$stdout, $stderr] = [fopen('php://memory', 'r+'), fopen('php://memory', 'r+')];
        $app = new Application(stdout: $stdout, stderr: $stderr, cwd: $appRoot);
        $code = $app->run(['durin', 'presets']);

        rewind($stdout);
        $output = stream_get_contents($stdout) ?: '';
        self::assertSame(ExitCode::SUCCESS, $code);
        self::assertStringContainsString('minimal', $output);
        self::assertFileDoesNotExist($marker);
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
