<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Console;

/**
 * Themed forge animation for interactive terminals.
 *
 * @internal
 */
final class ForgeProgressRenderer implements ProgressRenderer
{
    private int $frame = 0;

    /** @var list<string> */
    private array $unicodeFrames = ['⚒      ▰▰▰', '  ⚒    ▰▰▰', '    ⚒  ▰▰▰', '   ✦   ▰▰▰'];

    /** @var list<string> */
    private array $asciiFrames = ['/|>   [___]', '  /|> [___]', '   /|>[___]', '   *  [___]'];

    public function __construct(
        private readonly mixed $stdout = null,
        private readonly bool $unicode = true,
    ) {
    }

    public function start(string $stage, string $message): void
    {
        $this->frame = 0;
        $this->render($message, clearLine: false);
    }

    public function update(string $stage, string $message): void
    {
        $this->frame++;
        $this->render($message, clearLine: true);
    }

    public function succeed(string $message): void
    {
        $this->clearLine();
        $prefix = $this->unicode ? '⚒' : '*';
        $this->write($prefix . '  ' . $message);
    }

    public function fail(string $message): void
    {
        $this->clearLine();
        $prefix = $this->unicode ? '✗' : 'x';
        $this->write($prefix . '  ' . $message);
    }

    public function tick(string $message): void
    {
        $this->update('tick', $message);
    }

    private function render(string $message, bool $clearLine): void
    {
        if ($clearLine) {
            $this->clearLine();
        }
        $frames = $this->unicode ? $this->unicodeFrames : $this->asciiFrames;
        $frame = $frames[$this->frame % count($frames)];
        $this->write($frame . '   ' . $message, newline: false);
    }

    private function clearLine(): void
    {
        $stream = $this->stdout ?? STDOUT;
        fwrite($stream, "\r\033[2K");
    }

    private function write(string $line, bool $newline = true): void
    {
        $stream = $this->stdout ?? STDOUT;
        fwrite($stream, $line . ($newline ? PHP_EOL : ''));
    }
}
