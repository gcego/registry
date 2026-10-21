<?php

declare(strict_types=1);

namespace Gcego\Registry;

use Gcego\Registry\Contracts\DerivesDefinitions;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Exceptions\DefinitionNotFoundException;
use Gcego\Registry\Exceptions\DuplicateDefinitionException;
use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Gcego\Registry\Identity\DefinitionIdentity;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Registry\Index\DefinitionIndex;
use Gcego\Registry\Index\DefinitionSource;
use Gcego\Registry\Index\SourceType;
use Gcego\Registry\Loading\DerivedDefinitions;
use Gcego\Registry\Registration\DefinitionRegistrar;
use Gcego\Registry\Registration\RegistrationFailure;
use Gcego\Registry\Resolution\DefinitionResolver;

/**
 * The application's definition registry (a container singleton).
 *
 * Nothing happens at construction. The index is obtained on first use from
 * the RegistryManager (cache or discovery) and kept for the process lifetime;
 * definition objects are loaded lazily on get() and memoized. Both are
 * framework-wide, immutable data, so sharing them across requests in
 * long-running workers is safe. refresh() forgets them.
 */
final class Registry implements RegistryInterface
{
    private ?LoadedIndex $loaded = null;

    private ?DefinitionIndex $index = null;

    /**
     * @var array<string, object>
     */
    private array $definitions = [];

    /**
     * @var array<string, array{DefinitionDescriptor, object}>
     */
    private array $runtime = [];

    public function __construct(
        private readonly RegistryManager $manager,
        private readonly DefinitionResolver $resolver,
        private readonly DefinitionRegistrar $registrar,
    ) {}

    public function has(string $identifier): bool
    {
        try {
            return $this->key($identifier) !== null;
        } catch (DefinitionNotFoundException|InvalidDefinitionException) {
            return false;
        }
    }

    public function descriptor(string $identifier): DefinitionDescriptor
    {
        $key = $this->key($identifier) ?? throw DefinitionNotFoundException::forIdentity($identifier, $this->suggestions($identifier));

        /** @var DefinitionDescriptor */
        return $this->index()->get($key);
    }

    public function get(string $identifier, ?string $expected = null): object
    {
        $descriptor = $this->descriptor($identifier);
        $key = $descriptor->key();

        $definition = $this->definitions[$key] ??= $descriptor->source->type === SourceType::Runtime
            ? $this->runtime[$key][1]
            : $this->resolver->resolve($descriptor);

        if ($expected !== null && ! $definition instanceof $expected) {
            throw InvalidDefinitionException::wrongType($key, $expected, $definition::class);
        }

        return $definition;
    }

    public function descriptors(?string $kind = null, ?string $scope = null, ?SourceType $sourceType = null, ?string $location = null): array
    {
        return $this->index()->filter($kind, $scope, $sourceType, $location);
    }

    public function names(?string $kind = null): array
    {
        return $this->index()->names($kind);
    }

    public function count(?string $kind = null): int
    {
        return $kind === null ? $this->index()->count() : count($this->index()->ofKind($kind));
    }

    /**
     * @return list<string>
     */
    public function kinds(): array
    {
        return $this->index()->kinds();
    }

    public function index(): DefinitionIndex
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $this->loaded ??= $this->manager->load();
        $this->definitions += $this->loaded->definitions;
        $runtime = new DefinitionIndex(...array_map(static fn (array $entry): DefinitionDescriptor => $entry[0], array_values($this->runtime)));

        return $this->index = $this->loaded->index->merge($runtime);
    }

    /**
     * Problems that were skipped while building the index (only when gcego-registry.strict is false).
     *
     * @return list<RegistrationFailure>
     */
    public function failures(): array
    {
        $this->index();

        return $this->loaded === null ? [] : $this->loaded->failures;
    }

    /**
     * Where the current index came from: "cache" or "discovery".
     */
    public function origin(): string
    {
        $this->index();

        return $this->loaded === null ? LoadedIndex::FromDiscovery : $this->loaded->origin;
    }

    /**
     * Register a definition object at runtime, for example from a service provider.
     *
     * The object is validated now; it does not trigger discovery. Registering
     * the same object twice is a no-op; a different object with the same
     * identity is rejected (now, or when the index is merged).
     */
    public function register(object $definition, ?string $scope = null): DefinitionDescriptor
    {
        $known = $this->isRegisteredAtRuntime($definition);
        $descriptor = $this->registerOne($definition, $scope);

        // Definitions it derives (a module's pages) are registered with it, in the same scope.
        if (! $known && $definition instanceof DerivesDefinitions) {
            foreach (DerivedDefinitions::expand([$definition], DefinitionSource::runtime($definition::class)->toString()) as $derived) {
                if ($derived !== $definition) {
                    $this->registerOne($derived, $scope);
                }
            }
        }

        return $descriptor;
    }

    private function isRegisteredAtRuntime(object $definition): bool
    {
        foreach ($this->runtime as [, $registered]) {
            if ($registered === $definition) {
                return true;
            }
        }

        return false;
    }

    private function registerOne(object $definition, ?string $scope): DefinitionDescriptor
    {
        $source = DefinitionSource::runtime($definition::class);
        $descriptor = $this->registrar->describe($definition, $this->registrar->kindFor($definition, $source), $source, $scope);
        $key = $descriptor->key();

        if (isset($this->runtime[$key])) {
            if ($this->runtime[$key][1] === $definition) {
                return $this->runtime[$key][0];
            }

            throw DuplicateDefinitionException::forIdentity($key, $this->runtime[$key][0]->source->toString(), $source->toString());
        }

        if ($this->index !== null && $this->index->has($key)) {
            throw DuplicateDefinitionException::forIdentity($key, $this->index->get($key)?->source->toString() ?? '', $source->toString());
        }

        $this->runtime[$key] = [$descriptor, $definition];
        $this->index = null;

        return $descriptor;
    }

    public function refresh(): void
    {
        $this->loaded = null;
        $this->index = null;
        $this->definitions = [];
    }

    /**
     * Resolve an identifier to an index key.
     *
     * @throws DefinitionNotFoundException when a bare name is ambiguous.
     * @throws InvalidDefinitionException when a qualified identifier is malformed.
     */
    private function key(string $identifier): ?string
    {
        $index = $this->index();

        if (DefinitionIdentity::isQualified($identifier)) {
            $key = DefinitionIdentity::fromString($identifier)->toString();

            return $index->has($key) ? $key : null;
        }

        $candidates = array_values(array_filter(
            $index->withName($identifier),
            static fn (DefinitionDescriptor $descriptor): bool => $descriptor->scope() === null,
        ));

        if (count($candidates) > 1) {
            throw DefinitionNotFoundException::ambiguous($identifier, array_map(static fn (DefinitionDescriptor $descriptor): string => $descriptor->key(), $candidates));
        }

        return ($candidates[0] ?? null)?->key();
    }

    /**
     * @return list<string>
     */
    private function suggestions(string $identifier): array
    {
        $suggestions = [];

        foreach ($this->index()->identities() as $candidate) {
            $name = substr($candidate, (int) strrpos($candidate, ':') + 1);

            if (levenshtein($identifier, $candidate) <= 2 || levenshtein($identifier, $name) <= 2) {
                $suggestions[] = $candidate;
            }
        }

        return array_slice($suggestions, 0, 5);
    }
}
