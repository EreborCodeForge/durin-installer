<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Process;

/**
 * @internal
 */
final readonly class ProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }
}
