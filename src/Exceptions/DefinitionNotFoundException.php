<?php

declare(strict_types=1);

namespace Gcego\Registry\Exceptions;

use OutOfBoundsException;

class DefinitionNotFoundException extends OutOfBoundsException implements RegistryException
{
    /**
     * @param  list<string>  $suggestions
     */
    public static function forIdentity(string $identity, array $suggestions = []): self
    {
        return new self(sprintf(
            'Definition "%s" is not registered.%s',
            $identity,
            $suggestions === [] ? '' : ' Did you mean: '.implode(', ', $suggestions).'?',
        ));
    }

    /**
     * @param  list<string>  $candidates
     */
    public static function ambiguous(string $name, array $candidates): self
    {
        return new self(sprintf(
            'Definition name "%s" is ambiguous; use one of: %s.',
            $name,
            implode(', ', $candidates),
        ));
    }
}
