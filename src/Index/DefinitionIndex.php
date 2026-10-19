<?php

declare(strict_types=1);

namespace Gcego\Registry\Index;

use ArrayIterator;
use Countable;
use Gcego\Registry\Exceptions\DuplicateDefinitionException;
use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Gcego\Registry\Identity\DefinitionIdentity;
use IteratorAggregate;
use Traversable;

/**
 * An immutable, deterministic index of definition descriptors.
 *
 * Identity lookups are single array accesses; kind and name lookups use
 * secondary indexes built once at construction.
 *
 * Duplicate policy: the same identity from the same source is collapsed
 * (idempotent); the same identity from a different source throws.
 *
 * @implements IteratorAggregate<string, DefinitionDescriptor>
 */
final readonly class DefinitionIndex implements Countable, IteratorAggregate
{
    /**
     * @var array<string, DefinitionDescriptor>
     */
    private array $byKey;

    /**
     * @var array<string, list<string>>
     */
    private array $byKind;

    /**
     * @var array<string, list<string>>
     */
    private array $byName;

    /**
     * @throws DuplicateDefinitionException
     */
    public function __construct(DefinitionDescriptor ...$descriptors)
    {
        $byKey = [];
        $byKind = [];
        $byName = [];

        foreach ($descriptors as $descriptor) {
            $key = $descriptor->key();

            if (isset($byKey[$key])) {
                if ($byKey[$key]->isSameRegistration($descriptor)) {
                    continue;
                }

                throw DuplicateDefinitionException::forIdentity($key, $byKey[$key]->source->toString(), $descriptor->source->toString());
            }

            $byKey[$key] = $descriptor;
            $byKind[$descriptor->kind()][] = $key;
            $byName[$descriptor->name()][] = $key;
        }

        $this->byKey = $byKey;
        $this->byKind = $byKind;
        $this->byName = $byName;
    }

    public function has(string $identity): bool
    {
        return isset($this->byKey[$identity]);
    }

    public function get(string $identity): ?DefinitionDescriptor
    {
        return $this->byKey[$identity] ?? null;
    }

    /**
     * @return list<DefinitionDescriptor>
     */
    public function all(): array
    {
        return array_values($this->byKey);
    }

    /**
     * @return list<DefinitionDescriptor>
     */
    public function ofKind(string $kind): array
    {
        return array_map(fn (string $key): DefinitionDescriptor => $this->byKey[$key], $this->byKind[$kind] ?? []);
    }

    /**
     * Descriptors with the given name, in any kind or scope.
     *
     * @return list<DefinitionDescriptor>
     */
    public function withName(string $name): array
    {
        return array_map(fn (string $key): DefinitionDescriptor => $this->byKey[$key], $this->byName[$name] ?? []);
    }

    /**
     * Filter by any combination of kind, scope, source type and source location.
     *
     * Pass $scope = '' to select unscoped definitions only.
     *
     * @return list<DefinitionDescriptor>
     */
    public function filter(?string $kind = null, ?string $scope = null, ?SourceType $sourceType = null, ?string $location = null): array
    {
        $candidates = $kind === null ? $this->all() : $this->ofKind($kind);

        return array_values(array_filter($candidates, static fn (DefinitionDescriptor $descriptor): bool => ($scope === null || ($descriptor->scope() ?? '') === $scope)
            && ($sourceType === null || $descriptor->source->type === $sourceType)
            && ($location === null || $descriptor->source->location === $location)));
    }

    /**
     * @return list<string>
     */
    public function kinds(): array
    {
        return array_keys($this->byKind);
    }

    /**
     * @return list<string> Identity strings, in index order.
     */
    public function identities(): array
    {
        return array_keys($this->byKey);
    }

    /**
     * @return list<string> Definition names, optionally of one kind, in index order.
     */
    public function names(?string $kind = null): array
    {
        $descriptors = $kind === null ? $this->all() : $this->ofKind($kind);

        return array_values(array_unique(array_map(static fn (DefinitionDescriptor $descriptor): string => $descriptor->name(), $descriptors)));
    }

    /**
     * @throws DuplicateDefinitionException
     */
    public function merge(self $other): self
    {
        return new self(...$this->all(), ...$other->all());
    }

    public function count(): int
    {
        return count($this->byKey);
    }

    /**
     * @return Traversable<string, DefinitionDescriptor>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->byKey);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(static fn (DefinitionDescriptor $descriptor): array => $descriptor->toArray(), $this->all());
    }

    /**
     * Rebuild an index from toArray() output, validating every field.
     *
     * @param  array<mixed>  $rows
     *
     * @throws InvalidDefinitionException|DuplicateDefinitionException
     */
    public static function fromArray(array $rows): self
    {
        $descriptors = [];

        foreach ($rows as $position => $row) {
            if (! is_array($row)
                || ! is_string($row['kind'] ?? null)
                || ! is_string($row['name'] ?? null)
                || ! (is_string($row['scope'] ?? null) || ($row['scope'] ?? null) === null)
                || ! is_array($row['source'] ?? null)
                || ! is_string($row['source']['type'] ?? null)
                || ! is_string($row['source']['location'] ?? null)
                || ! is_string($row['class'] ?? null)
                || ! is_array($row['metadata'] ?? null)) {
                throw InvalidDefinitionException::invalidIdentity((string) $position, 'malformed index row.');
            }

            $type = SourceType::tryFrom($row['source']['type']);

            if ($type === null || $type === SourceType::Runtime) {
                throw InvalidDefinitionException::invalidIdentity((string) $position, 'unsupported source type.');
            }

            $metadata = [];

            foreach ($row['metadata'] as $key => $value) {
                if (! is_string($key) || ! ($value === null || is_scalar($value))) {
                    throw InvalidDefinitionException::invalidIdentity((string) $position, 'metadata must be scalar.');
                }

                $metadata[$key] = $value;
            }

            /** @var class-string $class Not autoloaded here: classes are verified when the definition is loaded. */
            $class = $row['class'];

            $scope = $row['scope'] ?? null;

            $descriptors[] = new DefinitionDescriptor(
                new DefinitionIdentity($row['kind'], $row['name'], is_string($scope) ? $scope : null),
                new DefinitionSource($type, $row['source']['location']),
                $class,
                $metadata,
            );
        }

        return new self(...$descriptors);
    }
}
