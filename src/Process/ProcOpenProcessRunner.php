<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Process;

/**
 * Cross-platform process runner using argument arrays (no shell interpolation).
 *
 * Reads stdout/stderr concurrently to avoid pipe-buffer deadlocks (common when
 * Composer writes heavily to STDERR while the parent waits on STDOUT alone).
 *
 * @internal
 */
final class ProcOpenProcessRunner implements ProcessRunner
{
    public function __construct(
        private readonly mixed $liveStdout = null,
        private readonly mixed $liveStderr = null,
    ) {
    }

    public function run(array $command, ?string $cwd = null): ProcessResult
    {
        if ($command === []) {
            throw new \InvalidArgumentException('Process command must not be empty.');
        }

        $argv = $this->prepareCommand($command);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open(
            $argv,
            $descriptors,
            $pipes,
            $cwd,
            null,
            ['bypass_shell' => true],
        );

        if (!is_resource($process)) {
            return new ProcessResult(
                1,
                '',
                'Failed to start process: ' . implode(' ', $argv),
            );
        }

        fclose($pipes[0]);

        /** @var array<int, resource> $open */
        $open = [
            1 => $pipes[1],
            2 => $pipes[2],
        ];

        foreach ($open as $stream) {
            stream_set_blocking($stream, false);
        }

        $stdout = '';
        $stderr = '';
        $idleAfterExit = 0;

        while ($open !== []) {
            $read = array_values($open);
            $write = null;
            $except = null;
            $ready = @stream_select($read, $write, $except, 1);

            if ($ready === false) {
                break;
            }

            foreach ($read as $stream) {
                $fd = null;
                foreach ($open as $key => $candidate) {
                    if ($candidate === $stream) {
                        $fd = $key;
                        break;
                    }
                }
                if ($fd === null) {
                    continue;
                }

                $chunk = fread($stream, 8192);
                if ($chunk === false) {
                    fclose($stream);
                    unset($open[$fd]);
                    continue;
                }

                if ($chunk !== '') {
                    if ($fd === 1) {
                        $stdout .= $chunk;
                        $this->tee($this->liveStdout, $chunk);
                    } else {
                        $stderr .= $chunk;
                        $this->tee($this->liveStderr, $chunk);
                    }
                }

                if ($chunk === '' && feof($stream)) {
                    fclose($stream);
                    unset($open[$fd]);
                }
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                if ($ready === 0) {
                    ++$idleAfterExit;
                } else {
                    $idleAfterExit = 0;
                }

                if ($idleAfterExit >= 2) {
                    foreach ($open as $stream) {
                        fclose($stream);
                    }
                    $open = [];
                }
            }
        }

        $exitCode = proc_close($process);

        return new ProcessResult(
            $exitCode === -1 ? 1 : $exitCode,
            $stdout,
            $stderr,
        );
    }

    private function tee(mixed $stream, string $chunk): void
    {
        if (!is_resource($stream)) {
            return;
        }

        fwrite($stream, $chunk);
    }

    /**
     * @param list<string> $command
     * @return list<string>
     */
    private function prepareCommand(array $command): array
    {
        $binary = $command[0];
        $args = array_slice($command, 1);

        if (PHP_OS_FAMILY === 'Windows' && preg_match('/\.(bat|cmd)$/i', $binary) === 1) {
            return array_merge(['cmd.exe', '/C', $binary], $args);
        }

        return $command;
    }
}
