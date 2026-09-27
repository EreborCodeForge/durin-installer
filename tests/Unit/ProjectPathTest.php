<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Unit;

use EreborCodeForge\Durin\Installer\Project\ProjectPath;
use PHPUnit\Framework\TestCase;

final class ProjectPathTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin-installer-path-' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
    }

    public function testResolvesRelativePath(): void
    {
        $path = ProjectPath::resolve('billing-api', $this->tempRoot);

        self::assertSame(
            $this->tempRoot . DIRECTORY_SEPARATOR . 'billing-api',
            $path->absolutePath(),
        );
        self::assertSame('billing-api', $path->name()->slug());
    }

    public function testResolvesAbsolutePath(): void
    {
        $absolute = $this->tempRoot . DIRECTORY_SEPARATOR . 'payments';
        $path = ProjectPath::resolve($absolute, $this->tempRoot);

        self::assertSame($absolute, $path->absolutePath());
        self::assertSame('payments', $path->name()->slug());
    }

    public function testRejectsExistingNonEmptyTarget(): void
    {
        $target = $this->tempRoot . DIRECTORY_SEPARATOR . 'existing';
        mkdir($target);
        file_put_contents($target . DIRECTORY_SEPARATOR . 'file.txt', 'x');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Target already exists');
        ProjectPath::resolve($target, $this->tempRoot);
    }

    public function testRejectsFilesystemRoot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('filesystem root');

        $root = DIRECTORY_SEPARATOR === '\\' ? 'C:\\' : '/';
        ProjectPath::resolve($root, $this->tempRoot);
    }

    public function testRejectsHomeDirectory(): void
    {
        $home = $this->tempRoot . DIRECTORY_SEPARATOR . 'home';
        mkdir($home);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dangerous path');
        ProjectPath::resolve($home, $this->tempRoot, $home);
    }

    public function testRejectsInstallerVendorDirectory(): void
    {
        $installer = $this->tempRoot . DIRECTORY_SEPARATOR . 'installer';
        $vendor = $installer . DIRECTORY_SEPARATOR . 'vendor';
        mkdir($vendor, 0777, true);

        $this->expectException(\InvalidArgumentException::class);
        ProjectPath::resolve($vendor, $this->tempRoot, null, null, $installer);
    }

    public function testDerivesNameFromNestedBasename(): void
    {
        $path = ProjectPath::resolve('apps/Billing API', $this->tempRoot);

        self::assertSame('billing-api', $path->name()->slug());
        self::assertSame(
            $this->tempRoot . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . 'Billing API',
            $path->absolutePath(),
        );
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
