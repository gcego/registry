<?php

declare(strict_types=1);

namespace Gcego\Registry\Cache;

/**
 * The state of the persistent registry cache.
 */
final readonly class CacheStatus
{
    public function __construct(
        public bool $enabled,
        public string $path,
        public bool $exists,
        public bool $valid,
        public ?bool $fresh,
        public ?string $generatedAt,
        public ?int $definitions,
        public ?string $problem = null,
    ) {}

    /**
     * @return array{enabled: bool, path: string, exists: bool, valid: bool, fresh: bool|null, generatedAt: string|null, definitions: int|null, problem: string|null}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'path' => $this->path,
            'exists' => $this->exists,
            'valid' => $this->valid,
            'fresh' => $this->fresh,
            'generatedAt' => $this->generatedAt,
            'definitions' => $this->definitions,
            'problem' => $this->problem,
        ];
    }
}
