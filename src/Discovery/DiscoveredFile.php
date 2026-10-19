<?php

declare(strict_types=1);

namespace Gcego\Registry\Discovery;

/**
 * A definition entrypoint found under a discovery root.
 */
final readonly class DiscoveredFile
{
    public function __construct(
        public string $path,
        public string $relativePath,
        public DiscoveryPath $root,
    ) {}
}
