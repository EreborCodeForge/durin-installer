<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Unit;

use EreborCodeForge\Durin\Installer\Project\ProjectName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProjectNameTest extends TestCase
{
    #[DataProvider('validNames')]
    public function testNormalizesValidNames(string $input, string $expected): void
    {
        $name = ProjectName::fromDirectoryBasename($input);

        self::assertSame($expected, $name->slug());
        self::assertSame('app/' . $expected, $name->composerPackageName());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validNames(): iterable
    {
        yield 'simple' => ['billing-api', 'billing-api'];
        yield 'spaces' => ['Billing API', 'billing-api'];
        yield 'underscores' => ['billing_service', 'billing-service'];
        yield 'alphanumeric' => ['Payments2', 'payments2'];
        yield 'collapse-hyphens' => ['billing--api', 'billing-api'];
    }

    public function testRejectsInvalidOnlySymbols(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ProjectName::fromDirectoryBasename('!!!');
    }

    public function testNormalizeReturnsNullForInvalid(): void
    {
        self::assertNull(ProjectName::normalize('---'));
        self::assertNull(ProjectName::normalize(''));
        self::assertNull(ProjectName::normalize('@@@'));
    }
}
