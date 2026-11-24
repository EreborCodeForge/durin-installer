<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Forge;

use EreborCodeForge\Durin\Installer\Support\ExitCode;

/**
 * @internal
 */
final class ForgeLocateException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = ExitCode::GENERIC_FAILURE,
    ) {
        parent::__construct($message);
    }

    public function exitCode(): int
    {
        return $this->statusCode;
    }
}
