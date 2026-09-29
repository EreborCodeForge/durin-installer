<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Console;

/**
 * Maps Forge JSONL stage IDs to thematic Portuguese forge copy.
 *
 * @internal
 */
final class ForgeStageMessages
{
    public static function forStage(string $stage, string $preset = ''): string
    {
        return match ($stage) {
            'create.project' => 'Acendendo a forja...',
            'customize' => 'Preparando o metal...',
            'preset.resolve' => $preset !== ''
                ? 'Talhando o molde "' . $preset . '"...'
                : 'Talhando o molde...',
            'scaffold.plan' => 'Lendo o desenho da forja...',
            'scaffold.apply' => 'Martelando a estrutura...',
            'runtime.resolve' => 'Escolhendo o fogo da forja...',
            'runtime.install' => 'Instalando o runtime...',
            'runtime.configure' => 'Gravando as runas do runtime...',
            'validate' => 'Inspecionando a obra...',
            'doctor' => 'Inspecionando a obra...',
            default => 'Trabalhando na forja...',
        };
    }

    public static function plainForStage(string $stage, string $preset = ''): string
    {
        return match ($stage) {
            'create.project' => 'Preparing project...',
            'customize' => 'Customizing project identity...',
            'preset.resolve', 'scaffold.plan', 'scaffold.apply' => $preset !== ''
                ? 'Applying preset: ' . $preset
                : 'Applying preset...',
            'runtime.resolve', 'runtime.install', 'runtime.configure' => 'Installing runtime...',
            'validate', 'doctor' => 'Validating application...',
            default => 'Working...',
        };
    }
}
