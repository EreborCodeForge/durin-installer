<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Console;

/**
 * @internal
 */
interface ProgressRenderer
{
    public function start(string $stage, string $message): void;

    public function update(string $stage, string $message): void;

    public function succeed(string $message): void;

    public function fail(string $message): void;
}
