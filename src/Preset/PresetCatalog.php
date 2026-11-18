<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Preset;

use EreborCodeForge\Durin\Presets\Registry\DefaultPresetRegistryFactory;
use EreborCodeForge\Durin\Presets\Registry\PresetRegistry;

/**
 * Read-only adapter over the durin-presets catalog.
 * The installer does not keep its own preset IDs.
 *
 * @internal
 */
final class PresetCatalog
{
    public function __construct(
        private readonly ?PresetRegistry $registry = null,
    ) {
    }

    public function registry(): PresetRegistry
    {
        return $this->registry ?? (new DefaultPresetRegistryFactory())->create();
    }

    public function defaultId(): string
    {
        return $this->registry()->defaultId() ?? 'minimal';
    }

    public function has(string $id): bool
    {
        return $this->registry()->has($id);
    }

    /**
     * @return array{id: string, label: string, description: string}
     */
    public function get(string $id): array
    {
        if (!$this->has($id)) {
            throw UnknownPresetException::forName($id, $this->ids());
        }

        $metadata = $this->registry()->definition($id)->metadata();

        return [
            'id' => $metadata->id,
            'label' => $metadata->label,
            'description' => $metadata->description,
        ];
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return $this->registry()->ids();
    }

    /**
     * @return list<array{id: string, label: string, description: string}>
     */
    public function all(): array
    {
        $rows = [];
        foreach ($this->registry()->all() as $definition) {
            $metadata = $definition->metadata();
            $rows[] = [
                'id' => $metadata->id,
                'label' => $metadata->label,
                'description' => $metadata->description,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonCatalog(): array
    {
        return $this->registry()->catalog();
    }
}
