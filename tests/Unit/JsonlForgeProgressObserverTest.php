<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Unit;

use EreborCodeForge\Durin\Installer\Console\ProgressRenderer;
use EreborCodeForge\Durin\Installer\Process\JsonlForgeProgressObserver;
use EreborCodeForge\Durin\Installer\Process\ProcessResult;
use PHPUnit\Framework\TestCase;

final class JsonlForgeProgressObserverTest extends TestCase
{
    public function testParsesWorkerWithoutSupervisor(): void
    {
        $observer = $this->observer();
        $observer->onStdout(
            "{\"type\":\"complete\",\"preset\":\"worker\",\"runtime\":{\"mode\":\"job\",\"execution\":\"mithril-job\",\"supervisor\":null}}\n",
        );
        $observer->onFinish(new ProcessResult(0, '', ''));

        $payload = $observer->completePayload();
        self::assertSame('worker', $payload['preset']);
        self::assertSame('job', $payload['runtime']['mode']);
        self::assertSame('mithril-job', $payload['runtime']['execution']);
        self::assertNull($payload['runtime']['supervisor']);
        self::assertFalse($observer->sawInvalidComplete());
    }

    public function testParsesWorkerWithSupervisor(): void
    {
        $observer = $this->observer();
        $observer->onStdout(
            "{\"type\":\"complete\",\"preset\":\"worker\",\"runtime\":{\"mode\":\"job\",\"execution\":\"mithril-job\",\"supervisor\":\"eregion\"}}\n",
        );
        $observer->onFinish(new ProcessResult(0, '', ''));

        $payload = $observer->completePayload();
        self::assertSame('mithril-job', $payload['runtime']['execution']);
        self::assertSame('eregion', $payload['runtime']['supervisor']);
    }

    public function testParsesHttpWithEregion(): void
    {
        $observer = $this->observer('service');
        $observer->onStdout(
            "{\"type\":\"complete\",\"preset\":\"service\",\"runtime\":{\"mode\":\"http\",\"execution\":\"mithril-http\",\"supervisor\":\"eregion\"}}\n",
        );
        $observer->onFinish(new ProcessResult(0, '', ''));

        $payload = $observer->completePayload();
        self::assertSame('http', $payload['runtime']['mode']);
        self::assertSame('mithril-http', $payload['runtime']['execution']);
        self::assertSame('eregion', $payload['runtime']['supervisor']);
    }

    public function testUnknownExecutionIsPreservedWithoutInference(): void
    {
        $observer = $this->observer();
        $observer->onStdout(
            "{\"type\":\"complete\",\"preset\":\"worker\",\"runtime\":{\"mode\":\"job\",\"execution\":\"custom-runtime-x\",\"supervisor\":null}}\n",
        );
        $observer->onFinish(new ProcessResult(0, '', ''));

        self::assertSame('custom-runtime-x', $observer->completePayload()['runtime']['execution']);
    }

    public function testMissingRuntimeMarksInvalidAndLeavesPayloadEmpty(): void
    {
        $observer = $this->observer();
        $observer->onStdout("{\"type\":\"complete\",\"preset\":\"worker\"}\n");
        $observer->onFinish(new ProcessResult(0, '', ''));

        self::assertSame([], $observer->completePayload());
        self::assertTrue($observer->sawInvalidComplete());
    }

    public function testLegacyRunnerPayloadIsRejectedWithoutEregionFallback(): void
    {
        $observer = $this->observer();
        $observer->onStdout("{\"type\":\"complete\",\"preset\":\"minimal\",\"runner\":\"eregion\"}\n");
        $observer->onFinish(new ProcessResult(0, '', ''));

        self::assertSame([], $observer->completePayload());
        self::assertTrue($observer->sawInvalidComplete());
    }

    public function testIncompleteRuntimeFieldsAreRejected(): void
    {
        $observer = $this->observer();
        $observer->onStdout(
            "{\"type\":\"complete\",\"preset\":\"worker\",\"runtime\":{\"mode\":\"job\",\"supervisor\":null}}\n",
        );
        $observer->onFinish(new ProcessResult(0, '', ''));

        self::assertSame([], $observer->completePayload());
        self::assertTrue($observer->sawInvalidComplete());
    }

    private function observer(string $preset = 'worker'): JsonlForgeProgressObserver
    {
        $renderer = new class implements ProgressRenderer {
            public function start(string $stage, string $message): void
            {
            }

            public function update(string $stage, string $message): void
            {
            }

            public function succeed(string $message): void
            {
            }

            public function fail(string $message): void
            {
            }
        };

        return new JsonlForgeProgressObserver($renderer, $preset, true);
    }
}
