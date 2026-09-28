<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Console;

/**
 * @internal
 */
final class TerminalCapabilities
{
    public static function isInteractiveTty(mixed $stream = null): bool
    {
        $stream ??= STDOUT;
        if (!is_resource($stream)) {
            return false;
        }

        if (function_exists('stream_isatty') && @stream_isatty($stream)) {
            return true;
        }

        return getenv('CI') === false || getenv('CI') === '' || getenv('CI') === '0';
    }

    public static function supportsUnicode(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $cp = getenv('LANG') ?: getenv('LC_ALL') ?: '';

            return str_contains(strtolower((string) $cp), 'utf')
                || getenv('WT_SESSION') !== false
                || getenv('TERM_PROGRAM') === 'vscode';
        }

        return true;
    }
}
