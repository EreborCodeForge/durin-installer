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
        $catalog = $this->catalog ?? new PresetCatalog();
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

    private function line(string $message): void
    {
        $stream = $this->stdout ?? STDOUT;
        fwrite($stream, $message . PHP_EOL);
    }
}
