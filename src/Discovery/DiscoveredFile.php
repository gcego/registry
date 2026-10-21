<?php

declare(strict_types=1);

namespace Gcego\Registry\Discovery;

/**
 * A definition entrypoint found under a discovery root.
 *
 * $root is the first root that matched it. $alternatives are later roots of
 * other kinds whose entrypoint convention also matches the file (two
 * packages sharing a folder); the registrar uses the one whose registered
 * kind accepts the loaded definition.
 */
final readonly class DiscoveredFile
{
    /**
     * @param  list<DiscoveryPath>  $alternatives
     */
    public function __construct(
        public string $path,
        public string $relativePath,
        public DiscoveryPath $root,
        public array $alternatives = [],
    ) {}

    /**
     * Copy with another candidate root, unless a root of that kind is already a candidate.
     */
    public function withAlternative(DiscoveryPath $root): self
    {
        foreach ($this->roots() as $candidate) {
            if ($candidate->kind === $root->kind) {
                return $this;
            }
        }

        return new self($this->path, $this->relativePath, $this->root, [...$this->alternatives, $root]);
    }

    /**
     * The first root, then the alternatives, in configuration order.
     *
     * @return list<DiscoveryPath>
     */
    public function roots(): array
    {
        return [$this->root, ...$this->alternatives];
    }
}
