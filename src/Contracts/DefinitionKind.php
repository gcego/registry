<?php

declare(strict_types=1);

namespace Gcego\Registry\Contracts;

/**
 * A category of definitions the registry can hold (module, page, widget, ...).
 *
 * Future GCEGO packages register their own kinds; the registry never guesses
 * kinds from class names. A kind decides which objects belong to it, how
 * they are named, how they are validated and what metadata is indexed.
 */
interface DefinitionKind
{
    /**
     * Lowercase identifier used in identities ("module" in "module:subjects").
     */
    public function id(): string;

    /**
     * The interface or class every definition of this kind implements.
     *
     * @return class-string
     */
    public function contract(): string;

    /**
     * The definition's logical name, read from the definition itself.
     *
     * @throws \Throwable when the definition has no valid name.
     */
    public function name(object $definition): string;

    /**
     * Validation errors as readable strings (empty when valid).
     *
     * Delegate to the definition's own validation engine; do not reimplement it.
     *
     * @return list<string>
     */
    public function validate(object $definition): array;

    /**
     * Scalar metadata indexed with the descriptor, available without loading the definition.
     *
     * @return array<string, string|int|float|bool|null>
     */
    public function metadata(object $definition): array;
}
