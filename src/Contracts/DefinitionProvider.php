<?php

declare(strict_types=1);

namespace Gcego\Registry\Contracts;

/**
 * Class-based definitions.
 *
 * A provider is resolved through Laravel's container (so it may use
 * constructor injection) and returns one or more definition objects. It is
 * used in two places, never by scanning classes:
 *
 *  - listed in gcego-registry.providers;
 *  - returned by name from a discovered definition file: `return Subjects::class;`.
 */
interface DefinitionProvider
{
    /**
     * @return iterable<object>
     */
    public function definitions(): iterable;
}
