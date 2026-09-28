<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Process;

/**
 * Keeps forge animation moving while Composer runs (stdout captured, not teed).
 *
 * @internal
 */
final class ComposerProgressObserver implements ProcessObserver
{
    public function __construct(
        private readonly \EreborCodeForge\Durin\Installer\Console\ProgressRenderer $renderer,
        private readonly string $message,
    ) {
    }

    public function onStart(): void
    {
        $this->renderer->start('create.project', $this->message);
    }

    public function onStdout(string $chunk): void
    {
    }

    public function onStderr(string $chunk): void
    {
    }

    public function onTick(): void
    {
        $this->renderer->update('create.project', $this->message);
    }

    public function onFinish(ProcessResult $result): void
    {
    }
}
