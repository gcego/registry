<?php

declare(strict_types=1);

namespace Gcego\Registry\Registration;

use Gcego\Registry\Index\DefinitionIndex;

/**
 * The outcome of building an index from discovered files and providers.
 */
final readonly class RegistrationResult
{
    /**
     * @param  array<string, object>  $definitions  Identity => the object loaded while building (reused to avoid loading twice).
     * @param  list<RegistrationFailure>  $failures
     * @param  list<array{path: string, kind: string}>  $unsupported  Entrypoints whose root kind is not registered.
     */
    public function __construct(
        public DefinitionIndex $index,
        public array $definitions = [],
        public array $failures = [],
        public array $unsupported = [],
    ) {}
}
