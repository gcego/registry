<?php

declare(strict_types=1);

namespace Gcego\Registry\Discovery;

use Gcego\Registry\Exceptions\DiscoveryException;

/**
 * Which PHP files under a discovery root are definition entrypoints.
 *
 *  - "directory": a file named after its directory (Subjects/Subjects.php);
 *    sibling files (Table.php, Form.php) are supporting code. Files directly
 *    in the root are entrypoints too (a single-file definition).
 *  - "file": every PHP file is an entrypoint (Pages/Dashboard.php).
 *  - any other value: a regular expression matched against the path relative
 *    to the root, using "/" separators (e.g. "#(^|/)[^/]+\.definition\.php$#").
 */
final readonly class Entrypoint
{
    public const string Directory = 'directory';

    public const string File = 'file';

    private function __construct(public string $strategy) {}

    public static function fromConfig(string $strategy, int|string $root): self
    {
        if ($strategy !== self::Directory && $strategy !== self::File && @preg_match($strategy, '') === false) {
            throw DiscoveryException::invalidRoot($root, sprintf('entrypoint must be "directory", "file" or a valid regular expression, "%s" given.', $strategy));
        }

        return new self($strategy);
    }

    /**
     * @param  string  $relativePath  Path relative to the root, "/"-separated, ending in ".php".
     */
    public function matches(string $relativePath): bool
    {
        return match ($this->strategy) {
            self::File => true,
            self::Directory => $this->matchesDirectory($relativePath),
            default => preg_match($this->strategy, $relativePath) === 1,
        };
    }

    private function matchesDirectory(string $relativePath): bool
    {
        $segments = explode('/', $relativePath);

        if (count($segments) === 1) {
            return true;
        }

        return basename($relativePath, '.php') === $segments[count($segments) - 2];
    }
}
