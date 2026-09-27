<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Project;

/**
 * Customizes application-owned metadata after create-project.
 *
 * @internal
 */
final class ProjectCustomizer
{
    public function customize(string $projectRoot, ProjectName $name): void
    {
        $this->updateComposerJson($projectRoot, $name);
        $this->updateDurinYaml($projectRoot, $name);
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

    private function updateDurinYaml(string $projectRoot, ProjectName $name): void
    {
        $path = $projectRoot . DIRECTORY_SEPARATOR . 'durin.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('durin.yaml is missing after create-project.');
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Unable to read durin.yaml.');
        }

        if (!preg_match('/^application:\s*$/m', $raw)) {
            throw new \RuntimeException('durin.yaml is missing the expected application: section.');
        }

        if (!preg_match('/^  name:\s*.+$/m', $raw)) {
            throw new \RuntimeException('durin.yaml is missing the expected application.name field.');
        }

        $updated = preg_replace(
            '/^(  name:\s*).+$/m',
            '${1}' . $name->slug(),
            $raw,
            1,
            $count,
        );

        if ($updated === null || $count !== 1) {
            throw new \RuntimeException('Failed to update durin.yaml application.name.');
        }

        if (preg_match_all('/^  name:\s*.+$/m', $updated) !== 1) {
            throw new \RuntimeException('durin.yaml would contain duplicate application.name entries.');
        }

        if (file_put_contents($path, $updated) === false) {
            throw new \RuntimeException('Unable to write durin.yaml.');
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
