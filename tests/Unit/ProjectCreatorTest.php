<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Unit;

use EreborCodeForge\Durin\Installer\Composer\ComposerLocator;
use EreborCodeForge\Durin\Installer\Process\ProcessResult;
use EreborCodeForge\Durin\Installer\Process\ProcessRunner;
use EreborCodeForge\Durin\Installer\Project\CreationException;
use EreborCodeForge\Durin\Installer\Project\ProjectCreator;
use EreborCodeForge\Durin\Installer\Project\ProjectPath;
use EreborCodeForge\Durin\Installer\Support\AppPackage;
use EreborCodeForge\Durin\Installer\Support\ExitCode;
use PHPUnit\Framework\TestCase;

final class ProjectCreatorTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin-creator-' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
    }

    public function testBuildCreateProjectCommand(): void
    {
        $creator = new ProjectCreator(
            new FakeProcessRunner([]),
            new ComposerLocator(env: ['COMPOSER_BINARY' => __FILE__], osFamily: 'Linux'),
        );

        $command = $creator->buildCreateProjectCommand('/usr/bin/composer', '/tmp/billing');

        self::assertSame([
            '/usr/bin/composer',
            'create-project',
            AppPackage::createProjectArgument(),
            '/tmp/billing',
            '--no-interaction',
            '--prefer-dist',
        ], $command);
        self::assertSame('ereborcodeforge/durin-app:^0.1.1', $command[2]);
    }

    public function testCreateProjectFailurePropagatesExitCode(): void
    {
        $composer = $this->tempRoot . DIRECTORY_SEPARATOR . 'composer';
        file_put_contents($composer, "#!/bin/sh\n");
        chmod($composer, 0755);

        $runner = new FakeProcessRunner([
            new ProcessResult(2, '', 'create-project boom'),
        ]);

        $creator = new ProjectCreator(
            $runner,
            new ComposerLocator(env: ['COMPOSER_BINARY' => $composer], osFamily: 'Linux'),
        );

        $target = ProjectPath::resolve('billing', $this->tempRoot);

        try {
            $creator->create($target);
            self::fail('Expected CreationException');
        } catch (CreationException $e) {
            self::assertSame(ExitCode::CREATE_PROJECT_FAILURE, $e->installerExitCode());
            self::assertStringContainsString('exit code 2', $e->getMessage());
        }

        $first = $runner->commands[0] ?? [];
        self::assertSame('create-project', $first[1] ?? null);
        self::assertSame(AppPackage::createProjectArgument(), $first[2] ?? null);
        self::assertSame('--no-interaction', $first[4] ?? null);
        self::assertSame('--prefer-dist', $first[5] ?? null);
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
final class FakeProcessRunner implements ProcessRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    /**
     * @param list<ProcessResult> $results
     */
    public function __construct(private array $results)
    {
    }

    public function run(array $command, ?string $cwd = null): ProcessResult
    {
        $this->commands[] = $command;

        if ($this->results === []) {
            return new ProcessResult(0, '', '');
        }

        return array_shift($this->results);
    }
}
