<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer;

use EreborCodeForge\Durin\Installer\Command\NewCommand;
use EreborCodeForge\Durin\Installer\Composer\ComposerLocator;
use EreborCodeForge\Durin\Installer\Process\ProcOpenProcessRunner;
use EreborCodeForge\Durin\Installer\Process\ProcessRunner;
use EreborCodeForge\Durin\Installer\Project\ProjectCreator;
use EreborCodeForge\Durin\Installer\Support\ExitCode;
use EreborCodeForge\Durin\Installer\Support\InstallerVersion;

/**
 * CLI entrypoint: argv parsing, help, version, and command dispatch.
 *
 * @internal
 */
final class Application
{
    public function __construct(
        private readonly ?ProcessRunner $processRunner = null,
        private readonly ?ComposerLocator $composerLocator = null,
        private readonly mixed $stdout = null,
        private readonly mixed $stderr = null,
        private readonly ?string $cwd = null,
        private readonly ?string $installerRoot = null,
        private readonly ?bool $debug = null,
    ) {
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $args = array_values(array_slice($argv, 1));

        if ($args === [] || $this->isHelp($args)) {
            $this->printHelp();

            return ExitCode::SUCCESS;
        }

        if ($this->isVersion($args)) {
            $this->printVersion();

            return ExitCode::SUCCESS;
        }

        $command = $args[0];
        $commandArgs = array_values(array_slice($args, 1));

        return match ($command) {
            'new' => $this->runNew($commandArgs),
            'help' => $this->printHelp(),
            default => $this->unknownCommand($command),
        };
    }

    /**
     * @param list<string> $args
     */
    private function isHelp(array $args): bool
    {
        return $args === ['--help']
            || $args === ['-h']
            || $args === ['help'];
    }

    /**
     * @param list<string> $args
     */
    private function isVersion(array $args): bool
    {
        return $args === ['--version'] || $args === ['-V'];
    }

    /**
     * @param list<string> $args
     */
    private function runNew(array $args): int
    {
        $debug = $this->debug ?? (getenv('DURIN_INSTALLER_DEBUG') === '1');

        $stdout = $this->stdout ?? STDOUT;
        $stderr = $this->stderr ?? STDERR;

        $runner = $this->processRunner ?? new ProcOpenProcessRunner(
            liveStdout: $stdout,
            liveStderr: $stderr,
        );
        $locator = $this->composerLocator ?? new ComposerLocator();
        $creator = new ProjectCreator(
            $runner,
            $locator,
            debug: $debug,
            output: $stderr,
            statusOutput: $stdout,
        );

        $command = new NewCommand(
            $creator,
            $this->stdout,
            $this->stderr,
            $this->cwd,
            $this->installerRoot,
        );

        return $command->run($args);
    }

    private function unknownCommand(string $command): int
    {
        $this->error("Unknown command: {$command}");
        $this->error('Run "durin --help" for usage.');

        return ExitCode::INVALID_USAGE;
    }

    private function printHelp(): int
    {
        $this->line('Durin Installer');
        $this->line('');
        $this->line('Usage:');
        $this->line('  durin new <project>');
        $this->line('  durin --version');
        $this->line('  durin --help');
        $this->line('');
        $this->line('Commands:');
        $this->line('  new    Create a new Durin application');

        return ExitCode::SUCCESS;
    }

    private function printVersion(): void
    {
        $this->line('Durin Installer ' . InstallerVersion::VERSION);
    }

    private function line(string $message): void
    {
        $stream = $this->stdout ?? STDOUT;
        fwrite($stream, $message . PHP_EOL);
    }

    private function error(string $message): void
    {
        $stream = $this->stderr ?? STDERR;
        fwrite($stream, $message . PHP_EOL);
    }
}
