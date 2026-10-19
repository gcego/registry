<?php

declare(strict_types=1);

namespace Gcego\Registry\Tests\Fixtures;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * A throwaway application directory for definition files.
 */
final class Workspace
{
    public readonly string $root;

    public function __construct()
    {
        $root = sys_get_temp_dir().'/gcego-registry-'.bin2hex(random_bytes(6));

        if (! mkdir($root, 0777, true) && ! is_dir($root)) {
            throw new RuntimeException('Cannot create '.$root);
        }

        $this->root = (string) realpath($root);
    }

    public function path(string $relative = ''): string
    {
        return $relative === '' ? $this->root : $this->root.'/'.$relative;
    }

    public function file(string $relative, string $contents): string
    {
        $path = $this->path($relative);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
        clearstatcache(true, $path);

        return $path;
    }

    /**
     * A definition file returning a blueprint with the given name (counted when executed).
     */
    public function blueprint(string $relative, string $name, string $extra = ''): string
    {
        return $this->file($relative, <<<PHP
            <?php

            declare(strict_types=1);

            use Gcego\\Blueprints\\Blueprint;
            use Gcego\\Components\\Property;

            \\Gcego\\Registry\\Tests\\Fixtures\\LoadCounter::hit(__FILE__);

            return new Blueprint(
                new Property(Blueprint::Name, '{$name}'),{$extra}
            );
            PHP);
    }

    public function directory(string $relative): string
    {
        $path = $this->path($relative);

        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }

        return $path;
    }

    public function remove(): void
    {
        if (! is_dir($this->root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $item) {
            if (! $item instanceof SplFileInfo) {
                continue;
            }

            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($this->root);
    }
}
