<?php

declare(strict_types=1);

namespace Gcego\Registry\Contracts;

use Gcego\Registry\Exceptions\DefinitionNotFoundException;
use Gcego\Registry\Exceptions\RegistryException;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Registry\Index\DefinitionIndex;
use Gcego\Registry\Index\SourceType;

/**
 * Lookup of registered definitions.
 *
 * Identifiers are "kind:name", "kind:scope.name", or a bare "name" that is
 * unique among unscoped definitions. Metadata methods never load
 * definitions; get() loads lazily and validates.
 */
interface RegistryInterface
{
    public function has(string $identifier): bool;

    /**
     * Metadata only; never loads the definition.
     *
     * @throws DefinitionNotFoundException
     */
    public function descriptor(string $identifier): DefinitionDescriptor;

    /**
     * Load (once), validate and return the definition.
     *
     * @template T of object
     *
     * @param  class-string<T>|null  $expected  Require the definition to be an instance of this class.
     * @return ($expected is null ? object : T)
     *
     * @throws RegistryException
     */
    public function get(string $identifier, ?string $expected = null): object;

    /**
     * @return list<DefinitionDescriptor>
     */
    public function descriptors(?string $kind = null, ?string $scope = null, ?SourceType $sourceType = null, ?string $location = null): array;

    /**
     * @return list<string>
     */
    public function names(?string $kind = null): array;

    public function count(?string $kind = null): int;

    public function index(): DefinitionIndex;

    /**
     * Register a definition object at runtime (not cached).
     */
    public function register(object $definition, ?string $scope = null): DefinitionDescriptor;

    /**
     * Forget the index and loaded definitions; the next lookup rebuilds or rereads them.
     */
    public function refresh(): void;
}
