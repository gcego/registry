# gcego/registry

The central discovery, registration, indexing and resolution system for application
definitions. It manages any definition category through application-owned
`DefinitionKind` adapters, including pages, widgets, layouts, actions,
navigation, panels, modules and other application definitions.

Register your kind before the registry is first used:

```php
use App\Definitions\PageKind;
use Gcego\Registry\Kinds\DefinitionKinds;

$this->app->make(DefinitionKinds::class)
    ->register(new PageKind);
```

A definition file returns an object supported by that kind:

```php
<?php // app/GCEGo/Pages/home.php
use App\Definitions\PageDefinition;

return new PageDefinition(name: 'home', title: 'Home');
```

`PageKind` and `PageDefinition` are application classes; see
[extensions](docs/extensions.md) for a complete adapter example.

```php
use Gcego\Registry\Facades\Registry;

Registry::get('page:home');       // validated definition object
Registry::descriptor('page:home'); // indexed metadata
Registry::names('page');          // ['home', ...]
Registry::register($definition);  // runtime registration
```

## Requirements

- PHP 8.3+, Laravel 13
- `gcego/core` ^1.0 (the only GCEGO runtime dependency)

## Installation

```bash
composer require gcego/registry
php artisan vendor:publish --tag=gcego-registry-config   # optional
```

`gcego/core:^1.0` is installed through Composer; no local path repository is needed.

The `Gcego\Registry\Facades\Registry` facade resolves the shared registry singleton.
Constructor injection remains available through `Gcego\Registry\Contracts\RegistryInterface`.

Laravel auto-discovers `Gcego\Registry\RegistryServiceProvider`. The provider
is also a `gcego/core` package (`gcego/registry`, capability `registry`).

## Conventions

| Directory | Kind | Entrypoints |
| --- | --- | --- |
| `app/GCEGo/Modules` | `module` | a file named after its folder: `Academics/Subjects/Subjects.php` |
| `app/GCEGo/Pages` | `page` | every PHP file |
| `app/GCEGo/Widgets` | `widget` | every PHP file |
| `app/GCEGo/Layouts` | `layout` | every PHP file |
| `app/GCEGo/Actions` | `action` | every PHP file |
| `app/GCEGo/Navigation` | `navigation` | every PHP file |
| `app/GCEGo/Panels` | `panel` | every PHP file |

No definition kinds are built in. Applications register their
kinds through `DefinitionKinds`; unregistered roots are ignored and reported
by the inspector. Missing directories are fine. Any additional kind can use
configured discovery roots (see [discovery](docs/discovery.md)).

A definition file returns a definition object, or the class name of a
`DefinitionProvider` (class-based definitions with constructor injection; see
[registration](docs/registration.md)).

## Caching

| Environment | Behaviour |
| --- | --- |
| local (default `cache.verify = true`) | the first lookup in each process scans the roots (listing and stat only) and rebuilds the cache when a file was added, changed or removed |
| production (`cache.verify = false`) | the compiled manifest is read; directories are never scanned on requests |

`php artisan optimize` builds the cache (`gcego:registry-cache`), and
`php artisan optimize:clear` removes it (`gcego:registry-clear`). Run
`optimize` on every deploy. See [caching](docs/caching.md).

## Diagnostics

```php
app(Gcego\Registry\Diagnostics\RegistryInspector::class)->inspect()->toArray();
```

The report covers roots, discovered entrypoints, loader failures, duplicate
identities, unsupported kinds and cache state.

## Documentation

| | |
| --- | --- |
| [Architecture](docs/architecture.md) | Identity, lifecycle, boundaries, decisions |
| [Discovery](docs/discovery.md) | Roots, entrypoint conventions, the developer workflow |
| [Registration](docs/registration.md) | Files, providers, runtime registration, duplicates |
| [Resolution](docs/resolution.md) | Identifiers, lookup, filtering, lazy loading |
| [Caching](docs/caching.md) | Manifest format, modes, deployment, safety |
| [Extensions](docs/extensions.md) | Adding definition kinds |
| [API reference](docs/api-reference.md) | Every public class and method |

## Development

```bash
composer check      # release checks
composer test       # Pest + Testbench
composer analyse    # PHPStan (Larastan), level max
composer format     # Pint
composer benchmark  # discovery, build, manifest I/O, lookups
```

The package and its test suite use only `gcego/core` from the GCEGO ecosystem.
Test fixtures are plain local PHP objects. Definition files and providers return
built objects, and registered kinds own naming, validation and metadata.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
