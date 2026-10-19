# API reference

All classes live under `Gcego\Registry`.

## `Registry` (final, implements `Contracts\RegistryInterface`)

| Method | Description |
| --- | --- |
| `has(string $identifier): bool` | qualified or bare; never throws |
| `descriptor(string $identifier): DefinitionDescriptor` | metadata only; throws `DefinitionNotFoundException` |
| `get(string $identifier, ?class-string $expected = null): object` | lazy, validated, memoized |
| `blueprint(string $name): BlueprintInterface` | `get('blueprint:'.$name, BlueprintInterface::class)` |
| `descriptors(?string $kind, ?string $scope, ?SourceType $sourceType, ?string $location): list<DefinitionDescriptor>` | filters; `scope: ''` means unscoped |
| `names(?string $kind = null): list<string>` | |
| `count(?string $kind = null): int` | |
| `kinds(): list<string>` | kinds present in the index |
| `index(): DefinitionIndex` | |
| `register(object $definition, ?string $scope = null): DefinitionDescriptor` | runtime registration |
| `failures(): list<RegistrationFailure>` | sources skipped in non-strict mode |
| `origin(): string` | `cache` or `discovery` |
| `refresh(): void` | |

## `RegistryManager` (final readonly)

`load(): LoadedIndex`, `rebuild(?DiscoveryResult $discovery = null): LoadedIndex`,
`build(?DiscoveryResult $discovery = null, ?bool $strict = null): RegistrationResult`,
`clear(): bool`, `status(bool $checkFreshness = true): CacheStatus`,
`discovery(): DefinitionDiscovery`, `fingerprint(DiscoveryResult): string`.

## `RegistryConfig` (final readonly)

`static fromArray(array)`, `activeRoots()`. Public `discoveryEnabled`,
`roots`, `ignoredDirectories`, `providers`, `strict`, `validateOnResolve`,
`cacheEnabled`, `cachePath`, `verifyCache` and `whenCacheMissing`.

## Identity and index

| Class | Members |
| --- | --- |
| `Identity\DefinitionIdentity` | `__construct(string $kind, string $name, ?string $scope = null)`, `static fromString()`, `static isQualified()`, `equals()`, `toString()`; pattern constants |
| `Index\DefinitionDescriptor` | public `identity`, `source`, `class`, `metadata`; `kind()`, `name()`, `scope()`, `key()`, `isSameRegistration()`, `toArray()` |
| `Index\DefinitionSource` | public `type` (`SourceType`), `location`; `static file()`, `static provider()`, `static runtime()`, `isCacheable()`, `equals()`, `toString()`, `toArray()` |
| `Index\SourceType` | `File`, `Provider`, `Runtime` |
| `Index\DefinitionIndex` | `has()`, `get()`, `all()`, `ofKind()`, `withName()`, `filter()`, `kinds()`, `identities()`, `names()`, `merge()`, `count()`, iteration, `toArray()`, `static fromArray()` |

## Discovery

| Class | Members |
| --- | --- |
| `Discovery\DiscoveryPath` | `static fromConfig(int\|string $index, array $config)`, public `path`, `kind`, `entrypoint`, `scope`, `required`; `toArray()` |
| `Discovery\Entrypoint` | `static fromConfig(string $strategy, int\|string $root)`, `matches(string $relativePath)`; constants `Directory`, `File` |
| `Discovery\DirectoryScanner` | `__construct(list<string> $ignoredDirectories = [])`, `scan(DiscoveryPath, string $realRoot)` |
| `Discovery\DefinitionDiscovery` | `__construct(list<DiscoveryPath>, DirectoryScanner)`, `roots()`, `discover(): DiscoveryResult` |
| `Discovery\DiscoveryResult` | public `files`, `skipped`, `roots`, `missingRoots`; `fingerprint()` |
| `Discovery\DiscoveredFile` | public `path`, `relativePath`, `root` |

## Loading, registration, resolution

| Class | Members |
| --- | --- |
| `Contracts\DefinitionLoader` | `load(DefinitionSource): list<object>` |
| `Loading\PhpDefinitionLoader` | `load()`, `loadFile(string)`, `loadProvider(string $class)`, `isTrusted(string $realPath)` |
| `Contracts\DefinitionProvider` | `definitions(): iterable<object>` |
| `Registration\DefinitionRegistrar` | `build(DiscoveryResult, list<class-string> $providers = [], bool $strict = true): RegistrationResult`, `describe(object, DefinitionKind, DefinitionSource, ?string $scope)`, `kindFor(object, DefinitionSource)` |
| `Registration\RegistrationResult` | public `index`, `definitions`, `failures`, `unsupported` |
| `Registration\RegistrationFailure` | public `source`, `exception`; `toArray()` |
| `Resolution\DefinitionResolver` | `resolve(DefinitionDescriptor): object` |
| `Resolution\RegistryBlueprintResolver` | implements `Gcego\Blueprints\Contracts\BlueprintResolver` |
| `LoadedIndex` | public `index`, `origin`, `definitions`, `failures` |

## Kinds

| Class | Members |
| --- | --- |
| `Contracts\DefinitionKind` | `id()`, `contract()`, `name(object)`, `validate(object)`, `metadata(object)` |
| `Kinds\DefinitionKinds` | `register()`, `has()`, `get()`, `ids()`, `matching(object)` |
| `Kinds\BlueprintKind` | the built-in `blueprint` kind; constant `Id` |

## Cache

| Class | Members |
| --- | --- |
| `Contracts\RegistryCache` | `path()`, `exists()`, `read(): ?Manifest`, `write(Manifest)`, `clear()`, `locked(Closure)` |
| `Cache\FileRegistryCache` | the PHP-file implementation |
| `Cache\Manifest` | public `index`, `fingerprint`, `generatedAt`; `toArray()`, `static fromArray(mixed, string $path)`; constant `Format` |
| `Cache\CacheStatus` | public `enabled`, `path`, `exists`, `valid`, `fresh`, `generatedAt`, `definitions`, `problem`; `toArray()` |

## Diagnostics

| Class | Members |
| --- | --- |
| `Diagnostics\RegistryInspector` | `inspect(): RegistryReport` (never throws; performs a non-strict build) |
| `Diagnostics\RegistryReport` | public `diagnostics`, `countsByKind`, `roots`, `cache`; `withLevel()`, `check(string)`, `isHealthy()`, `toArray()` |
| `Diagnostics\RegistryDiagnostic` | public `check`, `level`, `message`, `details`; `toArray()` |
| `Diagnostics\DiagnosticLevel` | `Ok`, `Warning`, `Error` |

Diagnostic checks: `discovery.root`, `discovery.kind`, `discovery.enabled`,
`discovery.files`, `discovery.skipped`, `registration.failure`,
`registration.unsupported`, `registration.count`, `registration.build` and
`cache`.

## Laravel

- `RegistryServiceProvider` (implements `Gcego\Core\Contracts\PackageInterface`):
  config tag `gcego-registry-config`; commands `gcego:registry-cache` and
  `gcego:registry-clear`, registered with `optimize` / `optimize:clear`.
- Singletons: `Registry` (alias `RegistryInterface`), `RegistryManager`,
  `RegistryConfig`, `DefinitionKinds`, `DefinitionDiscovery`,
  `PhpDefinitionLoader` (alias `DefinitionLoader`), `FileRegistryCache` (alias
  `RegistryCache`), `DefinitionResolver` and `BlueprintResolver`.
  `DefinitionRegistrar` and `RegistryInspector` are autowired.

## Exceptions (all implement `Exceptions\RegistryException`)

| Exception | Base | Typical cause |
| --- | --- | --- |
| `DuplicateDefinitionException` | `LogicException` | identity defined by two sources; conflicting kind |
| `DefinitionNotFoundException` | `OutOfBoundsException` | unknown or ambiguous identifier |
| `InvalidDefinitionException` | `InvalidArgumentException` | invalid identity, wrong contract or type, validation failure, unnamed, no matching kind; `errors()` |
| `DefinitionLoadException` | `RuntimeException` | missing, untrusted or broken file; bad provider; stale index |
| `DiscoveryException` | `InvalidArgumentException` | invalid configuration; missing required root |
| `RegistryCacheException` | `RuntimeException` | corrupt, missing (fail policy) or unwritable cache |
