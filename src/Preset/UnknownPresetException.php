<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Installer\Preset;

/**
 * @internal
 */
final class UnknownPresetException extends \InvalidArgumentException
{
    /**
     * @param list<string> $available
     */
    public static function forName(string $name, array $available): self
    {
        $list = $available === [] ? '(none registered)' : implode(', ', $available);

        return new self("Unknown preset \"{$name}\". Available: {$list}");
    }
}
