<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Command;

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
    ) {
    }

    /**
     * @param list<string> $args
     */
    public function run(array $args): int
    {
        $filtered = [];
        foreach ($args as $arg) {
            if ($arg === '--no-interaction') {
                continue;
            }
            $filtered[] = $arg;
        }

        if ($filtered === []) {
            $this->error('Missing project name.');
            $this->error('Usage: durin new <project>');

            return ExitCode::INVALID_USAGE;
        }

        if (count($filtered) > 1) {
            $this->error('Too many arguments.');
            $this->error('Usage: durin new <project>');

            return ExitCode::INVALID_USAGE;
        }

        $input = $filtered[0];

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
            $created = $this->creator->create($target);
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

        $this->line("Durin application created: {$slug}");
        $this->line('');
        $this->line('Path:');
        $this->line("  {$path}");
        $this->line('');
        $this->line('Next:');
        $this->line("  cd {$path}");
        $this->line('  vendor/bin/durin doctor');
        $this->line('  vendor/bin/durin dev');
        $this->line('');
        $this->line('Optional (local Eregion server is not installed by create-project):');
        $this->line('  vendor/bin/forge server:install');

        return ExitCode::SUCCESS;
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
