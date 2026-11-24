<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Forge;

/**
 * Absolute local Forge entrypoint selected for the active project.
 *
 * @internal
 */
final readonly class ForgeLaunchTarget
{
    public function __construct(
        public string $script,
        public string $projectRoot,
        public string $kind,
    ) {
    }
}
