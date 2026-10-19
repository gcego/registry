<?php

declare(strict_types=1);

namespace Gcego\Registry\Contracts;

use Closure;
use Gcego\Registry\Cache\Manifest;
use Gcego\Registry\Exceptions\RegistryCacheException;

/**
 * Persistent storage for the compiled registry manifest.
 */
interface RegistryCache
{
    public function path(): string;

    public function exists(): bool;

    /**
     * @return Manifest|null Null when no cache exists.
     *
     * @throws RegistryCacheException when the cache exists but is unreadable or corrupt.
     */
    public function read(): ?Manifest;

    /**
     * Write atomically: readers see the old or the new manifest, never a partial one.
     *
     * @throws RegistryCacheException
     */
    public function write(Manifest $manifest): void;

    public function clear(): bool;

    /**
     * Run $callback while holding an exclusive rebuild lock.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function locked(Closure $callback): mixed;
}
