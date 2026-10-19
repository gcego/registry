<?php

declare(strict_types=1);

namespace Gcego\Registry\Index;

use Gcego\Registry\Identity\DefinitionIdentity;

/**
 * Inspectable, cacheable metadata about one registered definition.
 *
 * Holds only scalars: no definition objects, closures, models or services.
 * Reading descriptors never loads a definition.
 *
 * @phpstan-type Metadata array<string, string|int|float|bool|null>
 */
final readonly class DefinitionDescriptor
{
    /**
     * @param  class-string  $class  The class of the definition object.
     * @param  array<string, string|int|float|bool|null>  $metadata  Kind-specific metadata (for blueprints: title, slug, ...).
     */
    public function __construct(
        public DefinitionIdentity $identity,
        public DefinitionSource $source,
        public string $class,
        public array $metadata = [],
    ) {}

    public function kind(): string
    {
        return $this->identity->kind;
    }

    public function name(): string
    {
        return $this->identity->name;
    }

    public function scope(): ?string
    {
        return $this->identity->scope;
    }

    public function key(): string
    {
        return $this->identity->toString();
    }

    /**
     * Whether two descriptors describe the same registration (same identity and source).
     */
    public function isSameRegistration(self $other): bool
    {
        return $this->identity->equals($other->identity) && $this->source->equals($other->source);
    }

    /**
     * @return array{identity: string, kind: string, name: string, scope: string|null, source: array{type: string, location: string}, class: class-string, metadata: array<string, string|int|float|bool|null>}
     */
    public function toArray(): array
    {
        return [
            'identity' => $this->identity->toString(),
            'kind' => $this->identity->kind,
            'name' => $this->identity->name,
            'scope' => $this->identity->scope,
            'source' => $this->source->toArray(),
            'class' => $this->class,
            'metadata' => $this->metadata,
        ];
    }
}
