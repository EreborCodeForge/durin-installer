<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Process;

/**
 * Cross-platform process runner using argument arrays (no shell interpolation).
 *
 * @internal
 */
final class ProcOpenProcessRunner implements ProcessRunner
{
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

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return new ProcessResult(
            $exitCode === -1 ? 1 : $exitCode,
            $stdout === false ? '' : $stdout,
            $stderr === false ? '' : $stderr,
        );
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
