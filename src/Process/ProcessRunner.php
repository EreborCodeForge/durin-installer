<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Process;

/**
 * @internal
 */
interface ProcessRunner
{
    /**
     * @param list<string> $command
     */
    public function run(array $command, ?string $cwd = null): ProcessResult;
}
