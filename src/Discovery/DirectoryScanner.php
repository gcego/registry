<?php

declare(strict_types=1);

namespace Gcego\Registry\Discovery;

use Gcego\Registry\Exceptions\DiscoveryException;

/**
 * Iteratively scans one discovery root for entrypoint files.
 *
 * - Only regular ".php" files whose real path stays inside the root are considered.
 * - Symbolic links (files and directories) are never followed.
 * - Names starting with "." or "_", and configured ignored directory names, are skipped.
 * - Results are sorted by relative path for deterministic ordering.
 */
final readonly class DirectoryScanner
{
    /**
     * @param  list<string>  $ignoredDirectories  Directory names never descended into, e.g. ["Tests", "Fixtures"].
     */
    public function __construct(
        private array $ignoredDirectories = [],
    ) {}

    /**
     * @return array{files: list<DiscoveredFile>, skipped: list<array{path: string, reason: string}>}
     *
     * @throws DiscoveryException
     */
    public function scan(DiscoveryPath $root, string $realRoot): array
    {
        $files = [];
        $skipped = [];
        $stack = [''];

        while ($stack !== []) {
            $relativeDirectory = array_pop($stack);
            $directory = $relativeDirectory === '' ? $realRoot : $realRoot.'/'.$relativeDirectory;
            $entries = @scandir($directory);

            if ($entries === false) {
                $skipped[] = ['path' => $directory, 'reason' => 'directory is not readable'];

                continue;
            }

            $subdirectories = [];

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $path = $directory.'/'.$entry;
                $relative = $relativeDirectory === '' ? $entry : $relativeDirectory.'/'.$entry;

                if (str_starts_with($entry, '.') || str_starts_with($entry, '_')) {
                    continue;
                }

                if (is_link($path)) {
                    $skipped[] = ['path' => $path, 'reason' => 'symbolic links are not followed'];

                    continue;
                }

                if (is_dir($path)) {
                    if (in_array($entry, $this->ignoredDirectories, true)) {
                        $skipped[] = ['path' => $path, 'reason' => 'ignored directory name'];

                        continue;
                    }

                    $subdirectories[] = $relative;

                    continue;
                }

                if (! is_file($path) || ! str_ends_with($entry, '.php')) {
                    continue;
                }

                if (! $root->entrypoint->matches($relative)) {
                    $skipped[] = ['path' => $path, 'reason' => 'not an entrypoint ('.$root->entrypoint->strategy.' convention)'];

                    continue;
                }

                $real = realpath($path);

                if ($real === false || ! str_starts_with($real, $realRoot.'/')) {
                    $skipped[] = ['path' => $path, 'reason' => 'resolves outside the discovery root'];

                    continue;
                }

                $files[$relative] = new DiscoveredFile($real, $relative, $root);
            }

            rsort($subdirectories, SORT_STRING);
            array_push($stack, ...$subdirectories);
        }

        ksort($files, SORT_STRING);

        return ['files' => array_values($files), 'skipped' => $skipped];
    }
}
