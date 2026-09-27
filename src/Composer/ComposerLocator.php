<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Composer;

/**
 * Resolves the Composer executable without shell interpolation.
 *
 * @internal
 */
final class ComposerLocator
{
    /**
     * @param array<string, string|null>|null $env
     */
    public function __construct(
        private readonly ?array $env = null,
        private readonly ?string $pathEnv = null,
        private readonly ?string $osFamily = null,
    ) {
    }

    public function locate(): string
    {
        $env = $this->env ?? $_ENV + $_SERVER;
        $override = isset($env['COMPOSER_BINARY']) ? trim((string) $env['COMPOSER_BINARY']) : '';

        if ($override !== '') {
            if (!$this->isExecutable($override)) {
                throw new \RuntimeException(
                    'COMPOSER_BINARY is set but is not an executable file: ' . $override,
                );
            }

            return $this->normalizePath($override);
        }

        $path = $this->pathEnv ?? (string) ($env['PATH'] ?? getenv('PATH') ?: '');
        $candidates = $this->candidateNames();

        foreach ($this->pathDirectories($path) as $directory) {
            foreach ($candidates as $name) {
                $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
                if ($this->isExecutable($candidate)) {
                    return $this->normalizePath($candidate);
                }
            }
        }

        throw new \RuntimeException(
            'Composer was not found on PATH. Install Composer and ensure it is available, or set COMPOSER_BINARY.',
        );
    }

    /**
     * @return list<string>
     */
    private function candidateNames(): array
    {
        $family = $this->osFamily ?? PHP_OS_FAMILY;

        if ($family === 'Windows') {
            return ['composer.bat', 'composer.cmd', 'composer', 'composer.phar'];
        }

        return ['composer', 'composer.phar'];
    }

    /**
     * @return list<string>
     */
    private function pathDirectories(string $path): array
    {
        if ($path === '') {
            return [];
        }

        $separator = ($this->osFamily ?? PHP_OS_FAMILY) === 'Windows' ? ';' : ':';
        $parts = explode($separator, $path);

        $directories = [];
        foreach ($parts as $part) {
            $trimmed = trim($part);
            if ($trimmed !== '') {
                $directories[] = $trimmed;
            }
        }

        return $directories;
    }

    private function isExecutable(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        // Host Windows cannot reliably simulate Unix execute bits for tests or .bat shims.
        if (PHP_OS_FAMILY === 'Windows' || ($this->osFamily ?? '') === 'Windows') {
            return true;
        }

        return is_executable($path);
    }

    private function normalizePath(string $path): string
    {
        $resolved = realpath($path);

        return $resolved !== false ? $resolved : $path;
    }
}
