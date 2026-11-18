<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Command;

use EreborCodeForge\Durin\Installer\Preset\PresetCatalog;
use EreborCodeForge\Durin\Installer\Support\ExitCode;

/**
 * @internal
 */
final class PresetsCommand
{
    public function __construct(
        private readonly mixed $stdout = null,
        private readonly mixed $stderr = null,
        private readonly ?PresetCatalog $catalog = null,
    ) {
    }

    /**
     * @param list<string> $args
     */
    public function run(array $args): int
    {
        $format = 'text';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--format=')) {
                $format = substr($arg, strlen('--format='));
            } elseif ($arg === '--format' || str_starts_with($arg, '--')) {
                $this->error('Usage: durin presets [--format=text|json]');

                return ExitCode::INVALID_USAGE;
            }
        }
        if ($format !== 'text' && $format !== 'json') {
            $this->error('Unknown --format. Use text or json.');

            return ExitCode::INVALID_USAGE;
        }

        $catalog = $this->catalog ?? new PresetCatalog();
        if ($format === 'json') {
            $this->line(json_encode(
                $catalog->jsonCatalog(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            return ExitCode::SUCCESS;
        }

        $presets = $catalog->all();

        $this->line('Available Durin presets');
        $this->line('');

        $width = 0;
        foreach ($presets as $row) {
            $width = max($width, strlen($row['id']));
        }

        foreach ($presets as $row) {
            $this->line('  ' . str_pad($row['id'], $width + 2) . $row['description']);
        }

        $this->line('');
        $this->line('Default: ' . $catalog->defaultId());

        return ExitCode::SUCCESS;
    }

    private function error(string $message): void
    {
        $stream = $this->stderr ?? STDERR;
        fwrite($stream, $message . PHP_EOL);
    }

    private function line(string $message): void
    {
        $stream = $this->stdout ?? STDOUT;
        fwrite($stream, $message . PHP_EOL);
    }
}
