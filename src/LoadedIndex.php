<?php

declare(strict_types=1);

namespace Gcego\Registry;

use Gcego\Registry\Index\DefinitionIndex;
use Gcego\Registry\Registration\RegistrationFailure;

/**
 * An index ready for use, with how it was obtained.
 */
final readonly class LoadedIndex
{
    public const string FromCache = 'cache';

    public const string FromDiscovery = 'discovery';

    /**
     * @param  array<string, object>  $definitions  Objects already loaded while building (empty when read from cache).
     * @param  list<RegistrationFailure>  $failures
     */
    public function __construct(
        public DefinitionIndex $index,
        public string $origin,
        public array $definitions = [],
        public array $failures = [],
    ) {}
}
