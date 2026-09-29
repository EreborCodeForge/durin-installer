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

    /**
     * @var array{
     *     preset: string,
     *     runtime: array{mode: string, execution: string, supervisor: ?string}
     * }|array{}
     */
    private array $complete = [];

    private bool $sawInvalidComplete = false;

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
     * @return array{
     *     preset: string,
     *     runtime: array{mode: string, execution: string, supervisor: ?string}
     * }|array{}
     */
    public function completePayload(): array
    {
        return $this->complete;
    }

    public function sawInvalidComplete(): bool
    {
        return $this->sawInvalidComplete;
    }

    private function handleLine(string $line): void
    {
        try {
            /** @var array<string, mixed> $row */
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
            $parsed = $this->parseComplete($row);
            if ($parsed === null) {
                $this->sawInvalidComplete = true;
                $this->complete = [];

                return;
            }

            $this->sawInvalidComplete = false;
            $this->complete = $parsed;
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{
     *     preset: string,
     *     runtime: array{mode: string, execution: string, supervisor: ?string}
     * }|null
     */
    private function parseComplete(array $row): ?array
    {
        $runtime = $row['runtime'] ?? null;
        if (!is_array($runtime)) {
            return null;
        }

        $mode = $runtime['mode'] ?? null;
        $execution = $runtime['execution'] ?? null;
        if (!is_string($mode) || $mode === '' || !is_string($execution) || $execution === '') {
            return null;
        }

        $supervisor = $runtime['supervisor'] ?? null;
        if ($supervisor !== null && !is_string($supervisor)) {
            return null;
        }
        if (is_string($supervisor) && $supervisor === '') {
            return null;
        }

        $preset = $row['preset'] ?? $this->preset;
        if (!is_string($preset) || $preset === '') {
            $preset = $this->preset;
        }

        return [
            'preset' => $preset,
            'runtime' => [
                'mode' => $mode,
                'execution' => $execution,
                'supervisor' => $supervisor,
            ],
        ];
    }
}
