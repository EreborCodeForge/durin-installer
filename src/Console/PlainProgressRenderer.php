<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Console;

/**
 * CI / non-TTY progress lines.
 *
 * @internal
 */
final class PlainProgressRenderer implements ProgressRenderer
{
    public function __construct(
        private readonly mixed $stdout = null,
    ) {
    }

    public function start(string $stage, string $message): void
    {
        $this->write('[durin] ' . $message);
    }

    public function update(string $stage, string $message): void
    {
        $this->write('[durin] ' . $message);
    }

    public function succeed(string $message): void
    {
        $this->write('[durin] ' . $message);
    }

    public function fail(string $message): void
    {
        $this->write('[durin] ' . $message);
    }

    private function write(string $line): void
    {
        $stream = $this->stdout ?? STDOUT;
        fwrite($stream, $line . PHP_EOL);
    }
}
