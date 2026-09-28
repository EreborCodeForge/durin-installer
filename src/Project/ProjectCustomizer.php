<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Project;

/**
 * Customizes installer-owned metadata after create-project.
 * Forge/preset owns durin.yaml — this class must not mutate it.
 *
 * @internal
 */
final class ProjectCustomizer
{
    public function customize(string $projectRoot, ProjectName $name): void
    {
        $this->updateComposerJson($projectRoot, $name);
        $this->ensureEnv($projectRoot, $name);
    }

    private function updateComposerJson(string $projectRoot, ProjectName $name): void
    {
        $path = $projectRoot . DIRECTORY_SEPARATOR . 'composer.json';
        if (!is_file($path)) {
            throw new \RuntimeException('composer.json is missing after create-project.');
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Unable to read composer.json.');
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('composer.json is not valid JSON: ' . $e->getMessage(), 0, $e);
        }

        $data['name'] = $name->composerPackageName();

        $encoded = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . "\n";

        if (file_put_contents($path, $encoded) === false) {
            throw new \RuntimeException('Unable to write composer.json.');
        }
    }

    private function ensureEnv(string $projectRoot, ProjectName $name): void
    {
        $envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
        $examplePath = $projectRoot . DIRECTORY_SEPARATOR . '.env.example';

        if (!is_file($envPath)) {
            if (!is_file($examplePath)) {
                throw new \RuntimeException('.env.example is missing; cannot create .env.');
            }

            if (!copy($examplePath, $envPath)) {
                throw new \RuntimeException('Unable to copy .env.example to .env.');
            }
        }

        $raw = file_get_contents($envPath);
        if ($raw === false) {
            throw new \RuntimeException('Unable to read .env.');
        }

        if (preg_match('/^APP_NAME=.*$/m', $raw) === 1) {
            $updated = preg_replace('/^APP_NAME=.*$/m', 'APP_NAME=' . $name->slug(), $raw, 1);
        } else {
            $updated = rtrim($raw) . "\nAPP_NAME=" . $name->slug() . "\n";
        }

        if ($updated === null) {
            throw new \RuntimeException('Failed to update APP_NAME in .env.');
        }

        if (file_put_contents($envPath, $updated) === false) {
            throw new \RuntimeException('Unable to write .env.');
        }
    }
}
