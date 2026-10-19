<?php

declare(strict_types=1);

namespace Gcego\Registry\Exceptions;

use InvalidArgumentException;

class DiscoveryException extends InvalidArgumentException implements RegistryException
{
    public static function invalidRoot(int|string $index, string $reason): self
    {
        return new self(sprintf('Invalid discovery root gcego-registry.discovery.roots.%s: %s', $index, $reason));
    }

    public static function missingRequiredRoot(string $path): self
    {
        return new self(sprintf('Required discovery root %s does not exist.', $path));
    }

    public static function notADirectory(string $path): self
    {
        return new self(sprintf('Discovery root %s exists but is not a readable directory.', $path));
    }
}
