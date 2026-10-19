# gcego/registry

Finds, validates, indexes and resolves GCEGO definitions, so application
developers never write registration code.

Create a file:

```php
<?php // app/GCEGo/Modules/Academics/Subjects/Subjects.php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Components\Property;

return new Blueprint(
    new Property(Blueprint::Name, 'subjects'),
    new Property(Blueprint::Title, 'Subject Management'),
);
```

Use it anywhere:

```php
use Gcego\Registry\Registry;

$registry = app(Registry::class);

$registry->blueprint('subjects');          // the validated Blueprint
$registry->get('blueprint:subjects');      // same, by identity
$registry->descriptor('subjects');         // metadata only; does not execute the file
$registry->names('blueprint');             // ['subjects', ...]
```

Helper files next to an entrypoint, such as `Subjects/Table.php`, are never
loaded as definitions.

## Requirements

- PHP 8.3+, Laravel 13
- `gcego/components`, `gcego/blueprints` and `gcego/core` ^0.1

## Installation

```bash
composer require gcego/registry
php artisan vendor:publish --tag=gcego-registry-config   # optional
```

Laravel auto-discovers `Gcego\Registry\RegistryServiceProvider`. The provider
is also a `gcego/core` package (`gcego/registry`, capability `registry`).

## Conventions

| Directory | Kind | Entrypoints |
| --- | --- | --- |
| `app/GCEGo/Modules` | `blueprint` | a file named after its folder: `Academics/Subjects/Subjects.php` |
| `app/GCEGo/Pages` | `page` | every PHP file |
| `app/GCEGo/Widgets` | `widget` | every PHP file |
| `app/GCEGo/Layouts` | `layout` | every PHP file |
| `app/GCEGo/Actions` | `action` | every PHP file |
| `app/GCEGo/Navigation` | `navigation` | every PHP file |
| `app/GCEGo/Panels` | `panel` | every PHP file |

Only `blueprint` is registered by this package. The other kinds start working
when the GCEGO packages that define them are installed; until then their
directories are ignored and reported by the inspector. Missing directories are
fine. Everything is configurable (see [discovery](docs/discovery.md)).

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
composer test       # Pest + Testbench
composer analyse    # PHPStan (Larastan), level max
composer format     # Pint
composer benchmark  # discovery, build, manifest I/O, lookups
```

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
