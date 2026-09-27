<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Support;

/**
 * @internal
 */
final class ExitCode
{
    public const int SUCCESS = 0;
    public const int GENERIC_FAILURE = 1;
    public const int INVALID_USAGE = 2;
    public const int ENVIRONMENT_MISSING = 3;
    public const int TARGET_CONFLICT = 4;
    public const int CREATE_PROJECT_FAILURE = 5;
    public const int POST_CREATE_VALIDATION_FAILURE = 6;
}
