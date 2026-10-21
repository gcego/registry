# Architecture

## Responsibilities

The registry manages definitions: it finds them, loads them, validates them
(by delegating), indexes them, caches the index, and resolves definitions by
identity. It never renders, routes, queries or authorizes anything.

```text
application definitions ──▶ gcego/registry ──▶ gcego/core ──▶ illuminate/*
```

The registry integrates with Laravel through `gcego/core`. Applications define
their own objects and register `DefinitionKind` implementations to supply
naming, validation and indexed metadata. No definition kinds are built in.

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

## Indexing and lazy loading

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

Building the index executes each definition file. With a valid production
manifest, metadata lookups read the index without executing those files.

## Lifecycle

```text
provider register()  bindings only; nothing scanned or loaded
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

## Long-running workers

The registry singleton retains its index and resolved definitions for the
process lifetime. Definitions should be immutable application configuration
and must not retain request, user or tenant state.

After deploying definition changes, rebuild the manifest with
`php artisan optimize` and restart long-running workers. Within a running
process, `Registry::refresh()` clears the loaded index and resolved objects;
the next lookup follows the configured cache policy. Runtime registrations
are retained.

## Security

- Definition files are trusted application code. Including them is **not** a
  sandbox. Files are only included when their real path lies inside a
  configured discovery root. Paths never come from requests, symlinks are not
  followed during discovery, and remote loading does not exist.
- The cache contains only scalars written with `var_export`, and is checksum-
  and structure-verified on read. Nothing is unserialized.
- Descriptors never contain objects, closures, models or services.
