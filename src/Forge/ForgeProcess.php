<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Forge;

/**
 * @internal
 */
interface ForgeProcess
{
    /**
     * @param list<string> $arguments
     */
    public function run(string $phpBinary, string $script, array $arguments, string $cwd): int;
}
