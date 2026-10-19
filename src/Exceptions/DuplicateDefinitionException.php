<?php

declare(strict_types=1);

namespace Gcego\Registry\Exceptions;

use LogicException;

class DuplicateDefinitionException extends LogicException implements RegistryException
{
    public static function forIdentity(string $identity, string $existingSource, string $newSource): self
    {
        return new self(sprintf(
            "Definition \"%s\" is defined twice.\n\nFirst:  %s\nSecond: %s\n\nRename one of them (definition names must be unique per kind and scope).",
            $identity,
            $existingSource,
            $newSource,
        ));
    }

    public static function forKind(string $kind, string $existingClass, string $newClass): self
    {
        return new self(sprintf('Definition kind "%s" is already registered by %s; %s cannot replace it.', $kind, $existingClass, $newClass));
    }
}
