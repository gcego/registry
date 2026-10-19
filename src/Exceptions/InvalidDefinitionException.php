<?php

declare(strict_types=1);

namespace Gcego\Registry\Exceptions;

use InvalidArgumentException;
use Throwable;

/**
 * A definition, identity or kind that does not satisfy the registry's contracts.
 */
class InvalidDefinitionException extends InvalidArgumentException implements RegistryException
{
    /**
     * @var list<string>
     */
    private array $errors = [];

    public static function invalidIdentity(string $identity, string $reason): self
    {
        return new self(sprintf('Invalid definition identity "%s": %s', $identity, $reason));
    }

    public static function unknownKind(string $kind, string $source): self
    {
        return new self(sprintf(
            'No definition kind "%s" is registered (source: %s). Install the GCEGO package that provides it, or point the discovery root at a registered kind.',
            $kind,
            $source,
        ));
    }

    public static function contractMismatch(string $kind, string $contract, string $actual, string $source): self
    {
        return new self(sprintf(
            '%s returned %s, but definitions of kind "%s" must implement %s.',
            $source,
            $actual,
            $kind,
            $contract,
        ));
    }

    /**
     * @param  list<string>  $kinds
     */
    public static function noKind(string $class, string $source, array $kinds): self
    {
        return new self(sprintf(
            '%s provided %s, which matches no registered definition kind (%s).',
            $source,
            $class,
            $kinds === [] ? 'none registered' : implode(', ', $kinds),
        ));
    }

    /**
     * @param  list<string>  $kinds
     */
    public static function ambiguousKind(string $class, string $source, array $kinds): self
    {
        return new self(sprintf('%s provided %s, which matches several definition kinds (%s).', $source, $class, implode(', ', $kinds)));
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failedValidation(string $identity, string $source, array $errors): self
    {
        $exception = new self(sprintf(
            "Definition \"%s\" (%s) is invalid:\n- %s",
            $identity,
            $source,
            implode("\n- ", $errors),
        ));
        $exception->errors = $errors;

        return $exception;
    }

    public static function wrongType(string $identity, string $expected, string $actual): self
    {
        return new self(sprintf('Definition "%s" is a %s, not a %s.', $identity, $actual, $expected));
    }

    public static function unnamed(string $kind, string $source, Throwable $previous): self
    {
        return new self(sprintf('A "%s" definition from %s has no valid name: %s', $kind, $source, $previous->getMessage()), 0, $previous);
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
