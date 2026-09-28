<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Command;

use EreborCodeForge\Durin\Installer\Support\ExitCode;
use EreborCodeForge\Durin\Presets\Registry\DefaultPresetRegistryFactory;
use EreborCodeForge\Durin\Presets\Registry\PresetRegistry;

/**
 * @internal
 */
final class PresetsCommand
{
    public function __construct(
        private readonly mixed $stdout = null,
        private readonly mixed $stderr = null,
        private readonly ?PresetRegistry $registry = null,
    ) {
    }

    /**
     * @param list<string> $args
     */
    public function run(array $args): int
    {
        $registry = $this->registry ?? (new DefaultPresetRegistryFactory())->create();
        $catalog = $registry->catalog();

        $this->line('Available Durin presets');
        $this->line('');

        $width = 0;
        foreach ($catalog['presets'] as $row) {
            $width = max($width, strlen((string) $row['id']));
        }

        foreach ($catalog['presets'] as $row) {
            $id = (string) $row['id'];
            $description = (string) ($row['description'] ?? $row['label'] ?? '');
            $this->line('  ' . str_pad($id, $width + 2) . $description);
        }

        $this->line('');
        $this->line('Default: ' . ($catalog['default'] ?? $registry->default()->id()));

        return ExitCode::SUCCESS;
    }

    private function line(string $message): void
    {
        $stream = $this->stdout ?? STDOUT;
        fwrite($stream, $message . PHP_EOL);
    }
}
