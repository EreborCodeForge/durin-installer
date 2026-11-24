<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Forge;

/**
 * Resolves the CLI PHP binary that should execute a local Forge script.
 *
 * @internal
 */
final class PhpExecutable
{
    public function resolve(?string $binary = null): string
    {
        $binary ??= PHP_BINARY;
        $base = strtolower(basename(str_replace('\\', '/', $binary)));

        if (!str_contains($base, 'php-cgi') && !str_contains($base, 'phpdbg') && !str_contains($base, 'php-fpm')) {
            return $binary;
        }

        $directory = dirname($binary);
        $names = PHP_OS_FAMILY === 'Windows' ? ['php.exe', 'php'] : ['php'];
        foreach ($names as $name) {
            $candidate = $directory . DIRECTORY_SEPARATOR . $name;
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $binary;
    }
}
