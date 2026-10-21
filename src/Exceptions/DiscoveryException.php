<?php

declare(strict_types=1);

namespace Gcego\Registry\Exceptions;

use InvalidArgumentException;

class DiscoveryException extends InvalidArgumentException implements RegistryException
{
    /**
     * @param  int|string  $index  A position in gcego-registry.discovery.roots, or a label for a registered root.
     */
    public static function invalidRoot(int|string $index, string $reason): self
    {
        return new self(sprintf('Invalid discovery root %s: %s', is_int($index) ? 'gcego-registry.discovery.roots.'.$index : $index, $reason));
    }

    public static function rootsFrozen(string $path): self
    {
        return new self(sprintf(
            'Discovery root %s was added after discovery started. Add roots from a service provider\'s register() method, with callAfterResolving(DiscoveryRoots::class, ...).',
            $path,
        ));
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
