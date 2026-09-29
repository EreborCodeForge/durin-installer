<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Preset;

/**
 * Discovery-only preset catalog for installer UX.
 *
 * Knows id / label / description only — never runtime rules.
 *
 * @internal
 */
final class PresetCatalog
{
    private const string DEFAULT_ID = 'minimal';

    /**
     * @var array<string, array{id: string, label: string, description: string}>
     */
    private const array PRESETS = [
        'minimal' => [
            'id' => 'minimal',
            'label' => 'Minimal',
            'description' => 'Minimal application',
        ],
        'service' => [
            'id' => 'service',
            'label' => 'Service',
            'description' => 'General-purpose structured backend service',
        ],
        'worker' => [
            'id' => 'worker',
            'label' => 'Worker',
            'description' => 'Background worker',
        ],
    ];

    public function defaultId(): string
    {
        return self::DEFAULT_ID;
    }

    public function has(string $id): bool
    {
        return isset(self::PRESETS[$id]);
    }

    /**
     * @return array{id: string, label: string, description: string}
     */
    public function get(string $id): array
    {
        if (!isset(self::PRESETS[$id])) {
            throw UnknownPresetException::forName($id, $this->ids());
        }

        return self::PRESETS[$id];
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys(self::PRESETS);
    }

    /**
     * @return list<array{id: string, label: string, description: string}>
     */
    public function all(): array
    {
        return array_values(self::PRESETS);
    }
}
