<?php

declare(strict_types=1);

namespace Gcego\Registry\Index;

use Stringable;

/**
 * Where a definition comes from: a file path, a provider class, or the runtime.
 */
final readonly class DefinitionSource implements Stringable
{
    public function __construct(
        public SourceType $type,
        public string $location,
    ) {}

    public static function file(string $path): self
    {
        return new self(SourceType::File, $path);
    }

    /**
     * @param  class-string  $class
     */
    public static function provider(string $class): self
    {
        return new self(SourceType::Provider, $class);
    }

    /**
     * @param  class-string  $class
     */
    public static function runtime(string $class): self
    {
        return new self(SourceType::Runtime, $class);
    }

    public function isCacheable(): bool
    {
        return $this->type !== SourceType::Runtime;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->location === $other->location;
    }

    public function toString(): string
    {
        return match ($this->type) {
            SourceType::File => 'file '.$this->location,
            SourceType::Provider => 'provider '.$this->location,
            SourceType::Runtime => 'runtime registration of '.$this->location,
        };
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @return array{type: string, location: string}
     */
    public function toArray(): array
    {
        return ['type' => $this->type->value, 'location' => $this->location];
    }
}
