<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Unit;

use EreborCodeForge\Durin\Installer\Application;
use EreborCodeForge\Durin\Installer\Preset\PresetCatalog;
use EreborCodeForge\Durin\Installer\Preset\UnknownPresetException;
use EreborCodeForge\Durin\Installer\Support\ExitCode;
use PHPUnit\Framework\TestCase;

final class PresetCatalogTest extends TestCase
{
    public function testDefaultAndKnownIds(): void
    {
        $catalog = new PresetCatalog();

        self::assertSame('minimal', $catalog->defaultId());
        self::assertSame(['minimal', 'service', 'worker'], $catalog->ids());
        self::assertTrue($catalog->has('worker'));
        self::assertSame('Background worker', $catalog->get('worker')['description']);
    }

    public function testUnknownPresetThrows(): void
    {
        $catalog = new PresetCatalog();

        $this->expectException(UnknownPresetException::class);
        $this->expectExceptionMessage('Unknown preset "nope". Available: minimal, service, worker');
        $catalog->get('nope');
    }

    public function testPresetsCommandListsCatalogWithoutDurinPresetsPackage(): void
    {
        [$stdout, $stderr] = [fopen('php://memory', 'r+'), fopen('php://memory', 'r+')];
        $app = new Application(stdout: $stdout, stderr: $stderr);
        $code = $app->run(['durin', 'presets']);

        self::assertSame(ExitCode::SUCCESS, $code);
        rewind($stdout);
        $out = stream_get_contents($stdout) ?: '';
        self::assertStringContainsString('minimal', $out);
        self::assertStringContainsString('service', $out);
        self::assertStringContainsString('worker', $out);
        self::assertStringContainsString('Default: minimal', $out);
    }

    public function testComposerJsonDoesNotRequireForbiddenPackages(): void
    {
        $json = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach ([
            'ereborcodeforge/durin-core',
            'ereborcodeforge/durin-presets',
            'ereborcodeforge/durin-architecture',
            'ereborcodeforge/durin-app',
        ] as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $json['require'] ?? []);
            self::assertArrayNotHasKey($forbidden, $json['require-dev'] ?? []);
        }
    }
}
