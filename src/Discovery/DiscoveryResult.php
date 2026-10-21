<?php

declare(strict_types=1);

namespace Gcego\Registry\Discovery;

use Gcego\Registry\Cache\DependencyFiles;

/**
 * The outcome of scanning the discovery roots, in deterministic order.
 */
final readonly class DiscoveryResult
{
    /**
     * @param  list<DiscoveredFile>  $files  Entrypoints: roots in configuration order, paths sorted within a root.
     * @param  list<array{path: string, reason: string}>  $skipped  Files and directories deliberately not used.
     * @param  list<DiscoveryPath>  $roots  Existing roots that were scanned.
     * @param  list<DiscoveryPath>  $missingRoots  Optional roots that do not exist.
     */
    public function __construct(
        public array $files = [],
        public array $skipped = [],
        public array $roots = [],
        public array $missingRoots = [],
    ) {}

    /**
     * A stable hash of what was discovered and its content (paths and file hashes).
     *
     * Content is hashed rather than compared by size and modification time,
     * which misses edits made within the same second that keep the size.
     */
    public function fingerprint(): string
    {
        $parts = array_map(static function (DiscoveredFile $file): string {
            return implode('|', [$file->root->kind, $file->root->scope ?? '', $file->path, DependencyFiles::hash($file->path)]);
        }, $this->files);

        // PHP files that are not entrypoints are supporting code an entrypoint may include
        // (such as Resources/*.php): editing, adding or removing one changes definitions too.
        $supporting = [];

        foreach ($this->skipped as $skipped) {
            if (str_starts_with($skipped['reason'], 'not an entrypoint') && str_ends_with($skipped['path'], '.php') && ! isset($supporting[$skipped['path']])) {
                $supporting[$skipped['path']] = $skipped['path'].'|'.DependencyFiles::hash($skipped['path']);
            }
        }

        ksort($supporting);
        $roots = array_map(static fn (DiscoveryPath $root): string => json_encode($root->toArray(), JSON_THROW_ON_ERROR), [...$this->roots, ...$this->missingRoots]);

        return hash('sha256', implode("\n", [...$roots, '--', ...$parts, '--', ...array_values($supporting)]));
    }
}
