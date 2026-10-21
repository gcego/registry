<?php

declare(strict_types=1);

namespace Gcego\Registry;

use Gcego\Registry\Discovery\DiscoveryPath;
use Gcego\Registry\Exceptions\DiscoveryException;

/**
 * A validated, typed view of config('gcego-registry').
 */
final readonly class RegistryConfig
{
    public const string MissingBuild = 'build';

    public const string MissingFail = 'fail';

    /**
     * @param  list<DiscoveryPath>  $roots
     * @param  list<string>  $ignoredDirectories
     * @param  list<class-string>  $providers
     */
    public function __construct(
        public bool $discoveryEnabled = true,
        public array $roots = [],
        public array $ignoredDirectories = [],
        public array $providers = [],
        public bool $strict = true,
        public bool $validateOnResolve = true,
        public bool $cacheEnabled = false,
        public string $cachePath = '',
        public bool $verifyCache = true,
        public string $whenCacheMissing = self::MissingBuild,
    ) {}

    /**
     * @param  array<mixed>  $config
     *
     * @throws DiscoveryException
     */
    public static function fromArray(array $config): self
    {
        $discovery = self::section($config, 'discovery');
        $cache = self::section($config, 'cache');
        $roots = [];

        foreach (self::listOf($discovery, 'discovery.roots', 'roots') as $index => $root) {
            if (! is_array($root)) {
                throw DiscoveryException::invalidRoot($index, 'each root must be an array with "path" and "kind".');
            }

            $roots[] = DiscoveryPath::fromConfig($index, $root);
        }

        $ignored = self::strings($discovery, 'discovery.ignore', 'ignore');
        $providers = [];

        foreach (self::strings($config, 'providers', 'providers') as $provider) {
            /** @var class-string $provider Existence is checked when the provider is loaded. */
            $providers[] = $provider;
        }

        $whenMissing = $cache['missing'] ?? self::MissingBuild;

        if ($whenMissing !== self::MissingBuild && $whenMissing !== self::MissingFail) {
            throw self::invalid('cache.missing', 'must be "build" or "fail"');
        }

        $cachePath = $cache['path'] ?? '';
        $cacheEnabled = self::bool($cache, 'cache.enabled', 'enabled', false);

        if ($cacheEnabled && (! is_string($cachePath) || $cachePath === '')) {
            throw self::invalid('cache.path', 'must be a file path when the cache is enabled');
        }

        return new self(
            discoveryEnabled: self::bool($discovery, 'discovery.enabled', 'enabled', true),
            roots: $roots,
            ignoredDirectories: $ignored,
            providers: array_values(array_unique($providers)),
            strict: self::bool($config, 'strict', 'strict', true),
            validateOnResolve: self::bool($config, 'validate_on_resolve', 'validate_on_resolve', true),
            cacheEnabled: $cacheEnabled,
            cachePath: is_string($cachePath) ? $cachePath : '',
            verifyCache: self::bool($cache, 'cache.verify', 'verify', true),
            whenCacheMissing: $whenMissing,
        );
    }

    /**
     * Roots that take part in discovery: the configured ones, then $registered
     * (from DiscoveryRoots). None when discovery is disabled.
     *
     * @param  list<DiscoveryPath>  $registered
     * @return list<DiscoveryPath>
     */
    public function activeRoots(array $registered = []): array
    {
        return $this->discoveryEnabled ? [...$this->roots, ...$registered] : [];
    }

    /**
     * @param  array<mixed>  $config
     * @return array<mixed>
     */
    private static function section(array $config, string $key): array
    {
        $section = $config[$key] ?? [];

        if (! is_array($section)) {
            throw self::invalid($key, 'must be an array');
        }

        return $section;
    }

    /**
     * @param  array<mixed>  $config
     * @return array<mixed>
     */
    private static function listOf(array $config, string $label, string $key): array
    {
        $value = $config[$key] ?? [];

        if (! is_array($value)) {
            throw self::invalid($label, 'must be an array');
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $config
     * @return list<string>
     */
    private static function strings(array $config, string $label, string $key): array
    {
        $strings = [];

        foreach (self::listOf($config, $label, $key) as $value) {
            if (! is_string($value) || $value === '') {
                throw self::invalid($label, 'must contain non-empty strings');
            }

            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * @param  array<mixed>  $config
     */
    private static function bool(array $config, string $label, string $key, bool $default): bool
    {
        $value = $config[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        $parsed = is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if (! is_bool($parsed)) {
            throw self::invalid($label, 'must be a boolean');
        }

        return $parsed;
    }

    private static function invalid(string $key, string $reason): DiscoveryException
    {
        return new DiscoveryException(sprintf('Invalid configuration gcego-registry.%s: %s.', $key, $reason));
    }
}
