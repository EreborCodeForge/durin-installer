<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Process;

/**
 * Observes child process lifecycle for progress animation / JSONL consumption.
 *
 * @internal
 */
interface ProcessObserver
{
    public function onStart(): void;

    public function onStdout(string $chunk): void;

    public function onStderr(string $chunk): void;

    public function onTick(): void;

    public function onFinish(ProcessResult $result): void;
}
