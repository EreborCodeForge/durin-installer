<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Forge;

use EreborCodeForge\Durin\Installer\Support\ExitCode;

/**
 * Finds the Forge CLI that belongs to the nearest Composer project.
 *
 * Stops at the first composer.json. Does not search PATH and does not climb
 * into a parent project when the nearest one cannot run.
 *
 * @internal
 */
final class LocalForgeLocator
{
    public const string PACKAGE = 'ereborcodeforge/durins-forge';

    public function __construct(
        private readonly ?string $launcherRealpath = null,
    ) {
    }

    public function locate(?string $cwd): ForgeLaunchTarget
    {
        if ($cwd === null || $cwd === '') {
            throw new ForgeLocateException('Unable to determine the working directory.');
        }

        $start = realpath($cwd);
        if ($start === false || !is_dir($start)) {
            throw new ForgeLocateException('Unable to determine the working directory.');
        }

        $dir = $start;
        while (true) {
            $composerPath = $dir . DIRECTORY_SEPARATOR . 'composer.json';
            if (is_file($composerPath)) {
                if ($this->containsVendorSegment($dir)) {
                    throw new ForgeLocateException($this->vendorMessage($start));
                }

                $meta = $this->readComposer($composerPath);
                if ($meta === null) {
                    throw new ForgeLocateException("composer.json at {$dir} is not valid JSON.");
                }

                $name = $meta['name'] ?? null;
                if ($name === self::PACKAGE) {
                    return $this->sourceTarget($dir, $start);
                }

                if ($this->requiresForge($meta)) {
                    return $this->consumerTarget($dir, $start);
                }

                throw new ForgeLocateException($this->notDurinMessage($start));
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        throw new ForgeLocateException($this->notDurinMessage($start));
    }

    private function sourceTarget(string $root, string $cwd): ForgeLaunchTarget
    {
        $rootReal = realpath($root);
        if ($rootReal === false) {
            throw new ForgeLocateException($this->notDurinMessage($cwd));
        }

        $this->assertOutsideVendor($rootReal, $cwd);

        $bin = $rootReal . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durins-forge';
        if (!is_file($bin)) {
            throw new ForgeLocateException("Expected local Forge entrypoint is missing:\n  {$bin}");
        }

        $autoload = $rootReal . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (!is_file($autoload)) {
            throw new ForgeLocateException(
                "durins-forge checkout at {$rootReal} has no vendor/autoload.php.\n"
                . "Run `composer install` in {$rootReal}.",
                ExitCode::ENVIRONMENT_MISSING,
            );
        }

        $resolved = realpath($bin);
        if ($resolved === false || !is_file($resolved) || !$this->samePath($resolved, $bin)) {
            throw new ForgeLocateException(
                'bin/durins-forge does not resolve inside this durins-forge checkout.',
            );
        }

        $this->assertNotLauncher($resolved);

        return new ForgeLaunchTarget($resolved, $rootReal, 'source');
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function consumerTarget(string $root, string $cwd): ForgeLaunchTarget
    {
        $rootReal = realpath($root);
        if ($rootReal === false) {
            throw new ForgeLocateException($this->notDurinMessage($cwd));
        }

        $this->assertOutsideVendor($rootReal, $cwd);

        $packageComposer = $rootReal
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'ereborcodeforge'
            . DIRECTORY_SEPARATOR . 'durins-forge'
            . DIRECTORY_SEPARATOR . 'composer.json';
        $packageBin = $rootReal
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'ereborcodeforge'
            . DIRECTORY_SEPARATOR . 'durins-forge'
            . DIRECTORY_SEPARATOR . 'bin'
            . DIRECTORY_SEPARATOR . 'durins-forge';
        $vendorBin = $rootReal
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'bin'
            . DIRECTORY_SEPARATOR . 'durins-forge';
        $autoload = $rootReal . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

        if (!is_file($autoload) || !is_file($packageComposer) || !is_file($packageBin) || !is_file($vendorBin)) {
            throw new ForgeLocateException(
                "Durin application at {$rootReal} has no installed Forge CLI.\n"
                . "Run `composer install` in {$rootReal}.",
                ExitCode::ENVIRONMENT_MISSING,
            );
        }

        $package = $this->readComposer($packageComposer);
        $packageName = is_array($package) ? ($package['name'] ?? null) : null;
        if ($packageName !== self::PACKAGE) {
            throw new ForgeLocateException(
                'vendor/ereborcodeforge/durins-forge does not identify as ' . self::PACKAGE . '.',
            );
        }

        $packageRootReal = realpath(dirname($packageBin, 2));
        $packageReal = realpath($packageBin);
        $ownedBin = ($packageRootReal !== false ? $packageRootReal : dirname($packageBin, 2))
            . DIRECTORY_SEPARATOR . 'bin'
            . DIRECTORY_SEPARATOR . 'durins-forge';
        if ($packageRootReal === false || $packageReal === false || !is_file($packageReal) || !$this->samePath($packageReal, $ownedBin)) {
            throw new ForgeLocateException(
                'Installed Forge entrypoint is not the package bin/durins-forge file.',
            );
        }

        if (!$this->vendorBinResolvesTo($vendorBin, $packageReal)) {
            throw new ForgeLocateException(
                'vendor/bin/durins-forge does not point at the installed ' . self::PACKAGE . ' package.',
            );
        }

        $this->assertNotLauncher($packageReal);

        return new ForgeLaunchTarget($packageReal, $rootReal, 'consumer');
    }

    private function vendorBinResolvesTo(string $vendorBin, string $packageReal): bool
    {
        if (!is_file($vendorBin)) {
            return false;
        }

        $vendorReal = realpath($vendorBin);
        if ($vendorReal === false || !is_file($vendorReal)) {
            return false;
        }

        if ($this->samePath($vendorReal, $packageReal)) {
            return true;
        }

        return $this->proxyPointsAt($vendorReal, $packageReal);
    }

    private function proxyPointsAt(string $proxyFile, string $packageReal): bool
    {
        $contents = file_get_contents($proxyFile);
        if ($contents === false || $contents === '') {
            return false;
        }

        foreach ($this->proxyCandidates($proxyFile, $contents) as $candidate) {
            $resolved = realpath($candidate);
            if ($resolved !== false && is_file($resolved) && $this->samePath($resolved, $packageReal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function proxyCandidates(string $proxyFile, string $contents): array
    {
        $directory = dirname($proxyFile);
        $candidates = [];

        if (preg_match_all(
            '/__DIR__\s*((?:\s*\.\s*(?:\'[^\']*\'|"[^"]*"))+)/',
            $contents,
            $matches,
        ) > 0) {
            foreach ($matches[1] as $concatenation) {
                $candidates[] = $directory . DIRECTORY_SEPARATOR . $this->joinedLiterals($concatenation);
            }
        }

        if (preg_match_all(
            '/dirname\s*\(\s*__DIR__\s*,\s*(\d+)\s*\)\s*((?:\s*\.\s*(?:\'[^\']*\'|"[^"]*"))+)/',
            $contents,
            $dirnameMatches,
            PREG_SET_ORDER,
        ) > 0) {
            foreach ($dirnameMatches as $match) {
                $base = $directory;
                $levels = (int) $match[1];
                for ($i = 0; $i < $levels; ++$i) {
                    $base = dirname($base);
                }
                $candidates[] = $base . DIRECTORY_SEPARATOR . $this->joinedLiterals($match[2]);
            }
        }

        return $candidates;
    }

    private function joinedLiterals(string $concatenation): string
    {
        preg_match_all('/\'([^\']*)\'|"([^"]*)"/', $concatenation, $parts);
        $relative = '';
        $count = count($parts[0]);
        for ($i = 0; $i < $count; ++$i) {
            $relative .= $parts[1][$i] !== '' ? $parts[1][$i] : $parts[2][$i];
        }

        return $relative;
    }

    private function assertOutsideVendor(string $root, string $cwd): void
    {
        $vendor = $root . DIRECTORY_SEPARATOR . 'vendor';
        if ($this->isInside($cwd, $vendor)) {
            throw new ForgeLocateException($this->vendorMessage($cwd));
        }
    }

    private function assertNotLauncher(string $script): void
    {
        if ($this->launcherRealpath === null || $this->launcherRealpath === '') {
            return;
        }

        $launcher = realpath($this->launcherRealpath);
        $launcher = $launcher !== false ? $launcher : $this->launcherRealpath;
        if ($this->samePath($script, $launcher)) {
            throw new ForgeLocateException(
                'Refusing to execute the global durins-forge launcher as the local target.',
            );
        }
    }

    /**
     * @param array<string, mixed> $composer
     */
    private function requiresForge(array $composer): bool
    {
        foreach (['require', 'require-dev'] as $section) {
            $requirements = $composer[$section] ?? null;
            if (is_array($requirements) && array_key_exists(self::PACKAGE, $requirements)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readComposer(string $path): ?array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function containsVendorSegment(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);
        foreach (explode('/', $normalized) as $segment) {
            if (strtolower($segment) === 'vendor') {
                return true;
            }
        }

        return false;
    }

    private function isInside(string $path, string $directory): bool
    {
        $path = $this->normalize($path);
        $directory = $this->normalize($directory);

        return $path === $directory || str_starts_with($path, $directory . '/');
    }

    private function samePath(string $left, string $right): bool
    {
        return $this->normalize($left) === $this->normalize($right);
    }

    private function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
        }

        return rtrim($path, '/');
    }

    private function notDurinMessage(string $cwd): string
    {
        return "No Durin project found starting at {$cwd}.\n"
            . "Change to a Durin application or a durins-forge checkout.\n"
            . 'Create an application with the global durin command: durin new <project>.';
    }

    private function vendorMessage(string $cwd): string
    {
        return "Refusing to run durins-forge from {$cwd} because it is inside vendor/.\n"
            . 'Run it from the application directory, outside vendor/.';
    }
}
