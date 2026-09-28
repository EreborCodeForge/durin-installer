<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Unit;

use EreborCodeForge\Durin\Installer\Application;
use EreborCodeForge\Durin\Installer\Support\ExitCode;
use EreborCodeForge\Durin\Installer\Support\InstallerVersion;
use PHPUnit\Framework\TestCase;

final class CommandParsingTest extends TestCase
{
    public function testHelpOutput(): void
    {
        [$stdout, $stderr] = $this->streams();
        $app = new Application(stdout: $stdout, stderr: $stderr);
        $code = $app->run(['durin', '--help']);

        self::assertSame(ExitCode::SUCCESS, $code);
        $out = $this->read($stdout);
        self::assertStringContainsString('Durin Installer', $out);
        self::assertStringContainsString('durin new <project>', $out);
        self::assertStringContainsString('--preset=<id>', $out);
        self::assertStringContainsString('presets', $out);
        self::assertStringContainsString('new       Create a new Durin application', $out);
        self::assertStringNotContainsString('doctor', $out);
    }

    public function testVersionOutput(): void
    {
        [$stdout, $stderr] = $this->streams();
        $app = new Application(stdout: $stdout, stderr: $stderr);
        $code = $app->run(['durin', '--version']);

        self::assertSame(ExitCode::SUCCESS, $code);
        self::assertSame(
            'Durin Installer ' . InstallerVersion::VERSION . PHP_EOL,
            $this->read($stdout),
        );
    }

    public function testHelpCommand(): void
    {
        [$stdout, $stderr] = $this->streams();
        $app = new Application(stdout: $stdout, stderr: $stderr);
        $code = $app->run(['durin', 'help']);

        self::assertSame(ExitCode::SUCCESS, $code);
        self::assertStringContainsString('Usage:', $this->read($stdout));
    }

    public function testUnknownCommand(): void
    {
        [$stdout, $stderr] = $this->streams();
        $app = new Application(stdout: $stdout, stderr: $stderr);
        $code = $app->run(['durin', 'doctor']);

        self::assertSame(ExitCode::INVALID_USAGE, $code);
        self::assertStringContainsString('Unknown command: doctor', $this->read($stderr));
    }

    public function testNewWithoutProject(): void
    {
        [$stdout, $stderr] = $this->streams();
        $app = new Application(stdout: $stdout, stderr: $stderr);
        $code = $app->run(['durin', 'new']);

        self::assertSame(ExitCode::INVALID_USAGE, $code);
        self::assertStringContainsString('Missing project name', $this->read($stderr));
    }

    /**
     * @return array{resource, resource}
     */
    private function streams(): array
    {
        return [fopen('php://memory', 'r+'), fopen('php://memory', 'r+')];
    }

    /**
     * @param resource $stream
     */
    private function read($stream): string
    {
        rewind($stream);

        return stream_get_contents($stream) ?: '';
    }
}
