<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Process;

use EreborCodeForge\Durin\Installer\Console\ForgeStageMessages;
use EreborCodeForge\Durin\Installer\Console\ProgressRenderer;

/**
 * Consumes Forge --progress=jsonl and drives the installer progress renderer.
 *
 * @internal
 */
final class JsonlForgeProgressObserver implements ProcessObserver
{
    private string $buffer = '';

    /** @var array{preset?: string, runner?: string} */
    private array $complete = [];

    public function __construct(
        private readonly ProgressRenderer $renderer,
        private readonly string $preset,
        private readonly bool $plain,
        private string $currentMessage = '',
    ) {
    }

    public function onStart(): void
    {
    }

    public function onStdout(string $chunk): void
    {
        $this->buffer .= $chunk;
        while (($pos = strpos($this->buffer, "\n")) !== false) {
            $line = trim(substr($this->buffer, 0, $pos));
            $this->buffer = substr($this->buffer, $pos + 1);
            if ($line === '') {
                continue;
            }
            $this->handleLine($line);
        }
    }

    public function onStderr(string $chunk): void
    {
        // Keep stderr for failure reporting; do not stream to UI during success path.
    }

    public function onTick(): void
    {
        if ($this->currentMessage !== '') {
            $this->renderer->update('tick', $this->currentMessage);
        }
    }

    public function onFinish(ProcessResult $result): void
    {
        if ($this->buffer !== '') {
            $this->handleLine(trim($this->buffer));
            $this->buffer = '';
        }
    }

    /**
     * @return array{preset?: string, runner?: string}
     */
    public function completePayload(): array
    {
        return $this->complete;
    }

    private function handleLine(string $line): void
    {
        try {
            /** @var array{type?: string, stage?: string, message?: string, preset?: string, runner?: string} $row */
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        if (($row['type'] ?? '') === 'progress') {
            $stage = (string) ($row['stage'] ?? '');
            $message = $this->plain
                ? ForgeStageMessages::plainForStage($stage, $this->preset)
                : ForgeStageMessages::forStage($stage, $this->preset);
            $this->currentMessage = $message;
            $this->renderer->update($stage, $message);

            return;
        }

        if (($row['type'] ?? '') === 'complete') {
            $this->complete = [
                'preset' => (string) ($row['preset'] ?? $this->preset),
                'runner' => (string) ($row['runner'] ?? 'eregion'),
            ];
        }
    }
}
