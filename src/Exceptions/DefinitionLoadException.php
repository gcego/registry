<?php

declare(strict_types=1);

namespace Gcego\Registry\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A definition source that could not be loaded.
 */
class DefinitionLoadException extends RuntimeException implements RegistryException
{
    public static function missingFile(string $path): self
    {
        return new self(sprintf('Definition file %s does not exist. If it was removed, rebuild the registry cache.', $path));
    }

    public static function untrustedPath(string $path): self
    {
        return new self(sprintf('Refusing to load %s: it is outside every configured discovery root.', $path));
    }

    public static function failed(string $source, Throwable $previous): self
    {
        return new self(sprintf(
            'Loading definition %s failed with %s: %s (line %d).',
            $source,
            $previous::class,
            $previous->getMessage(),
            $previous->getLine(),
        ), 0, $previous);
    }

    public static function output(string $path): self
    {
        return new self(sprintf('Definition file %s produced output. Definition files must only return a definition.', $path));
    }

    public static function invalidReturn(string $source, string $type): self
    {
        return new self(sprintf(
            '%s returned %s. A definition file must return a definition object or the class name of a %s.',
            $source,
            $type,
            'Gcego\\Registry\\Contracts\\DefinitionProvider',
        ));
    }

    public static function invalidProvider(string $class): self
    {
        return new self(sprintf('%s does not exist or does not implement Gcego\\Registry\\Contracts\\DefinitionProvider.', $class));
    }

    public static function classConflict(string $path, string $class, string $declaredIn): self
    {
        return new self(sprintf(
            'Definition file %s declares %s, which is already declared by %s. Class names must be unique.',
            $path,
            $class,
            $declaredIn,
        ));
    }

    public static function reloadRequired(string $path): self
    {
        return new self(sprintf(
            'Definition file %s changed after its classes were loaded in this PHP process, and PHP cannot declare a class twice. Restart the process (queue workers, Octane) to load the new version.',
            $path,
        ));
    }

    public static function ambiguousClassEntrypoint(string $path, int $providers): self
    {
        return new self(sprintf(
            'Definition file %s declares classes but did not return a definition, and it declares %d %s classes, so the registry cannot tell which one it provides. Return the class name: `return Subjects::class;`.',
            $path,
            $providers,
            'Gcego\\Registry\\Contracts\\DefinitionProvider',
        ));
    }

    public static function stale(string $identity, string $source): self
    {
        return new self(sprintf(
            'The registry index says %s defines "%s", but it no longer does. Rebuild the registry cache (php artisan gcego:registry-cache).',
            $source,
            $identity,
        ));
    }
}
