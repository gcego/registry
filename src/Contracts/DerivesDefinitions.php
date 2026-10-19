<?php

declare(strict_types=1);

namespace Gcego\Registry\Contracts;

/**
 * A definition that brings further definitions with it: a module declares
 * resources, and each resource becomes the pages that manage it.
 *
 * The registry indexes the derived definitions next to the definition, with
 * the same source and scope, and loads them lazily the same way (by loading
 * the source again and deriving). Each derived definition is a complete
 * definition of a registered kind, validated like any other.
 *
 * Deriving must be pure and deterministic: no queries, no routes, no I/O.
 */
interface DerivesDefinitions
{
    /**
     * @return iterable<object>
     */
    public function derivedDefinitions(): iterable;
}
