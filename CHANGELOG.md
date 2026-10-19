# Changelog

All notable changes to `gcego/registry` are documented in this file.
The project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Fixed

- The cache fingerprint covers supporting PHP files under discovery roots (files an entrypoint includes, such as gcego/modules' `Resources/*.php`). Editing, adding or removing one now marks the cache stale; before, `cache.verify` kept serving outdated module definitions until `gcego:registry-cache` was run.
- A folder configured for several kinds (e.g. the default blueprint root `app/GCEGo/Modules`, also gcego/modules' module folder) is scanned for each kind; previously the second kind was skipped as "root already configured" and modules were never discovered with the default configuration. The same folder configured twice for one kind is still scanned once.

### Added

- `Contracts\DerivesDefinitions`: a definition may bring further definitions (gcego/modules modules derive their pages). Derived definitions are indexed with their parent's source and scope, matched to their own kind by contract, loaded lazily by reloading the source (`Loading\DerivedDefinitions`, at most 3 levels), and registered together with a runtime registration.
- A default discovery root for layouts: `app/GCEGo/Layouts` (kind `layout`, every PHP file), used once gcego/layouts registers the kind.
- Convention-based discovery (`DefinitionDiscovery`, `DirectoryScanner`, `DiscoveryPath`, `Entrypoint`) with `directory`, `file` and regex entrypoint conventions, ignored names, symlink refusal and deterministic ordering.
- `PhpDefinitionLoader` for trusted files returning definition objects or `DefinitionProvider` class names, plus container-resolved class-based providers.
- `DefinitionIdentity` (`kind:name`, `kind:scope.name`), `DefinitionDescriptor`, `DefinitionSource` and the immutable `DefinitionIndex`.
- `DefinitionRegistrar` with kind contract checks, naming, validation via the kind, duplicate detection and a strict/lenient mode.
- Extensible definition kinds (`DefinitionKind`, `DefinitionKinds`) and the built-in `BlueprintKind`.
- `Registry` with metadata-only lookup, lazy validated resolution, bare-name resolution, filtering, runtime registration and refresh; `RegistryBlueprintResolver` bound to `BlueprintResolver`.
- Compiled PHP manifest cache (`FileRegistryCache`, `Manifest`) with checksum verification, atomic writes, rebuild locking, fingerprint-based verification and `build`/`fail` missing-cache policies (`RegistryManager`).
- `RegistryInspector` diagnostics.
- `RegistryServiceProvider` (a `gcego/core` package), `gcego:registry-cache` / `gcego:registry-clear` commands wired into `php artisan optimize`.
