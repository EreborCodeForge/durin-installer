<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Support;

/**
 * Single source of truth for the canonical application package constraint.
 *
 * @internal
 */
final class AppPackage
{
    public const string NAME = 'ereborcodeforge/durin-app';
    public const string CONSTRAINT = '^0.1.1';

    public static function createProjectArgument(): string
    {
        return self::NAME . ':' . self::CONSTRAINT;
    }
}
