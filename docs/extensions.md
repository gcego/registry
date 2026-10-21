# Definition kinds

A kind tells the registry which objects belong to a category, how they are
named, how they are validated, and which metadata to index. No kinds are built
in. Applications define and register their own using registry contracts.
Only `gcego/core:^1.0` is required from the GCEGO ecosystem.

## Define an application object

```php
<?php // app/Definitions/PageDefinition.php

namespace App\Definitions;

final readonly class PageDefinition
{
    public function __construct(
        public string $name,
        public string $title,
    ) {}
}
```

## Implement a kind

```php
<?php // app/Definitions/PageKind.php

namespace App\Definitions;

use Gcego\Registry\Contracts\DefinitionKind;
use InvalidArgumentException;

final readonly class PageKind implements DefinitionKind
{
    public function id(): string
    {
        return 'page';
    }

    public function contract(): string
    {
        return PageDefinition::class;
    }

    public function name(object $definition): string
    {
        return $this->page($definition)->name;
    }

    public function validate(object $definition): array
    {
        return $this->page($definition)->title === ''
            ? ['title: a page needs a title']
            : [];
    }

    public function metadata(object $definition): array
    {
        return ['title' => $this->page($definition)->title];
    }

    private function page(object $definition): PageDefinition
    {
        return $definition instanceof PageDefinition
            ? $definition
            : throw new InvalidArgumentException('Expected a page definition.');
    }
}
```

The contract must be an existing class or interface. Validation returns a list
of readable errors. Metadata keys are application-defined, and values must be
strings, integers, floats, booleans or null.

## Register the kind

Add the kind in your application service provider before the registry is first
used. Resolving `DefinitionKinds` does not scan or load definition files:

```php
use App\Definitions\PageKind;
use Gcego\Registry\Kinds\DefinitionKinds;

public function register(): void
{
    $this->app->make(DefinitionKinds::class)->register(new PageKind);
}
```

The default configuration already maps `app/GCEGo/Pages` to `page`.
A file in that directory can now return a `PageDefinition`:

```php
<?php // app/GCEGo/Pages/home.php

use App\Definitions\PageDefinition;

return new PageDefinition('home', 'Home');
```

Resolve it through the facade:

```php
use App\Definitions\PageDefinition;
use Gcego\Registry\Facades\Registry;

Registry::get('page:home', PageDefinition::class);
Registry::descriptor('page:home')->metadata; // ['title' => 'Home']
```

Register kinds directly rather than through an `afterResolving()` callback
that could miss an already-resolved instance. If the registry index was built
before a new kind was registered, call `Registry::refresh()` using the facade.

## Registration rules

- Kind ids are lowercase identifiers.
- Registering the same kind class twice is a no-op.
- A different class claiming an existing id throws `DuplicateDefinitionException`.
- Registered kind ids contribute to the cache fingerprint. Rebuild production
  manifests with `php artisan optimize` after adding a kind.
- Discovery roots choose the kind, and returned objects must satisfy its contract.
- Providers and runtime registrations match kinds by contract. The most specific
  matching contract wins; no match or equally specific matches are errors.

## Derived definitions

A definition implementing `Gcego\Registry\Contracts\DerivesDefinitions` returns
additional objects from `derivedDefinitions()`. For example, a catalog can derive
its pages. Each derived object needs a registered kind.

- Indexing assigns each derived definition its own identity, with its parent's
  source and scope.
- Lazy resolution reloads the source and derives the objects again.
- `Registry::register()` registers derived objects together with their parent.

Derivation must be deterministic and must not depend on request state.
Duplicate identities are rejected. Expansion supports at most three levels.

Definition files and providers return built objects; the registry does not
execute builders. Applications can wrap `Registry::get($identity, $expectedClass)`
in their own typed resolver, or inject `RegistryInterface` into services.
