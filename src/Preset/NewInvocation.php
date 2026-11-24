<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Preset;

use EreborCodeForge\Durin\Presets\Capability\CapabilityCatalog;
use EreborCodeForge\Durin\Presets\Capability\IncompatibleCapabilityException;
use EreborCodeForge\Durin\Presets\Capability\WorkloadOptionException;
use EreborCodeForge\Durin\Presets\Capability\WorkloadOptionPolicy;

/**
 * Parses global `durin new` flags and validates them before create-project.
 *
 * @internal
 */
final class NewInvocation
{
    /**
     * @param list<string> $initArguments
     * @param array<string, bool> $expectedFeatures
     */
    public function __construct(
        public readonly ?string $project,
        public readonly ?string $presetId,
        public readonly array $initArguments,
        public readonly array $expectedFeatures,
    ) {
    }

    /**
     * @param list<string> $args
     */
    public static function parse(array $args, ?PresetCatalog $catalog = null): self
    {
        $catalog ??= new PresetCatalog();
        $options = [];
        $positionals = [];

        foreach ($args as $arg) {
            if ($arg === '--no-interaction') {
                continue;
            }
            if (!str_starts_with($arg, '--')) {
                $positionals[] = $arg;
                continue;
            }

            $body = substr($arg, 2);
            $eq = strpos($body, '=');
            if ($eq === false) {
                throw new \InvalidArgumentException("Missing value for --{$body}.");
            }

            $key = substr($body, 0, $eq);
            $value = substr($body, $eq + 1);
            if (!in_array($key, ['preset', 'with', 'supervisor', 'scaling', 'scaling-policy', 'resource-guard-mode', 'workers-min', 'workers-max', 'resource-class', 'memory-mb'], true)) {
                throw new \InvalidArgumentException("Unknown option --{$key}.");
            }
            if (array_key_exists($key, $options) && $options[$key] !== $value) {
                throw new \InvalidArgumentException("Conflicting values for --{$key}.");
            }
            $options[$key] = $value;
        }

        $project = $positionals[0] ?? null;
        if (count($positionals) > 1) {
            throw new \InvalidArgumentException('Too many arguments.');
        }

        $preset = $options['preset'] ?? null;
        if ($preset === '') {
            throw new \InvalidArgumentException('Missing value for --preset.');
        }

        $workloadKeys = ['with', 'supervisor', 'scaling', 'scaling-policy', 'resource-guard-mode', 'workers-min', 'workers-max', 'resource-class', 'memory-mb'];
        $hasWorkload = false;
        foreach ($workloadKeys as $key) {
            if (array_key_exists($key, $options)) {
                $hasWorkload = true;
                break;
            }
        }
        if ($hasWorkload && ($preset === null || $preset === '')) {
            throw new \InvalidArgumentException('Options --with, --supervisor, and workload flags require --preset.');
        }

        if ($preset === null || $preset === '') {
            return new self($project, null, [], []);
        }

        if (!$catalog->has($preset)) {
            throw UnknownPresetException::forName($preset, $catalog->ids());
        }

        $definition = $catalog->registry()->definition($preset);
        $capabilities = new CapabilityCatalog();
        try {
            $features = $capabilities->normalize($options['with'] ?? null);
            $capabilities->assertCompatible($definition->id(), $definition->runtime()->mode, $features);
            $workersMin = self::optionalInt($options, 'workers-min');
            $workersMax = self::optionalInt($options, 'workers-max');
            $memory = self::optionalInt($options, 'memory-mb');
            $policy = isset($options['scaling-policy']) ? self::absolutePolicy($options['scaling-policy']) : null;
            if ($policy !== null) {
                $options['scaling-policy'] = $policy;
            }
            (new WorkloadOptionPolicy())->compile(
                $definition->id(),
                $definition->runtime()->mode,
                $features,
                $options['supervisor'] ?? 'auto',
                $options['scaling'] ?? null,
                $workersMin,
                $workersMax,
                $options['resource-class'] ?? null,
                $memory,
                $policy,
                $options['resource-guard-mode'] ?? null,
            );
        } catch (IncompatibleCapabilityException | WorkloadOptionException $e) {
            throw new \InvalidArgumentException($e->getMessage(), 0, $e);
        }

        $init = [];
        if (isset($options['with'])) {
            $init[] = '--with=' . implode(',', $features);
        }
        foreach (['supervisor', 'scaling', 'scaling-policy', 'resource-guard-mode', 'workers-min', 'workers-max', 'resource-class', 'memory-mb'] as $key) {
            if (isset($options[$key])) {
                $init[] = '--' . $key . '=' . $options[$key];
            }
        }

        $expected = [];
        if ($features !== [] || $definition->id() === 'consumer' || $definition->id() === 'producer') {
            $expected = [
                'consumer' => $definition->id() === 'consumer' || in_array('consumer', $features, true),
                'producer' => $definition->id() === 'producer' || in_array('producer', $features, true),
            ];
        }

        return new self($project, $definition->id(), $init, $expected);
    }

    /**
     * @param array<string, string> $options
     */
    private static function optionalInt(array $options, string $key): ?int
    {
        if (!isset($options[$key])) {
            return null;
        }
        if (preg_match('/^-?\d+$/', $options[$key]) !== 1) {
            throw new WorkloadOptionException("Invalid --{$key}: expected an integer.");
        }

        return (int) $options[$key];
    }

    private static function absolutePolicy(string $path): string
    {
        if ($path === '' || strpbrk($path, "\0") !== false) {
            throw new WorkloadOptionException('Invalid --scaling-policy path.');
        }
        $absolute = $path;
        $windows = preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
        $rooted = str_starts_with($path, '/') || str_starts_with($path, '\\\\');
        if (!$windows && !$rooted) {
            $cwd = getcwd();
            if ($cwd === false) {
                throw new WorkloadOptionException('Unable to resolve --scaling-policy.');
            }
            $absolute = $cwd . DIRECTORY_SEPARATOR . $path;
        }
        $real = realpath($absolute);
        if ($real === false || !is_file($real) || is_link($real)) {
            throw new WorkloadOptionException('--scaling-policy must be a regular file.');
        }

        return $real;
    }
}
