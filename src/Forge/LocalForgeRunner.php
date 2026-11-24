<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Forge;

use EreborCodeForge\Durin\Installer\Support\ExitCode;

/**
 * Runs a validated local Forge script with the original argv, cwd, and stdio.
 *
 * Argument arrays only. On Unix, SIGINT and SIGTERM are forwarded to the current
 * process group so the local CLI and descendants such as Eregion receive the
 * same signal, then the launcher waits for the direct child. A signal that
 * arrives while that child is already being reaped is ignored. On Windows,
 * proc_open stays in the console process group (no new group) and a
 * shutdown handler terminates a child that is still running if the launcher
 * exits first. Console Ctrl+C cannot be injected reliably from this process.
 *
 * @internal
 */
final class LocalForgeRunner implements ForgeProcess
{
    public function __construct(
        private readonly mixed $stdin = null,
        private readonly mixed $stdout = null,
        private readonly mixed $stderr = null,
    ) {
    }

    public function run(string $phpBinary, string $script, array $arguments, string $cwd): int
    {
        $process = $this->open($phpBinary, $script, $arguments, $cwd);
        if (!is_resource($process)) {
            $this->writeFailure();

            return ExitCode::GENERIC_FAILURE;
        }

        $closed = false;
        $this->armShutdown($process, $closed);
        $exitCode = function_exists('pcntl_async_signals')
            ? $this->waitWhileDispatchingSignals($process, $closed)
            : proc_close($process);
        $closed = true;

        return $exitCode === -1 ? ExitCode::GENERIC_FAILURE : $exitCode;
    }

    /**
     * proc_close blocks inside a C call, so a pending SIGTERM would never reach
     * the handler that forwards it to Eregion. Polling leaves the VM free to
     * dispatch the signal and then reaps the direct child.
     */
    private function waitWhileDispatchingSignals(mixed $process, bool &$closed): int
    {
        while (true) {
            $status = @proc_get_status($process);
            if (!is_array($status) || !is_resource($process)) {
                $closed = true;

                return ExitCode::GENERIC_FAILURE;
            }

            if (($status['running'] ?? false) !== true) {
                $exitCode = $this->statusExitCode($status);
                @proc_close($process);
                $closed = true;

                return $exitCode;
            }

            usleep(100000);
        }
    }

    /**
     * @param array<string, mixed> $status
     */
    private function statusExitCode(array $status): int
    {
        if (($status['signaled'] ?? false) === true) {
            return 128 + (int) ($status['termsig'] ?? 0);
        }

        $exitCode = $status['exitcode'] ?? -1;

        return is_int($exitCode) ? $exitCode : ExitCode::GENERIC_FAILURE;
    }

    /**
     * @param list<string> $arguments
     */
    private function open(string $phpBinary, string $script, array $arguments, string $cwd): mixed
    {
        $command = array_merge([$phpBinary, $script], $arguments);
        $descriptors = [
            0 => $this->stream($this->stdin, STDIN),
            1 => $this->stream($this->stdout, STDOUT),
            2 => $this->stream($this->stderr, STDERR),
        ];

        return @proc_open(
            $command,
            $descriptors,
            $pipes,
            $cwd,
            null,
            ['bypass_shell' => true],
        );
    }

    /**
     * @param resource $process
     */
    private function armShutdown(mixed $process, bool &$closed): void
    {
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
        $forwarding = false;
        $forward = static function (int $signal) use ($process, &$closed, &$forwarding): void {
            if ($closed || $forwarding) {
                return;
            }

            $forwarding = true;
            if (function_exists('posix_kill') && function_exists('posix_getpgrp')) {
                $group = posix_getpgrp();
                if (is_int($group) && $group > 1) {
                    @posix_kill(-$group, $signal);
                }
            }

            try {
                if (is_resource($process)) {
                    proc_terminate($process, $signal);
                }
            } catch (\Throwable) {
                // The terminal already delivered this signal and proc_close is reaping the child.
            }
        };
            if (defined('SIGINT')) {
                pcntl_signal(SIGINT, $forward);
            }
            if (defined('SIGTERM')) {
                pcntl_signal(SIGTERM, $forward);
            }
        }

        register_shutdown_function(static function () use ($process, &$closed): void {
            if ($closed || !is_resource($process)) {
                return;
            }

            $status = @proc_get_status($process);
            if (is_array($status) && ($status['running'] ?? false) === true) {
                @proc_terminate($process);
                @proc_close($process);
            }
            $closed = true;
        });
    }

    private function stream(mixed $override, mixed $fallback): mixed
    {
        $stream = $override ?? $fallback;
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Forge process stdio must be a stream resource.');
        }

        return $stream;
    }

    private function writeFailure(): void
    {
        $stream = $this->stderr ?? STDERR;
        if (is_resource($stream)) {
            fwrite($stream, "Failed to start local Forge CLI.\n");
        }
    }
}
