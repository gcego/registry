# Changelog

All notable changes to `gcego/registry` are documented in this file.
The project follows [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-10-20

### Changed

- Core is resolved as `gcego/core:^1.0` from Composer package repositories; no local path repository is required.
- `gcego/core` is the only GCEGO dependency in production and development. Tests and benchmarks use local plain PHP definitions.
- All definition kinds are application-owned. The registry starts with no registered kinds and resolves definitions through `Registry::get()`.
- Definition files and providers return built definition objects.
- The default Modules discovery root uses the generic `module` kind.

### Fixed

- The cache fingerprint covers supporting PHP files under discovery roots (files an entrypoint includes, such as `Resources/*.php`). Editing, adding or removing one now marks the cache stale; before, `cache.verify` kept serving outdated module definitions until `gcego:registry-cache` was run.
- A folder configured for several kinds (e.g. `app/GCEGo/Modules` with separate entrypoint conventions) is scanned for each kind; previously the second kind was skipped as "root already configured" and modules were never discovered with the default configuration. The same folder configured twice for one kind is still scanned once.

### Added

- `Gcego\Registry\Facades\Registry` provides facade access to the shared registry singleton.
- `Contracts\DerivesDefinitions`: a definition may bring further definitions (for example, a catalog deriving its pages). Derived definitions are indexed with their parent's source and scope, matched to their own kind by contract, loaded lazily by reloading the source (`Loading\DerivedDefinitions`, at most 3 levels), and registered together with a runtime registration.
- A default discovery root for layouts: `app/GCEGo/Layouts` (kind `layout`, every PHP file), used once the application registers the kind.
- Convention-based discovery (`DefinitionDiscovery`, `DirectoryScanner`, `DiscoveryPath`, `Entrypoint`) with `directory`, `file` and regex entrypoint conventions, ignored names, symlink refusal and deterministic ordering.
- `PhpDefinitionLoader` for trusted files returning definition objects or `DefinitionProvider` class names, plus container-resolved class-based providers.
- `DefinitionIdentity` (`kind:name`, `kind:scope.name`), `DefinitionDescriptor`, `DefinitionSource` and the immutable `DefinitionIndex`.
- `DefinitionRegistrar` with kind contract checks, naming, validation via the kind, duplicate detection and a strict/lenient mode.
- Extensible definition kinds (`DefinitionKind`, `DefinitionKinds`).
- `Registry` with metadata-only lookup, lazy validated resolution, bare-name resolution, filtering, runtime registration and refresh.
- Compiled PHP manifest cache (`FileRegistryCache`, `Manifest`) with checksum verification, atomic writes, rebuild locking, fingerprint-based verification and `build`/`fail` missing-cache policies (`RegistryManager`).
- `RegistryInspector` diagnostics.
- `RegistryServiceProvider` (a `gcego/core` package), `gcego:registry-cache` / `gcego:registry-clear` commands wired into `php artisan optimize`.
