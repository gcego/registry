# gcego/registry

The central discovery, registration, indexing and resolution system for application
definitions. It manages any definition category through application-owned
`DefinitionKind` adapters, including pages, widgets, layouts, actions,
navigation, panels, modules and other application definitions.

## Requirements

- PHP 8.3+, Laravel 13
- `gcego/core` ^1.0

## Installation

```bash
composer require gcego/registry
php artisan vendor:publish --tag=gcego-registry-config   # optional
```

Composer installs `gcego/core` automatically.

The `Gcego\Registry\Facades\Registry` facade resolves the shared registry singleton.
Constructor injection remains available through `Gcego\Registry\Contracts\RegistryInterface`.

Laravel auto-discovers `Gcego\Registry\RegistryServiceProvider`.

## Quick start

Register your kind in an application service provider before the registry is first used:

```php
use App\Definitions\PageKind;
use Gcego\Registry\Kinds\DefinitionKinds;

public function register(): void
{
    $this->app->make(DefinitionKinds::class)->register(new PageKind);
}
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
use App\Definitions\PageDefinition;
use Gcego\Registry\Facades\Registry;

Registry::get('page:home');       // validated definition object
Registry::descriptor('page:home'); // indexed metadata
Registry::names('page');          // ['home', ...]
Registry::register(new PageDefinition('reports', 'Reports')); // runtime registration
```

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
| [Architecture](docs/architecture.md) | Identity, lifecycle and application integration |
| [Discovery](docs/discovery.md) | Roots, entrypoint conventions, the developer workflow |
| [Registration](docs/registration.md) | Files, providers, runtime registration, duplicates |
| [Resolution](docs/resolution.md) | Identifiers, lookup, filtering, lazy loading |
| [Caching](docs/caching.md) | Manifest format, modes, deployment, safety |
| [Extensions](docs/extensions.md) | Adding definition kinds |
| [API reference](docs/api-reference.md) | Facade, contracts and supporting APIs |

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
