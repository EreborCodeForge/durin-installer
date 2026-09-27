<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Tests\Unit;

use EreborCodeForge\Durin\Installer\Project\ProjectCustomizer;
use EreborCodeForge\Durin\Installer\Project\ProjectName;
use PHPUnit\Framework\TestCase;

final class ProjectCustomizerTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        $this->fixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin-customizer-' . bin2hex(random_bytes(4));
        mkdir($this->fixture);

        file_put_contents($this->fixture . DIRECTORY_SEPARATOR . 'composer.json', json_encode([
            'name' => 'ereborcodeforge/durin-app',
            'type' => 'project',
            'require' => [
                'php' => '^8.5',
                'ereborcodeforge/mithrilphp' => '^2.2',
            ],
            'autoload' => [
                'psr-4' => ['App\\' => 'src/'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        file_put_contents($this->fixture . DIRECTORY_SEPARATOR . 'durin.yaml', <<<'YAML'
application:
  name: durin-app
  preset: minimal

runtime:
  engine: mithril
  server: eregion
  mode: http

features:
  http: true
  messaging: false

architecture:
  modules: false

YAML);

        file_put_contents($this->fixture . DIRECTORY_SEPARATOR . '.env.example', <<<'ENV'
APP_NAME=durin-app
APP_ENV=development
APP_DEBUG=true
APP_URL=http://127.0.0.1:8080
APP_PORT=8080

ENV);
    }

    protected function tearDown(): void
    {
        foreach (['composer.json', 'durin.yaml', '.env', '.env.example'] as $file) {
            $path = $this->fixture . DIRECTORY_SEPARATOR . $file;
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($this->fixture);
    }

    public function testCustomizesIdentityAndIsIdempotent(): void
    {
        $name = ProjectName::fromDirectoryBasename('billing-api');
        $customizer = new ProjectCustomizer();

        $customizer->customize($this->fixture, $name);
        $this->assertCustomized();

        $customizer->customize($this->fixture, $name);
        $this->assertCustomized();
    }

    private function assertCustomized(): void
    {
        $composer = json_decode(
            (string) file_get_contents($this->fixture . DIRECTORY_SEPARATOR . 'composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame('app/billing-api', $composer['name']);
        self::assertSame('^2.2', $composer['require']['ereborcodeforge/mithrilphp']);

        $yaml = (string) file_get_contents($this->fixture . DIRECTORY_SEPARATOR . 'durin.yaml');
        self::assertMatchesRegularExpression('/^  name: billing-api$/m', $yaml);
        self::assertStringContainsString('modules: false', $yaml);
        self::assertStringContainsString('preset: minimal', $yaml);
        self::assertSame(1, preg_match_all('/^  name:/m', $yaml));

        $env = (string) file_get_contents($this->fixture . DIRECTORY_SEPARATOR . '.env');
        self::assertMatchesRegularExpression('/^APP_NAME=billing-api$/m', $env);
        self::assertStringContainsString('APP_ENV=development', $env);
    }
}
