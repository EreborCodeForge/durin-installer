<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Project;

/**
 * Normalizes a project directory basename into a stable application slug.
 *
 * @internal
 */
final class ProjectName
{
    private function __construct(
        private readonly string $slug,
    ) {
    }

    public static function fromDirectoryBasename(string $basename): self
    {
        $normalized = self::normalize($basename);

        if ($normalized === null) {
            throw new \InvalidArgumentException(
                'Invalid project name. Use a slug with letters, numbers, and hyphens (e.g. billing-api).',
            );
        }

        return new self($normalized);
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function composerPackageName(): string
    {
        return 'app/' . $this->slug;
    }

    public static function normalize(string $input): ?string
    {
        $value = strtolower(trim($input));
        $value = str_replace([' ', '_'], '-', $value);
        $value = preg_replace('/[^a-z0-9-]+/', '', $value) ?? '';
        $value = preg_replace('/-+/', '-', $value) ?? '';
        $value = trim($value, '-');

        if ($value === '') {
            return null;
        }

        if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
