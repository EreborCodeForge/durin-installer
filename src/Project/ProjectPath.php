<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Project;

/**
 * Validates and normalizes the target project path.
 *
 * @internal
 */
final class ProjectPath
{
    private function __construct(
        private readonly string $absolutePath,
        private readonly ProjectName $name,
    ) {
    }

    public static function resolve(
        string $input,
        ?string $cwd = null,
        ?string $homeDirectory = null,
        ?string $composerHome = null,
        ?string $installerRoot = null,
    ): self {
        $cwd ??= getcwd() ?: throw new \RuntimeException('Unable to determine current working directory.');
        $trimmed = trim($input);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Project path must not be empty.');
        }

        $absolute = self::toAbsolutePath($trimmed, $cwd);
        $normalized = self::normalizeSeparators($absolute);

        self::assertNotDangerous($normalized, $cwd, $homeDirectory, $composerHome, $installerRoot);

        if (file_exists($normalized)) {
            throw new \RuntimeException(
                "Target already exists:\n  {$normalized}",
                4,
            );
        }

        $basename = basename($normalized);
        $name = ProjectName::fromDirectoryBasename($basename);

        return new self($normalized, $name);
    }

    public function absolutePath(): string
    {
        return $this->absolutePath;
    }

    public function name(): ProjectName
    {
        return $this->name;
    }

    public function parentDirectory(): string
    {
        return dirname($this->absolutePath);
    }

    private static function toAbsolutePath(string $path, string $cwd): string
    {
        $path = self::normalizeSeparators($path);

        if (self::isAbsolute($path)) {
            return self::collapseDots($path);
        }

        return self::collapseDots(
            rtrim(self::normalizeSeparators($cwd), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . ltrim($path, '/\\'),
        );
    }

    private static function isAbsolute(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private static function normalizeSeparators(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private static function collapseDots(string $path): string
    {
        $isWindowsDrive = preg_match('/^[A-Za-z]:/', $path) === 1;
        $prefix = '';
        $remainder = $path;

        if ($isWindowsDrive) {
            $prefix = substr($path, 0, 2);
            $remainder = substr($path, 2);
        } elseif (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $prefix = DIRECTORY_SEPARATOR;
            $remainder = substr($path, 1);
        }

        $parts = [];
        foreach (explode(DIRECTORY_SEPARATOR, $remainder) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($parts === []) {
                    continue;
                }
                array_pop($parts);
                continue;
            }
            $parts[] = $segment;
        }

        $joined = implode(DIRECTORY_SEPARATOR, $parts);

        if ($prefix === DIRECTORY_SEPARATOR) {
            return DIRECTORY_SEPARATOR . $joined;
        }

        if ($isWindowsDrive) {
            return $prefix . DIRECTORY_SEPARATOR . $joined;
        }

        return $joined === '' ? $prefix : $prefix . $joined;
    }

    private static function assertNotDangerous(
        string $path,
        string $cwd,
        ?string $homeDirectory,
        ?string $composerHome,
        ?string $installerRoot,
    ): void {
        $normalized = rtrim(self::normalizeSeparators($path), DIRECTORY_SEPARATOR);
        $parent = dirname($normalized);

        if ($normalized === '' || $normalized === DIRECTORY_SEPARATOR || preg_match('/^[A-Za-z]:$/', $normalized) === 1) {
            throw new \InvalidArgumentException('Refusing to create a project at the filesystem root.');
        }

        if ($parent === $normalized || $parent === '' || $parent === '.' || preg_match('/^[A-Za-z]:\\\\?$/', $parent) === 1 && $normalized === rtrim($parent, '\\')) {
            // parent of root-like path
        }

        $dangerous = [];

        $home = $homeDirectory ?? (getenv('HOME') ?: (getenv('USERPROFILE') ?: null));
        if (is_string($home) && $home !== '') {
            $dangerous[] = rtrim(self::normalizeSeparators($home), DIRECTORY_SEPARATOR);
        }

        $composer = $composerHome
            ?? (getenv('COMPOSER_HOME') ?: null);
        if (is_string($composer) && $composer !== '') {
            $dangerous[] = rtrim(self::normalizeSeparators($composer), DIRECTORY_SEPARATOR);
        }

        if (is_string($installerRoot) && $installerRoot !== '') {
            $dangerous[] = rtrim(self::normalizeSeparators($installerRoot), DIRECTORY_SEPARATOR);
            $dangerous[] = rtrim(self::normalizeSeparators($installerRoot), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . 'vendor';
        }

        $cwdNormalized = rtrim(self::normalizeSeparators($cwd), DIRECTORY_SEPARATOR);
        // Creating inside cwd is fine; creating AS cwd itself is dangerous.
        $dangerous[] = $cwdNormalized;

        foreach ($dangerous as $forbidden) {
            if ($forbidden !== '' && strcasecmp($normalized, $forbidden) === 0) {
                throw new \InvalidArgumentException(
                    "Refusing to create a project at a dangerous path:\n  {$normalized}",
                );
            }
        }

        $basename = basename($normalized);
        if ($basename === '' || $basename === '.' || $basename === '..') {
            throw new \InvalidArgumentException('Invalid project path basename.');
        }
    }
}
