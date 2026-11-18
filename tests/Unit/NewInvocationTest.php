<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Unit;

use EreborCodeForge\Durin\Installer\Preset\NewInvocation;
use PHPUnit\Framework\TestCase;

final class NewInvocationTest extends TestCase
{
    public function testHttpConsumerIsRejectedBeforeArgumentsAreForwarded(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('restricted to job presets');
        NewInvocation::parse(['orders', '--preset=service', '--with=consumer']);
    }

    public function testWorkloadFlagsRequireAPreset(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('require --preset');
        NewInvocation::parse(['orders', '--supervisor=eregion']);
    }

    public function testConsumerResourcesAreForwardedAsSeparateArguments(): void
    {
        $invocation = NewInvocation::parse([
            'mail',
            '--preset=consumer',
            '--supervisor=eregion',
            '--scaling=resources',
            '--workers-max=4',
            '--resource-class=io',
        ]);

        self::assertSame('consumer', $invocation->presetId);
        self::assertContains('--supervisor=eregion', $invocation->initArguments);
        self::assertContains('--scaling=resources', $invocation->initArguments);
        self::assertContains('--workers-max=4', $invocation->initArguments);
        self::assertSame(true, $invocation->expectedFeatures['consumer']);
        self::assertSame(false, $invocation->expectedFeatures['producer']);
    }
}
