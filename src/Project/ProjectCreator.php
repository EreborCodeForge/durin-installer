<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Project;

use EreborCodeForge\Durin\Installer\Composer\ComposerLocator;
use EreborCodeForge\Durin\Installer\Process\ProcessRunner;
use EreborCodeForge\Durin\Installer\Support\AppPackage;
use EreborCodeForge\Durin\Installer\Support\ExitCode;

/**
 * Orchestrates create-project, customization, and post-create validation.
 *
 * @internal
 */
final class ProjectCreator
{
    public function __construct(
        private readonly ProcessRunner $processRunner,
        private readonly ComposerLocator $composerLocator,
        private readonly ProjectCustomizer $customizer = new ProjectCustomizer(),
        private readonly bool $debug = false,
        private readonly mixed $output = null,
    ) {
    }

    /**
     * @return array{path: string, name: string}
     */
    public function create(ProjectPath $target): array
    {
        $composer = $this->composerLocator->locate();
        $this->debugLine('Composer: ' . $composer);
        $this->debugLine('Target: ' . $target->absolutePath());

        $parent = $target->parentDirectory();
        if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
            throw new CreationException(
                'Unable to create parent directory: ' . $parent,
                ExitCode::GENERIC_FAILURE,
            );
        }

        $packageArg = AppPackage::createProjectArgument();
        $command = [
            $composer,
            'create-project',
            $packageArg,
            $target->absolutePath(),
            '--no-interaction',
            '--prefer-dist',
        ];

        $this->debugLine('Argv: ' . json_encode($command, JSON_UNESCAPED_SLASHES));

        $result = $this->processRunner->run($command);

        if (!$result->isSuccessful()) {
            $message = "Composer create-project failed with exit code {$result->exitCode}.";
            if ($result->stderr !== '') {
                $message .= "\n" . trim($result->stderr);
            } elseif ($result->stdout !== '') {
                $message .= "\n" . trim($result->stdout);
            }

            if (is_dir($target->absolutePath())) {
                $message .= "\n\nPartial project left at:\n  {$target->absolutePath()}\n"
                    . "Remove it manually before retrying.";
            }

            throw new CreationException($message, ExitCode::CREATE_PROJECT_FAILURE);
        }

        try {
            $this->customizer->customize($target->absolutePath(), $target->name());
            $this->validateCreatedApplication($target->absolutePath());
            $this->runDoctor($target->absolutePath());
        } catch (CreationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new CreationException(
                $e->getMessage(),
                ExitCode::POST_CREATE_VALIDATION_FAILURE,
                $e,
            );
        }

        return [
            'path' => $target->absolutePath(),
            'name' => $target->name()->slug(),
        ];
    }

    /**
     * Exposed for tests: build the exact create-project argv.
     *
     * @return list<string>
     */
    public function buildCreateProjectCommand(string $composerBinary, string $targetPath): array
    {
        return [
            $composerBinary,
            'create-project',
            AppPackage::createProjectArgument(),
            $targetPath,
            '--no-interaction',
            '--prefer-dist',
        ];
    }

    private function validateCreatedApplication(string $projectRoot): void
    {
        $required = [
            'composer.json',
            'durin.yaml',
            '.env',
            'src' . DIRECTORY_SEPARATOR . 'Kernel.php',
            'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durin',
        ];

        foreach ($required as $relative) {
            $path = $projectRoot . DIRECTORY_SEPARATOR . $relative;
            if (!file_exists($path)) {
                throw new CreationException(
                    "Created application is missing required file:\n  {$path}",
                    ExitCode::POST_CREATE_VALIDATION_FAILURE,
                );
            }
        }
    }

    private function runDoctor(string $projectRoot): void
    {
        $doctor = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durin';
        if (PHP_OS_FAMILY === 'Windows' && is_file($doctor . '.bat')) {
            $doctor .= '.bat';
        }

        $result = $this->processRunner->run([$doctor, 'doctor'], $projectRoot);

        if (!$result->isSuccessful()) {
            $message = 'Created application failed Durin Doctor.';
            if ($result->stderr !== '') {
                $message .= "\n" . trim($result->stderr);
            } elseif ($result->stdout !== '') {
                $message .= "\n" . trim($result->stdout);
            }

            throw new CreationException($message, ExitCode::POST_CREATE_VALIDATION_FAILURE);
        }
    }

    private function debugLine(string $line): void
    {
        if (!$this->debug) {
            return;
        }

        $stream = $this->output ?? STDERR;
        fwrite($stream, '[durin-installer] ' . $line . PHP_EOL);
    }
}
