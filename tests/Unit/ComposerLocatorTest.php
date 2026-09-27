<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Unit;

use EreborCodeForge\Durin\Installer\Composer\ComposerLocator;
use PHPUnit\Framework\TestCase;

final class ComposerLocatorTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin-composer-' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
    }

    public function testUsesComposerBinaryOverride(): void
    {
        $binary = $this->tempRoot . DIRECTORY_SEPARATOR . 'composer-fake';
        file_put_contents($binary, "#!/bin/sh\necho ok\n");
        chmod($binary, 0755);

        $locator = new ComposerLocator(
            env: ['COMPOSER_BINARY' => $binary, 'PATH' => ''],
            pathEnv: '',
            osFamily: 'Linux',
        );

        self::assertSame(realpath($binary), $locator->locate());
    }

    public function testFindsComposerOnPath(): void
    {
        $binDir = $this->tempRoot . DIRECTORY_SEPARATOR . 'bin';
        mkdir($binDir);
        $binary = $binDir . DIRECTORY_SEPARATOR . 'composer';
        file_put_contents($binary, "#!/bin/sh\necho ok\n");
        chmod($binary, 0755);

        $locator = new ComposerLocator(
            env: ['PATH' => $binDir],
            pathEnv: $binDir,
            osFamily: 'Linux',
        );

        self::assertSame(realpath($binary), $locator->locate());
    }

    public function testFailsWhenMissing(): void
    {
        $locator = new ComposerLocator(
            env: ['PATH' => $this->tempRoot],
            pathEnv: $this->tempRoot,
            osFamily: 'Linux',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Composer was not found');
        $locator->locate();
    }

    public function testFailsWhenOverrideMissing(): void
    {
        $locator = new ComposerLocator(
            env: ['COMPOSER_BINARY' => $this->tempRoot . DIRECTORY_SEPARATOR . 'missing', 'PATH' => ''],
            pathEnv: '',
            osFamily: 'Linux',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('COMPOSER_BINARY');
        $locator->locate();
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $entry;
            is_dir($full) ? $this->removeTree($full) : unlink($full);
        }

        rmdir($path);
    }
}
