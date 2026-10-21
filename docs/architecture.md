# Architecture

## Responsibilities

The registry manages definitions: it finds them, loads them, validates them
(by delegating), indexes them, caches the index, and resolves definitions by
identity. It never renders, routes, queries or authorizes anything.

```text
application definitions ──▶ gcego/registry ──▶ gcego/core ──▶ illuminate/*
```

The only GCEGO dependency is `gcego/core:^1.0`. Composer installs its published
release, which supplies the package lifecycle contracts. Laravel supplies the container, configuration, filesystem
and console integration. Applications own their `DefinitionKind`
adapters, validation engines, builder execution and typed resolver bindings.
The registry starts with no kinds. Tests and benchmarks use local plain PHP
definitions, and no local Composer path repositories are required.

## Facade and container

Application examples use `Gcego\Registry\Facades\Registry`. Laravel resolves
its calls to the `Gcego\Registry\Registry` singleton. Constructor injection
through `Gcego\Registry\Contracts\RegistryInterface` uses that same instance.
The facade adds no separate definition index or cache.

## Identity

```text
kind:name          module:subjects
kind:scope.name    module:admin.subjects
```

- **kind:** chosen by the discovery root or matched by contract.
- **name:** read from the definition itself, through the kind
  (for example, an application definition's `name()` method), never from the file path.
- **scope:** optional, set per root (for example a future panel).

All parts are lowercase identifiers, so comparison is exact. The same
identity from two different sources is always an error that names both
sources. The same identity from the same source is collapsed, which makes
registration idempotent.

## The PHP-identity problem and the chosen strategy

A file that returns `new ModuleDefinition(...)` only reveals its name when executed.
The registry does not try to parse PHP. Instead:

1. **Build/index phase.** Discovery lists entrypoints by path convention. Each
   trusted entrypoint is executed once, and the returned object is checked
   against its kind, named and validated. The resulting identity, source,
   class and scalar metadata are recorded in a descriptor.
2. **Manifest.** Descriptors (scalars only) are written to a compiled PHP
   manifest.
3. **Request phase (production).** Metadata comes from the manifest. A
   definition file is executed only when `get()` asks for that definition. The
   returned object must match the recorded kind, name and class, otherwise the
   index is stale and the error says to rebuild.

The cost is that building the index executes every definition file. That is
acceptable because these files are trusted application code, and it is what
makes metadata-only lookups free afterwards.

## Lifecycle

```text
register()   bindings only; nothing scanned or loaded
first use    Registry::index()
               └─ RegistryManager::load()
                    ├─ cache disabled          → discover + build (strict per config)
                    ├─ cache + verify          → scan, compare fingerprint, read or rebuild
                    ├─ cache, no verify        → read manifest
                    └─ missing/corrupt cache   → rebuild ("build") or throw ("fail")
get($id)     descriptor → DefinitionResolver → loader → kind check → validation → memoized
refresh()    forget index and loaded objects; next use repeats "first use"
```

## Main classes

| Class | Role |
| --- | --- |
| `Facades\Registry` | application facade for the shared registry |
| `Registry` | service implementing `RegistryInterface`; holds the index and memoized definitions |
| `RegistryManager` | cache and discovery policy, rebuild, status, fingerprint |
| `RegistryConfig` | validated view of `config('gcego-registry')` |
| `Discovery\DefinitionDiscovery`, `DirectoryScanner`, `DiscoveryPath`, `Entrypoint` | scanning by convention |
| `Loading\PhpDefinitionLoader` | trusted file and provider loading |
| `Registration\DefinitionRegistrar` | load, check, name, validate, describe, detect duplicates |
| `Resolution\DefinitionResolver` | lazy loading with stale detection |
| `Index\DefinitionIndex`, `DefinitionDescriptor`, `DefinitionSource` | immutable index |
| `Kinds\DefinitionKinds` | definition kind registry |
| `Cache\FileRegistryCache`, `Manifest` | compiled manifest storage |
| `Diagnostics\RegistryInspector` | structured diagnostics |

### Decisions and deviations from the plan

- **`RegistryManager` absorbs the planned `RegistryCacheManager`**, because
  cache policy and index building are one decision.
- **`DefinitionProvider` replaces the planned `Registrable` and `Discoverable`.**
  Class-based definitions are explicit: they are listed in configuration or
  returned by name from a discovered file. Classes are never scanned.
- **No `ResolutionResult` or `DefinitionLoadResult`.** Resolution returns the
  definition or throws; build results are `RegistrationResult` and `LoadedIndex`.
- **Two thin Artisan commands** (`gcego:registry-cache`, `gcego:registry-clear`)
  exist only so that `php artisan optimize` can build the cache on deploy.
  Applications can provide richer developer commands.
- **Registry diagnostics** are exposed through `RegistryInspector`.

## Long-running workers

`Registry` is a container singleton. It holds the index (immutable
descriptors) and memoized definition objects. Definitions should be immutable
application configuration, without request-,
user- or tenant-specific state. A test confirms
no `Request` is reachable from the registry after handling requests. The registry service classes hold no static mutable state. Call
`Registry::refresh()` through the facade or restart workers after
deploying new definitions; with `cache.verify = false`, run
`php artisan optimize` first.

## Security

- Definition files are trusted application code. Including them is **not** a
  sandbox. Files are only included when their real path lies inside a
  configured discovery root. Paths never come from requests, symlinks are not
  followed during discovery, and remote loading does not exist.
- The cache contains only scalars written with `var_export`, and is checksum-
  and structure-verified on read. Nothing is unserialized.
- Descriptors never contain objects, closures, models or services.
