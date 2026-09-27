<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Unit;

use EreborCodeForge\Durin\Installer\Support\ExitCode;
use PHPUnit\Framework\TestCase;

final class ExitCodeTest extends TestCase
{
    public function testStableSemantics(): void
    {
        self::assertSame(0, ExitCode::SUCCESS);
        self::assertSame(1, ExitCode::GENERIC_FAILURE);
        self::assertSame(2, ExitCode::INVALID_USAGE);
        self::assertSame(3, ExitCode::ENVIRONMENT_MISSING);
        self::assertSame(4, ExitCode::TARGET_CONFLICT);
        self::assertSame(5, ExitCode::CREATE_PROJECT_FAILURE);
        self::assertSame(6, ExitCode::POST_CREATE_VALIDATION_FAILURE);
    }
}
