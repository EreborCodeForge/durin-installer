<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Command;

use EreborCodeForge\Durin\Installer\Preset\NewInvocation;
use EreborCodeForge\Durin\Installer\Preset\PresetCatalog;
use EreborCodeForge\Durin\Installer\Preset\UnknownPresetException;
use EreborCodeForge\Durin\Installer\Project\CreationException;
use EreborCodeForge\Durin\Installer\Project\ProjectCreator;
use EreborCodeForge\Durin\Installer\Project\ProjectPath;
use EreborCodeForge\Durin\Installer\Support\ExitCode;

/**
 * @internal
 */
final class NewCommand
{
    public function __construct(
        private readonly ProjectCreator $creator,
        private readonly mixed $stdout = null,
        private readonly mixed $stderr = null,
        private readonly ?string $cwd = null,
        private readonly ?string $installerRoot = null,
        private readonly ?PresetCatalog $catalog = null,
    ) {
    }

    /**
     * @param list<string> $args
     */
    public function run(array $args): int
    {
        $catalog = $this->catalog ?? new PresetCatalog();
        try {
            $invocation = NewInvocation::parse($args, $catalog);
        } catch (UnknownPresetException | \InvalidArgumentException $e) {
            $this->error($e->getMessage());
            if ($e->getMessage() === 'Missing project name.' || str_starts_with($e->getMessage(), 'Missing value') || $e->getMessage() === 'Too many arguments.') {
                $this->error('Usage: durin new <project> [--preset=<id>] [--with=<capabilities>] [--supervisor=auto|none|eregion]');
            }

            return ExitCode::INVALID_USAGE;
        }

        if ($invocation->project === null || $invocation->project === '') {
            $this->error('Missing project name.');
            $this->error('Usage: durin new <project> [--preset=<id>] [--with=<capabilities>] [--supervisor=auto|none|eregion]');

            return ExitCode::INVALID_USAGE;
        }

        $presetId = $invocation->presetId;
        $input = $invocation->project;

        try {
            $target = ProjectPath::resolve(
                $input,
                $this->cwd,
                null,
                null,
                $this->installerRoot,
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return ExitCode::INVALID_USAGE;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            $code = $e->getCode();

            return is_int($code) && $code === ExitCode::TARGET_CONFLICT
                ? ExitCode::TARGET_CONFLICT
                : ExitCode::GENERIC_FAILURE;
        }

        try {
            $created = $this->creator->create(
                $target,
                $presetId,
                $invocation->initArguments,
                $invocation->expectedFeatures,
            );
        } catch (CreationException $e) {
            $this->error($e->getMessage());

            return $e->installerExitCode();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            if (str_contains($e->getMessage(), 'Composer was not found')
                || str_contains($e->getMessage(), 'COMPOSER_BINARY')) {
                return ExitCode::ENVIRONMENT_MISSING;
            }

            return ExitCode::GENERIC_FAILURE;
        }

        $slug = $created['name'];
        $path = $created['path'];
        $preset = $created['preset'];
        $execution = $created['execution'];
        $supervisor = $created['supervisor'];
        $mode = $created['mode'];

        $this->line('');
        if ($preset === 'uninitialized') {
            $this->line('Preset: uninitialized');
            $this->line("Path: {$path}");
            $this->line('');
            $this->line('Next:');
            $this->line("  cd {$path}");
            $this->line('  durins-forge init --preset=minimal');
            $this->line('  durins-forge doctor');

            return ExitCode::SUCCESS;
        }

        $this->line("Preset: {$preset}");
        $this->line("Runtime: {$execution}");
        $this->line("Supervisor: {$supervisor}");
        $this->line("Path: {$path}");
        $this->line('');
        $this->line('Next:');
        $this->line("  cd {$path}");
        $this->line('  durins-forge doctor');
        $this->line('  durins-forge ' . $this->followUpCommand($mode, $execution));

        return ExitCode::SUCCESS;
    }

    private function followUpCommand(string $mode, string $execution): string
    {
        if ($mode === 'job' || str_contains($execution, 'job')) {
            return 'run';
        }

        return 'dev';
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
