<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Project;

use EreborCodeForge\Durin\Installer\Composer\ComposerLocator;
use EreborCodeForge\Durin\Installer\Console\ForgeProgressRenderer;
use EreborCodeForge\Durin\Installer\Console\ForgeStageMessages;
use EreborCodeForge\Durin\Installer\Console\PlainProgressRenderer;
use EreborCodeForge\Durin\Installer\Console\ProgressRenderer;
use EreborCodeForge\Durin\Installer\Console\TerminalCapabilities;
use EreborCodeForge\Durin\Installer\Process\ComposerProgressObserver;
use EreborCodeForge\Durin\Installer\Process\JsonlForgeProgressObserver;
use EreborCodeForge\Durin\Installer\Process\ProcessRunner;
use EreborCodeForge\Durin\Installer\Support\AppPackage;
use EreborCodeForge\Durin\Installer\Support\ExitCode;

/**
 * Orchestrates create-project, customization, Forge init, and Doctor.
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
        private readonly mixed $statusOutput = null,
        private readonly ?ProgressRenderer $progress = null,
        private readonly bool $interactive = false,
    ) {
    }

    /**
     * @return array{path: string, name: string, preset: string, execution: string, supervisor: string}
     */
    public function create(ProjectPath $target, string $presetId): array
    {
        $plain = !$this->interactive;
        $progress = $this->progress ?? ($plain
            ? new PlainProgressRenderer($this->statusOutput)
            : new ForgeProgressRenderer(
                $this->statusOutput,
                TerminalCapabilities::supportsUnicode(),
            ));

        $composer = $this->composerLocator->locate();
        $this->debugLine('Composer: ' . $composer);
        $this->debugLine('Target: ' . $target->absolutePath());
        $this->debugLine('Preset: ' . $presetId);

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

        $createMessage = $plain
            ? ForgeStageMessages::plainForStage('create.project')
            : ForgeStageMessages::forStage('create.project');
        $progress->start('create.project', $createMessage);

        $result = $this->processRunner->run(
            $command,
            null,
            new ComposerProgressObserver($progress, $createMessage),
        );

        if (!$result->isSuccessful()) {
            $progress->fail($plain ? 'Project creation failed.' : 'A forja esfriou antes da conclusão.');
            $message = "Composer create-project failed with exit code {$result->exitCode}.";
            if ($result->stderr !== '') {
                $message .= "\n" . trim($result->stderr);
            } elseif ($result->stdout !== '' && $this->debug) {
                $message .= "\n" . trim($result->stdout);
            }

            if (is_dir($target->absolutePath())) {
                $message .= "\n\nPartial project left at:\n  {$target->absolutePath()}\n"
                    . "Remove it manually before retrying.";
            }

            throw new CreationException($message, ExitCode::CREATE_PROJECT_FAILURE);
        }

        $execution = '';
        $supervisor = 'none';

        try {
            $customizeMessage = $plain
                ? ForgeStageMessages::plainForStage('customize')
                : ForgeStageMessages::forStage('customize');
            $progress->update('customize', $customizeMessage);
            $this->customizer->customize($target->absolutePath(), $target->name());

            $this->validateCreatedApplication($target->absolutePath());

            $initObserver = new JsonlForgeProgressObserver($progress, $presetId, $plain);
            $initResult = $this->runForgeInit($target->absolutePath(), $presetId, $initObserver);
            if (!$initResult->isSuccessful()) {
                $progress->fail($plain ? 'Preset initialization failed.' : 'A forja esfriou antes da conclusão.');
                $message = "Forge init failed with exit code {$initResult->exitCode}.";
                if ($initResult->stderr !== '') {
                    $message .= "\n" . trim($initResult->stderr);
                } elseif ($initResult->stdout !== '') {
                    $message .= "\n" . trim($initResult->stdout);
                }
                $message = "Preset: {$presetId}\nStage: runtime/scaffold\n\n" . $message;
                throw new CreationException($message, ExitCode::POST_CREATE_VALIDATION_FAILURE);
            }

            $complete = $initObserver->completePayload();
            if ($complete === [] || !isset($complete['runtime'])) {
                $progress->fail($plain ? 'Preset initialization failed.' : 'A forja esfriou antes da conclusão.');
                $detail = $initObserver->sawInvalidComplete()
                    ? 'Forge complete payload missing required runtime.'
                    : 'Forge complete payload was not received.';
                $message = "Preset: {$presetId}\nStage: complete\n\n{$detail}";
                if ($initResult->stderr !== '') {
                    $message .= "\n" . trim($initResult->stderr);
                }
                throw new CreationException($message, ExitCode::POST_CREATE_VALIDATION_FAILURE);
            }

            $presetId = $complete['preset'];
            $execution = $complete['runtime']['execution'];
            $supervisor = $complete['runtime']['supervisor'] ?? 'none';

            $doctorMessage = $plain
                ? ForgeStageMessages::plainForStage('doctor')
                : ForgeStageMessages::forStage('doctor');
            $progress->update('doctor', $doctorMessage);
            $this->runDoctor($target->absolutePath());
        } catch (CreationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $progress->fail($plain ? 'Post-create failed.' : 'A forja esfriou antes da conclusão.');
            throw new CreationException(
                $e->getMessage(),
                ExitCode::POST_CREATE_VALIDATION_FAILURE,
                $e,
            );
        }

        $slug = $target->name()->slug();
        $success = $plain
            ? "Created {$slug}."
            : "{$slug} foi forjado com sucesso.";
        $progress->succeed($success);

        return [
            'path' => $target->absolutePath(),
            'name' => $slug,
            'preset' => $presetId,
            'execution' => $execution,
            'supervisor' => $supervisor,
        ];
    }

    /**
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
            'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durin',
        ];

        foreach ($required as $relative) {
            $path = $projectRoot . DIRECTORY_SEPARATOR . $relative;
            if (!file_exists($path) && !(PHP_OS_FAMILY === 'Windows' && is_file($path . '.bat'))) {
                throw new CreationException(
                    "Created application is missing required file:\n  {$path}",
                    ExitCode::POST_CREATE_VALIDATION_FAILURE,
                );
            }
        }
    }

    private function runForgeInit(
        string $projectRoot,
        string $presetId,
        JsonlForgeProgressObserver $observer,
    ): \EreborCodeForge\Durin\Installer\Process\ProcessResult {
        $durin = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durin';
        if (PHP_OS_FAMILY === 'Windows' && is_file($durin . '.bat')) {
            $durin .= '.bat';
        }

        $runner = $this->processRunner;

        return $runner->run(
            [$durin, 'init', '--preset=' . $presetId, '--progress=jsonl'],
            $projectRoot,
            $observer,
        );
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
