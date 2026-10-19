# Definition kinds

The registry is generic: a **kind** tells it which objects belong to a
category, how they are named, validated and described. `blueprint` is built
in; future packages (pages, widgets, panels, ...) add theirs.

## The contract

```php
interface DefinitionKind
{
    public function id(): string;                        // "page"
    public function contract(): string;                  // class-string every definition implements
    public function name(object $definition): string;    // read from the definition
    public function validate(object $definition): array; // list<string> errors; delegate to the definition's engine
    public function metadata(object $definition): array; // scalar metadata indexed without loading
}
```

`tests/Fixtures/PageKind.php` is a working example over a plain value class:

```php
final readonly class PageKind implements DefinitionKind
{
    public function id(): string { return 'page'; }
    public function contract(): string { return PageDefinition::class; }
    public function name(object $definition): string { return $this->page($definition)->name; }
    public function validate(object $definition): array
    {
        return $this->page($definition)->title === '' ? ['title: a page needs a title'] : [];
    }
    public function metadata(object $definition): array { return ['title' => $this->page($definition)->title]; }
    // ...
}
```

## Registering a kind

Register it in your package provider's `register()` method, before the
registry is first used. Resolving `DefinitionKinds` is cheap and loads nothing:

```php
public function register(): void
{
    $this->app->make(DefinitionKinds::class)->register(new PageKind);
}
```

Do not use `afterResolving()` for this. If `DefinitionKinds` has already been
resolved, Laravel never calls the callback and the kind would silently be
missing. If the registry's index was built before your kind was registered,
call `Registry::refresh()`.

The default configuration already points `app/GCEGo/Pages` at the `page` kind,
so applications need no configuration change once your package is installed.

Rules:

- **Identifiers:** kind ids are lowercase identifiers.
- **Contracts:** a kind's contract must exist.
- **Duplicates:** registering the same kind class twice is a no-op; a different
  class claiming the same id throws `DuplicateDefinitionException`.
- **Fingerprint:** registered kind ids are part of the cache fingerprint.
  Installing a new kind therefore makes a verified cache rebuild; in production,
  run `php artisan optimize`.

## Choosing a kind for an object

- **Discovered files:** the root's `kind` decides, and the object must
  implement its contract.
- **Providers and runtime registrations:** the single kind whose contract the
  object implements. No match, or an ambiguous match, is an
  `InvalidDefinitionException`.

## Validation adapters

A kind should delegate validation to its definition's own engine. The built-in
`BlueprintKind` calls `gcego/blueprints`' `BlueprintValidator` and formats each
error as `path: message [code]`, so the registry never duplicates component or
blueprint rules.


## Definitions that derive definitions

A definition implementing `Gcego\Registry\Contracts\DerivesDefinitions` returns further definitions from `derivedDefinitions()`. gcego/modules uses this: a module derives the pages of its resources.

- **Indexing:** each derived definition is indexed under its own kind, matched by contract, with the same source and scope as its parent.
- **Loading:** it is loaded lazily by loading the source again and deriving.
- **Runtime registration:** `Registry::register()` registers the derived definitions together with their parent.

Deriving must be pure and deterministic. A derived definition with an identity that already exists is a duplicate, like any other.
