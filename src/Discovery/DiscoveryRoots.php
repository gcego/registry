<?php

declare(strict_types=1);

namespace Gcego\Registry\Discovery;

use Gcego\Registry\Exceptions\DiscoveryException;

/**
 * Discovery roots added by packages (a container singleton).
 *
 * The registry ships without roots: each package that owns a definition kind
 * adds the folders that hold it, from its service provider's register()
 * method, the same way it registers the kind:
 *
 *     $this->callAfterResolving(DiscoveryRoots::class, static fn (DiscoveryRoots $roots) => $roots->add(app_path('Reports'), 'report'));
 *
 * Registered roots are scanned after the roots in gcego-registry.discovery.roots,
 * in registration order, and only while discovery is enabled. Roots are read
 * once, when discovery is first built; adding one afterwards throws instead of
 * being silently ignored.
 */
final class DiscoveryRoots
{
    /**
     * @var list<DiscoveryPath>
     */
    private array $roots = [];

    private bool $frozen = false;

    /**
     * @param  string  $entrypoint  "directory", "file" or a regular expression (see Entrypoint).
     *
     * @throws DiscoveryException
     */
    public function add(string $path, string $kind, string $entrypoint = Entrypoint::File, ?string $scope = null, bool $required = false): void
    {
        if ($this->frozen) {
            throw DiscoveryException::rootsFrozen($path);
        }

        $root = DiscoveryPath::fromConfig(sprintf('%s (kind "%s", added with DiscoveryRoots::add())', $path, $kind), [
            'path' => $path,
            'kind' => $kind,
            'entrypoint' => $entrypoint,
            'scope' => $scope,
            'required' => $required,
        ]);

        foreach ($this->roots as $existing) {
            if ($existing->toArray() === $root->toArray()) {
                return;
            }
        }

        $this->roots[] = $root;
    }

    /**
     * @return list<DiscoveryPath>
     */
    public function all(): array
    {
        return $this->roots;
    }

    /**
     * Stop accepting roots; called when discovery is built from them.
     *
     * @internal
     */
    public function freeze(): void
    {
        $this->frozen = true;
    }
}
