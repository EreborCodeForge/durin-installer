<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Project;

/**
 * @internal
 */
final class CreationException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $installerExitCode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $installerExitCode, $previous);
    }

    public function installerExitCode(): int
    {
        return $this->installerExitCode;
    }
}
