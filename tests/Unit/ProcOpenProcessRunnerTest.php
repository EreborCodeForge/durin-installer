<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Unit;

use EreborCodeForge\Durin\Installer\Process\ProcOpenProcessRunner;
use PHPUnit\Framework\TestCase;

final class ProcOpenProcessRunnerTest extends TestCase
{
    public function testLargeStderrDoesNotDeadlock(): void
    {
        $runner = new ProcOpenProcessRunner();
        $payloadSize = 200_000;

        $result = $runner->run([
            PHP_BINARY,
            '-r',
            'fwrite(STDERR, str_repeat("x", ' . $payloadSize . ')); fwrite(STDOUT, "ok");',
        ]);

        self::assertSame(0, $result->exitCode);
        self::assertSame('ok', $result->stdout);
        self::assertSame($payloadSize, strlen($result->stderr));
    }

    public function testLiveOutputIsStreamedWhileCapturing(): void
    {
        $liveOut = fopen('php://memory', 'r+');
        $liveErr = fopen('php://memory', 'r+');
        self::assertIsResource($liveOut);
        self::assertIsResource($liveErr);

        $runner = new ProcOpenProcessRunner(liveStdout: $liveOut, liveStderr: $liveErr);

        $result = $runner->run([
            PHP_BINARY,
            '-r',
            'fwrite(STDOUT, "out-"); fwrite(STDERR, "err-"); fwrite(STDOUT, "done");',
        ]);

        self::assertSame(0, $result->exitCode);
        self::assertSame('out-done', $result->stdout);
        self::assertSame('err-', $result->stderr);

        rewind($liveOut);
        rewind($liveErr);
        self::assertSame('out-done', stream_get_contents($liveOut) ?: '');
        self::assertSame('err-', stream_get_contents($liveErr) ?: '');
    }
}
