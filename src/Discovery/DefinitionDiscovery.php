<?php

declare(strict_types=1);

namespace Gcego\Registry\Discovery;

use Gcego\Registry\Exceptions\DiscoveryException;

/**
 * Scans every configured root and merges the results deterministically.
 *
 * Roots are processed in configuration order. A root configured twice (same
 * real path) is scanned once, and a file reachable from two nested roots is
 * attributed to the first root only. When roots of different kinds match the
 * same file, the later ones are recorded as alternatives of that file.
 */
final readonly class DefinitionDiscovery
{
    /**
     * @param  list<DiscoveryPath>  $roots
     */
    public function __construct(
        private array $roots,
        private DirectoryScanner $scanner = new DirectoryScanner,
    ) {}

    /**
     * @return list<DiscoveryPath>
     */
    public function roots(): array
    {
        return $this->roots;
    }

    /**
     * @throws DiscoveryException for a required root that is missing, or a root that is not a directory.
     */
    public function discover(): DiscoveryResult
    {
        $files = [];
        $skipped = [];
        $scanned = [];
        $missing = [];
        $seenRoots = [];
        $seenFiles = [];

        foreach ($this->roots as $root) {
            if (! file_exists($root->path)) {
                if ($root->required) {
                    throw DiscoveryException::missingRequiredRoot($root->path);
                }

                $missing[] = $root;

                continue;
            }

            $real = realpath($root->path);

            if ($real === false || ! is_dir($real) || ! is_readable($real)) {
                throw DiscoveryException::notADirectory($root->path);
            }

            // One folder may hold several kinds (with distinct entrypoint conventions); the same folder configured twice for one kind is scanned once.
            $key = $real."\0".$root->kind."\0".($root->scope ?? '');

            if (isset($seenRoots[$key])) {
                $skipped[] = ['path' => $root->path, 'reason' => 'root already configured'];

                continue;
            }

            $seenRoots[$key] = true;
            $scanned[] = $root;
            $result = $this->scanner->scan($root, $real);
            array_push($skipped, ...$result['skipped']);

            foreach ($result['files'] as $file) {
                if (isset($seenFiles[$file->path])) {
                    $index = $seenFiles[$file->path];
                    $files[$index] = $files[$index]->withAlternative($root);
                    $skipped[] = ['path' => $file->path, 'reason' => 'already discovered under another root'];

                    continue;
                }

                $seenFiles[$file->path] = count($files);
                $files[] = $file;
            }
        }

        return new DiscoveryResult(array_values($files), $skipped, $scanned, $missing);
    }
}
