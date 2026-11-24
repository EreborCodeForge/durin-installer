<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Unit;

use EreborCodeForge\Durin\Installer\Forge\ForgeLocateException;
use EreborCodeForge\Durin\Installer\Forge\LocalForgeLocator;
use EreborCodeForge\Durin\Installer\Forge\PhpExecutable;
use EreborCodeForge\Durin\Installer\Support\ExitCode;
use PHPUnit\Framework\TestCase;

final class LocalForgeLocatorTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin-locate-' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
    }

    public function testConsumerAppResolvesPackageEntrypoint(): void
    {
        $root = $this->tempRoot . DIRECTORY_SEPARATOR . 'orders-api';
        $script = $this->writeConsumer($root);

        $target = (new LocalForgeLocator())->locate($root);

        self::assertSame('consumer', $target->kind);
        self::assertSame($this->canonical($script), $this->canonical($target->script));
        self::assertSame($this->canonical($root), $this->canonical($target->projectRoot));
    }

    public function testNestedDirectoryKeepsTheSameProjectRoot(): void
    {
        $root = $this->tempRoot . DIRECTORY_SEPARATOR . 'orders-api';
        $script = $this->writeConsumer($root);
        $nested = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Application';
        mkdir($nested, 0777, true);

        $target = (new LocalForgeLocator())->locate($nested);

        self::assertSame($this->canonical($script), $this->canonical($target->script));
        self::assertSame($this->canonical($root), $this->canonical($target->projectRoot));
    }

    public function testNeutralAppWithoutManifestStillResolves(): void
    {
        $root = $this->tempRoot . DIRECTORY_SEPARATOR . 'neutral';
        $script = $this->writeConsumer($root);
        self::assertFileDoesNotExist($root . DIRECTORY_SEPARATOR . 'durin.yaml');

        $target = (new LocalForgeLocator())->locate($root);

        self::assertSame($this->canonical($script), $this->canonical($target->script));
    }

    public function testSourceCheckoutUsesRepositoryBin(): void
    {
        $root = $this->tempRoot . DIRECTORY_SEPARATOR . 'durins-forge';
        $script = $this->writeSourceCheckout($root);
        mkdir($root . DIRECTORY_SEPARATOR . 'src');

        $target = (new LocalForgeLocator())->locate($root . DIRECTORY_SEPARATOR . 'src');

        self::assertSame('source', $target->kind);
        self::assertSame($this->canonical($script), $this->canonical($target->script));
        self::assertStringNotContainsString(
            'vendor' . DIRECTORY_SEPARATOR . 'bin',
            $target->script,
        );
    }

    public function testSourceCheckoutWithoutAutoloadDoesNotClimbToParent(): void
    {
        $parent = $this->tempRoot . DIRECTORY_SEPARATOR . 'parent-app';
        $this->writeConsumer($parent);
        $checkout = $parent . DIRECTORY_SEPARATOR . 'nested-forge';
        $this->writeSourceCheckout($checkout, withAutoload: false);

        try {
            (new LocalForgeLocator())->locate($checkout);
            self::fail('Expected a missing autoload error.');
        } catch (ForgeLocateException $e) {
            self::assertSame(ExitCode::ENVIRONMENT_MISSING, $e->exitCode());
            self::assertStringContainsString('composer install', $e->getMessage());
            self::assertStringContainsString('nested-forge', $e->getMessage());
            self::assertStringNotContainsString('vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durins-forge', $e->getMessage());
        }
    }

    public function testInstalledAppWithoutVendorAsksForComposerInstall(): void
    {
        $root = $this->tempRoot . DIRECTORY_SEPARATOR . 'bare-app';
        mkdir($root);
        $this->writeJson($root . DIRECTORY_SEPARATOR . 'composer.json', [
            'name' => 'app/bare',
            'require' => ['ereborcodeforge/durins-forge' => '^0.4'],
        ]);

        try {
            (new LocalForgeLocator())->locate($root);
            self::fail('Expected a missing install error.');
        } catch (ForgeLocateException $e) {
            self::assertSame(ExitCode::ENVIRONMENT_MISSING, $e->exitCode());
            self::assertStringContainsString('composer install', $e->getMessage());
            self::assertStringContainsString($this->canonical($root), $this->canonical($e->getMessage()));
        }
    }

    public function testUnrelatedDirectoryDoesNotCreateOrSearchPath(): void
    {
        $root = $this->tempRoot . DIRECTORY_SEPARATOR . 'notes';
        mkdir($root);
        $this->writeJson($root . DIRECTORY_SEPARATOR . 'composer.json', [
            'name' => 'other/notes',
            'require' => ['php' => '^8.5'],
        ]);
        file_put_contents($root . DIRECTORY_SEPARATOR . 'durins-forge', "<?php\n");

        try {
            (new LocalForgeLocator())->locate($root);
            self::fail('Expected a project-not-found error.');
        } catch (ForgeLocateException $e) {
            self::assertSame(ExitCode::GENERIC_FAILURE, $e->exitCode());
            self::assertStringContainsString('durin new', $e->getMessage());
            self::assertStringNotContainsString('composer install', $e->getMessage());
        }
    }

    public function testNearestProjectBoundaryIsNotCrossed(): void
    {
        $parent = $this->tempRoot . DIRECTORY_SEPARATOR . 'forge-parent';
        $this->writeSourceCheckout($parent);
        $child = $parent . DIRECTORY_SEPARATOR . 'other';
        mkdir($child);
        $this->writeJson($child . DIRECTORY_SEPARATOR . 'composer.json', [
            'name' => 'other/tool',
        ]);

        try {
            (new LocalForgeLocator())->locate($child);
            self::fail('Expected the child project to block the parent checkout.');
        } catch (ForgeLocateException $e) {
            self::assertStringContainsString('durin new', $e->getMessage());
        }
    }

    public function testInvocationInsideVendorIsRejected(): void
    {
        $root = $this->tempRoot . DIRECTORY_SEPARATOR . 'orders-api';
        $this->writeConsumer($root);
        $inside = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'ereborcodeforge' . DIRECTORY_SEPARATOR . 'durins-forge';

        try {
            (new LocalForgeLocator())->locate($inside);
            self::fail('Expected a vendor boundary error.');
        } catch (ForgeLocateException $e) {
            self::assertStringContainsString('inside vendor/', $e->getMessage());
            self::assertStringNotContainsString('orders-api' . DIRECTORY_SEPARATOR . 'bin', $e->getMessage());
        }
    }

    public function testVendorBinDirectoryIsNotPromotedToTheAppRoot(): void
    {
        $root = $this->tempRoot . DIRECTORY_SEPARATOR . 'orders-api';
        $this->writeConsumer($root);

        try {
            (new LocalForgeLocator())->locate($root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin');
            self::fail('Expected a vendor boundary error.');
        } catch (ForgeLocateException $e) {
            self::assertStringContainsString('inside vendor/', $e->getMessage());
        }
    }

    public function testLegacyDurinAliasIsNotSelected(): void
    {
        $root = $this->tempRoot . DIRECTORY_SEPARATOR . 'orders-api';
        mkdir($root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin', 0777, true);
        file_put_contents(
            $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durin',
            "<?php\n",
        );
        file_put_contents($root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php', "<?php\n");
        $this->writeJson($root . DIRECTORY_SEPARATOR . 'composer.json', [
            'name' => 'app/orders',
            'require' => ['ereborcodeforge/durins-forge' => '^0.4'],
        ]);

        try {
            (new LocalForgeLocator())->locate($root);
            self::fail('Expected the legacy alias to be ignored.');
        } catch (ForgeLocateException $e) {
            self::assertSame(ExitCode::ENVIRONMENT_MISSING, $e->exitCode());
            self::assertStringContainsString('composer install', $e->getMessage());
        }
    }

    public function testProxyMustPointAtTheInstalledPackage(): void
    {
        $root = $this->tempRoot . DIRECTORY_SEPARATOR . 'orders-api';
        $this->writeConsumer($root, proxy: 'durin');

        try {
            (new LocalForgeLocator())->locate($root);
            self::fail('Expected a proxy identity error.');
        } catch (ForgeLocateException $e) {
            self::assertStringContainsString('does not point at the installed', $e->getMessage());
        }
    }

    public function testPackageIdentityMismatchIsRejected(): void
    {
        $root = $this->tempRoot . DIRECTORY_SEPARATOR . 'orders-api';
        $this->writeConsumer($root, packageName: 'other/forge');

        try {
            (new LocalForgeLocator())->locate($root);
            self::fail('Expected a package identity error.');
        } catch (ForgeLocateException $e) {
            self::assertStringContainsString('does not identify as', $e->getMessage());
        }
    }

    public function testTwoProjectsResolveDifferentScripts(): void
    {
        $first = $this->writeConsumer($this->tempRoot . DIRECTORY_SEPARATOR . 'app-a');
        $second = $this->writeConsumer($this->tempRoot . DIRECTORY_SEPARATOR . 'app-b');
        $locator = new LocalForgeLocator();

        $fromA = $locator->locate($this->tempRoot . DIRECTORY_SEPARATOR . 'app-a');
        $fromB = $locator->locate($this->tempRoot . DIRECTORY_SEPARATOR . 'app-b' . DIRECTORY_SEPARATOR . 'src');

        self::assertSame($this->canonical($first), $this->canonical($fromA->script));
        self::assertSame($this->canonical($second), $this->canonical($fromB->script));
        self::assertNotSame($this->canonical($fromA->script), $this->canonical($fromB->script));
    }

    public function testRecursiveLauncherIsRejected(): void
    {
        $root = $this->tempRoot . DIRECTORY_SEPARATOR . 'orders-api';
        $script = $this->writeConsumer($root);

        try {
            (new LocalForgeLocator($script))->locate($root);
            self::fail('Expected a recursion error.');
        } catch (ForgeLocateException $e) {
            self::assertStringContainsString('global durins-forge launcher', $e->getMessage());
        }
    }

    public function testPathRepositorySymlinkResolvesTheLinkedPackageBin(): void
    {
        $source = $this->tempRoot . DIRECTORY_SEPARATOR . 'forge-src';
        $script = $this->writeSourceCheckout($source);
        $app = $this->tempRoot . DIRECTORY_SEPARATOR . 'path app';
        mkdir($app . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'ereborcodeforge', 0777, true);
        mkdir($app . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin', 0777, true);
        mkdir($app . DIRECTORY_SEPARATOR . 'src', 0777, true);
        $link = $app . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'ereborcodeforge' . DIRECTORY_SEPARATOR . 'durins-forge';
        if (!@symlink($source, $link)) {
            self::markTestSkipped('symlink() is not available for a path-repository package.');
        }

        file_put_contents($app . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php', "<?php\n");
        $this->writeJson($app . DIRECTORY_SEPARATOR . 'composer.json', [
            'name' => 'app/path-app',
            'require' => ['ereborcodeforge/durins-forge' => '^0.4'],
        ]);
        file_put_contents(
            $app . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durins-forge',
            "<?php\nreturn include __DIR__ . '/..'.'/ereborcodeforge/durins-forge/bin/durins-forge';\n",
        );

        $target = (new LocalForgeLocator())->locate($app . DIRECTORY_SEPARATOR . 'src');

        self::assertSame('consumer', $target->kind);
        self::assertSame($this->canonical($script), $this->canonical($target->script));
    }

    public function testPackageBinSymlinkToAnotherProjectIsRejected(): void
    {
        $app = $this->tempRoot . DIRECTORY_SEPARATOR . 'orders-api';
        $this->writeConsumer($app);
        $other = $this->tempRoot . DIRECTORY_SEPARATOR . 'other-forge';
        $otherBin = $this->writeSourceCheckout($other);
        $packageBin = $app
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'ereborcodeforge'
            . DIRECTORY_SEPARATOR . 'durins-forge'
            . DIRECTORY_SEPARATOR . 'bin'
            . DIRECTORY_SEPARATOR . 'durins-forge';
        unlink($packageBin);
        if (!@symlink($otherBin, $packageBin)) {
            self::markTestSkipped('symlink() is not available for a foreign bin.');
        }

        try {
            (new LocalForgeLocator())->locate($app);
            self::fail('Expected a foreign package bin to be rejected.');
        } catch (ForgeLocateException $e) {
            self::assertStringContainsString('not the package bin/durins-forge file', $e->getMessage());
        }
    }

    public function testProxyPointingAtAnotherProjectIsRejected(): void
    {
        $app = $this->tempRoot . DIRECTORY_SEPARATOR . 'orders-api';
        $other = $this->tempRoot . DIRECTORY_SEPARATOR . 'other-app';
        $this->writeConsumer($app);
        $this->writeConsumer($other);
        file_put_contents(
            $app . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durins-forge',
            "<?php\nreturn include __DIR__ . '/../../../other-app/vendor/ereborcodeforge/durins-forge/bin/durins-forge';\n",
        );

        try {
            (new LocalForgeLocator())->locate($app);
            self::fail('Expected a proxy aimed at another project to be rejected.');
        } catch (ForgeLocateException $e) {
            self::assertStringContainsString('does not point at the installed', $e->getMessage());
        }
    }

    public function testInvalidComposerJsonFailsWithoutClimbing(): void
    {
        $parent = $this->tempRoot . DIRECTORY_SEPARATOR . 'forge-parent';
        $this->writeSourceCheckout($parent);
        $child = $parent . DIRECTORY_SEPARATOR . 'broken';
        mkdir($child);
        file_put_contents($child . DIRECTORY_SEPARATOR . 'composer.json', '{invalid');

        try {
            (new LocalForgeLocator())->locate($child);
            self::fail('Expected invalid composer.json to stop resolution.');
        } catch (ForgeLocateException $e) {
            self::assertStringContainsString('not valid JSON', $e->getMessage());
            self::assertStringContainsString('broken', $e->getMessage());
        }
    }

    public function testProjectPathWithSpacesResolves(): void
    {
        $root = $this->tempRoot . DIRECTORY_SEPARATOR . 'my app';
        $script = $this->writeConsumer($root);

        $target = (new LocalForgeLocator())->locate($root . DIRECTORY_SEPARATOR . 'src');

        self::assertSame($this->canonical($script), $this->canonical($target->script));
    }

    public function testWindowsJunctionPathRepositoryResolvesTheLinkedPackageBin(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            self::markTestSkipped('Junctions are a Windows filesystem feature.');
        }

        $source = $this->tempRoot . DIRECTORY_SEPARATOR . 'forge-src';
        $script = $this->writeSourceCheckout($source);
        $app = $this->tempRoot . DIRECTORY_SEPARATOR . 'junction app';
        mkdir($app . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'ereborcodeforge', 0777, true);
        mkdir($app . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin', 0777, true);
        mkdir($app . DIRECTORY_SEPARATOR . 'src', 0777, true);
        $link = $app . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'ereborcodeforge' . DIRECTORY_SEPARATOR . 'durins-forge';
        $command = 'cmd.exe /C mklink /J ' . escapeshellarg($link) . ' ' . escapeshellarg($source);
        exec($command, $output, $status);
        if ($status !== 0 || !is_dir($link)) {
            self::markTestSkipped('mklink /J is not available: ' . implode(' ', $output));
        }

        file_put_contents($app . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php', "<?php\n");
        $this->writeJson($app . DIRECTORY_SEPARATOR . 'composer.json', [
            'name' => 'app/junction-app',
            'require' => ['ereborcodeforge/durins-forge' => '^0.4'],
        ]);
        file_put_contents(
            $app . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durins-forge',
            "<?php\nreturn include __DIR__ . '/..'.'/ereborcodeforge/durins-forge/bin/durins-forge';\n",
        );

        $target = (new LocalForgeLocator())->locate($app . DIRECTORY_SEPARATOR . 'src');

        self::assertSame('consumer', $target->kind);
        self::assertSame($this->canonical($script), $this->canonical($target->script));
    }

    public function testMissingWorkingDirectoryFails(): void
    {
        $missing = $this->tempRoot . DIRECTORY_SEPARATOR . 'gone';

        try {
            (new LocalForgeLocator())->locate($missing);
            self::fail('Expected a working-directory error.');
        } catch (ForgeLocateException $e) {
            self::assertStringContainsString('working directory', $e->getMessage());
        }

        try {
            (new LocalForgeLocator())->locate(null);
            self::fail('Expected a working-directory error.');
        } catch (ForgeLocateException $e) {
            self::assertSame(ExitCode::GENERIC_FAILURE, $e->exitCode());
        }
    }

    public function testPhpCgiResolvesToNeighborCliBinary(): void
    {
        $directory = $this->tempRoot . DIRECTORY_SEPARATOR . 'php';
        mkdir($directory);
        $suffix = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
        $cgi = $directory . DIRECTORY_SEPARATOR . 'php-cgi' . $suffix;
        $cli = $directory . DIRECTORY_SEPARATOR . 'php' . $suffix;
        file_put_contents($cgi, '');
        file_put_contents($cli, '');

        self::assertSame($cli, (new PhpExecutable())->resolve($cgi));
        self::assertSame($cli, (new PhpExecutable())->resolve($cli));
    }

    private function writeConsumer(
        string $root,
        string $proxy = 'durins-forge',
        string $packageName = 'ereborcodeforge/durins-forge',
    ): string {
        $packageBin = $root
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'ereborcodeforge'
            . DIRECTORY_SEPARATOR . 'durins-forge'
            . DIRECTORY_SEPARATOR . 'bin';
        mkdir($packageBin, 0777, true);
        mkdir($root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin', 0777, true);
        mkdir($root . DIRECTORY_SEPARATOR . 'src', 0777, true);

        $script = $packageBin . DIRECTORY_SEPARATOR . 'durins-forge';
        file_put_contents($script, "<?php\ndeclare(strict_types=1);\n");
        file_put_contents($packageBin . DIRECTORY_SEPARATOR . 'durin', "<?php\ndeclare(strict_types=1);\n");
        $this->writeJson(dirname($packageBin) . DIRECTORY_SEPARATOR . 'composer.json', [
            'name' => $packageName,
        ]);
        file_put_contents($root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php', "<?php\n");
        $this->writeJson($root . DIRECTORY_SEPARATOR . 'composer.json', [
            'name' => 'app/' . basename($root),
            'require' => ['ereborcodeforge/durins-forge' => '^0.4'],
        ]);

        $include = $proxy === 'durins-forge'
            ? "/ereborcodeforge/durins-forge/bin/durins-forge"
            : "/ereborcodeforge/durins-forge/bin/durin";
        file_put_contents(
            $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durins-forge',
            "<?php\nreturn include __DIR__ . '/..'.'{$include}';\n",
        );

        $resolved = realpath($script);
        self::assertNotFalse($resolved);

        return $resolved;
    }

    private function writeSourceCheckout(string $root, bool $withAutoload = true): string
    {
        mkdir($root . DIRECTORY_SEPARATOR . 'bin', 0777, true);
        $script = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durins-forge';
        file_put_contents($script, "<?php\ndeclare(strict_types=1);\n");
        $this->writeJson($root . DIRECTORY_SEPARATOR . 'composer.json', [
            'name' => 'ereborcodeforge/durins-forge',
        ]);
        if ($withAutoload) {
            mkdir($root . DIRECTORY_SEPARATOR . 'vendor', 0777, true);
            file_put_contents($root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php', "<?php\n");
        }

        $resolved = realpath($script);
        self::assertNotFalse($resolved);

        return $resolved;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJson(string $path, array $data): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function canonical(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        if (PHP_OS_FAMILY === 'Windows') {
            $normalized = strtolower($normalized);
        }

        return $normalized;
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
            $entry = $file->getPathname();
            if ($file->isDir()) {
                rmdir($entry);

                continue;
            }
            if (!@unlink($entry)) {
                @rmdir($entry);
            }
        }

        if (is_dir($path)) {
            rmdir($path);
        }
    }
}
