<?php

declare(strict_types=1);

namespace Gcego\Registry;

use Gcego\Registry\Cache\CacheStatus;
use Gcego\Registry\Cache\Manifest;
use Gcego\Registry\Contracts\RegistryCache;
use Gcego\Registry\Discovery\DefinitionDiscovery;
use Gcego\Registry\Discovery\DiscoveryResult;
use Gcego\Registry\Exceptions\RegistryCacheException;
use Gcego\Registry\Exceptions\RegistryException;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Registration\DefinitionRegistrar;
use Gcego\Registry\Registration\RegistrationResult;

/**
 * Decides where the index comes from and keeps the cache honest.
 *
 * - Cache disabled: discover, load and validate on first use (development).
 * - Cache enabled, verify on: rescan the roots (directory listing and stat
 *   only, no includes) and rebuild when the fingerprint changed.
 * - Cache enabled, verify off (production): read the manifest; never scan.
 * - Missing or corrupt cache: rebuild ("build") or throw ("fail").
 *
 * Rebuilds are always strict and happen under the cache lock; a second
 * process waiting for the lock reuses the manifest the first one wrote.
 */
final readonly class RegistryManager
{
    public function __construct(
        private RegistryConfig $config,
        private DefinitionDiscovery $discovery,
        private DefinitionRegistrar $registrar,
        private RegistryCache $cache,
        private DefinitionKinds $kinds,
    ) {}

    /**
     * @throws RegistryException
     */
    public function load(): LoadedIndex
    {
        if (! $this->config->cacheEnabled) {
            $result = $this->build($this->discovery->discover(), $this->config->strict);

            return new LoadedIndex($result->index, LoadedIndex::FromDiscovery, $result->definitions, $result->failures);
        }

        $manifest = $this->readOrNull();
        $discovery = null;

        if ($manifest !== null && $this->config->verifyCache) {
            $discovery = $this->discovery->discover();

            if (! hash_equals($manifest->fingerprint, $this->fingerprint($discovery))) {
                $manifest = null;
            }
        }

        if ($manifest !== null) {
            return new LoadedIndex($manifest->index, LoadedIndex::FromCache);
        }

        // A corrupt cache under the "fail" policy has already thrown in readOrNull().
        if ($this->config->whenCacheMissing === RegistryConfig::MissingFail && ! $this->cache->exists()) {
            throw RegistryCacheException::missing($this->cache->path());
        }

        return $this->rebuild($discovery);
    }

    /**
     * Discover, load, validate (strictly) and write the cache. Safe to run concurrently.
     *
     * @throws RegistryException
     */
    public function rebuild(?DiscoveryResult $discovery = null): LoadedIndex
    {
        return $this->cache->locked(function () use ($discovery): LoadedIndex {
            $discovery ??= $this->discovery->discover();
            $fingerprint = $this->fingerprint($discovery);
            $existing = $this->readOrNull();

            if ($existing !== null && hash_equals($existing->fingerprint, $fingerprint)) {
                return new LoadedIndex($existing->index, LoadedIndex::FromCache);
            }

            $result = $this->build($discovery, true);
            $this->cache->write(new Manifest($result->index, $fingerprint, gmdate('Y-m-d\TH:i:s\Z')));

            return new LoadedIndex($result->index, LoadedIndex::FromDiscovery, $result->definitions);
        });
    }

    /**
     * Build an index without touching the cache.
     */
    public function build(?DiscoveryResult $discovery = null, ?bool $strict = null): RegistrationResult
    {
        return $this->registrar->build($discovery ?? $this->discovery->discover(), $this->config->providers, $strict ?? $this->config->strict);
    }

    public function clear(): bool
    {
        return $this->cache->clear();
    }

    /**
     * Inspect the cache without throwing. Freshness is only computed when $checkFreshness is true (it scans the roots).
     */
    public function status(bool $checkFreshness = true): CacheStatus
    {
        $path = $this->cache->path();

        if (! $this->config->cacheEnabled) {
            return new CacheStatus(false, $path, $this->cache->exists(), false, null, null, null);
        }

        try {
            $manifest = $this->cache->read();
        } catch (RegistryCacheException $exception) {
            return new CacheStatus(true, $path, true, false, null, null, null, $exception->getMessage());
        }

        if ($manifest === null) {
            return new CacheStatus(true, $path, false, false, null, null, null);
        }

        $fresh = null;
        $problem = null;

        if ($checkFreshness) {
            try {
                $fresh = hash_equals($manifest->fingerprint, $this->fingerprint($this->discovery->discover()));
            } catch (RegistryException $exception) {
                $problem = $exception->getMessage();
            }
        }

        return new CacheStatus(true, $path, true, true, $fresh, $manifest->generatedAt, $manifest->index->count(), $problem);
    }

    public function discovery(): DefinitionDiscovery
    {
        return $this->discovery;
    }

    /**
     * Everything that determines the index: discovered files (path, size, mtime),
     * roots, providers and registered kinds.
     */
    public function fingerprint(DiscoveryResult $discovery): string
    {
        return hash('sha256', implode("\n", [
            'format:'.Manifest::Format,
            'discovery:'.$discovery->fingerprint(),
            'providers:'.implode(',', $this->config->providers),
            'kinds:'.implode(',', $this->kinds->ids()),
        ]));
    }

    private function readOrNull(): ?Manifest
    {
        try {
            return $this->cache->read();
        } catch (RegistryCacheException $exception) {
            if ($this->config->whenCacheMissing === RegistryConfig::MissingFail) {
                throw $exception;
            }

            return null;
        }
    }
}
