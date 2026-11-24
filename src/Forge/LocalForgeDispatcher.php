<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Forge;

/**
 * Global durins-forge entry: locate the local CLI and execute it.
 *
 * @internal
 */
final class LocalForgeDispatcher
{
    public function __construct(
        private readonly ?LocalForgeLocator $locator = null,
        private readonly ?ForgeProcess $process = null,
        private readonly mixed $stderr = null,
        private readonly ?PhpExecutable $php = null,
        private readonly ?string $launcherRealpath = null,
    ) {
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv, ?string $cwd): int
    {
        $locator = $this->locator ?? new LocalForgeLocator($this->launcherRealpath);

        try {
            $target = $locator->locate($cwd);
        } catch (ForgeLocateException $e) {
            $this->error($e->getMessage());

            return $e->exitCode();
        }

        $php = ($this->php ?? new PhpExecutable())->resolve();
        $process = $this->process ?? new LocalForgeRunner(
            stderr: $this->stderr,
        );

        return $process->run(
            $php,
            $target->script,
            array_values(array_slice($argv, 1)),
            (string) $cwd,
        );
    }

    private function error(string $message): void
    {
        $stream = $this->stderr ?? STDERR;
        fwrite($stream, $message . PHP_EOL);
    }
}
