# Changelog

All notable changes to `gcego/registry` are documented here.
The project follows [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-10-20

Initial stable release.

### Features

- Discovery of application definitions using directory, file and regular-expression conventions.
- Application-owned definition kinds with naming, validation and indexed metadata.
- Definition providers with constructor injection and runtime registration.
- Qualified and scoped identities, duplicate detection and typed lazy resolution.
- Derived definitions indexed and resolved alongside their parent definitions.
- Compiled manifest caching with file-change verification, atomic writes and rebuild locking.
- Registry diagnostics covering discovery, registration and cache health.
- `Gcego\Registry\Facades\Registry` for access to the shared registry singleton.
- Cache commands integrated with Laravel's `optimize` and `optimize:clear`.

### Requirements

- PHP 8.3 or later.
- Laravel 13.
- `gcego/core` ^1.0, installed automatically through Composer.
