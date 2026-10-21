# Resolution

## Identifiers

| Form | Example | Meaning |
| --- | --- | --- |
| qualified | `module:subjects`, `module:admin.subjects` | exact identity |
| bare name | `subjects` | the only unscoped definition with that name, of any kind |

A bare name that matches several unscoped definitions throws
`DefinitionNotFoundException` listing the qualified candidates. A missing
definition throws `DefinitionNotFoundException` with "Did you mean"
suggestions.

## Metadata lookup

```php
use Gcego\Registry\Facades\Registry;
use Gcego\Registry\Index\SourceType;

Registry::has('subjects');
Registry::descriptor('subjects');   // DefinitionDescriptor
Registry::descriptor('subjects')->metadata;   // ['title' => 'Subject Management']
Registry::names();                  // all names, index order
Registry::names('module');
Registry::count();
Registry::count('page');
Registry::kinds();
Registry::descriptors(kind: 'module');
Registry::descriptors(scope: 'admin');      // '' selects unscoped definitions
Registry::descriptors(sourceType: SourceType::Provider);
Registry::descriptors(location: '/full/path/Subjects.php');
Registry::index();                  // the immutable DefinitionIndex
```

Metadata lookups do not load definition objects once the index is available.
The first lookup may execute definition files to build or rebuild that index;
with a valid production manifest, metadata comes directly from the cache.

## Loading

```php
use App\Definitions\ModuleDefinition;
use Gcego\Registry\Facades\Registry;

Registry::get('subjects');                              // object
Registry::get('subjects', ModuleDefinition::class);   // typed; InvalidDefinitionException if not an instance
```

`get()` loads a definition at most once per process:

1. the descriptor is found;
2. its source is loaded:
   - a file is executed, if it is still inside a trusted root;
   - a provider is resolved from the container;
   - a runtime registration is returned as is;
3. the object matching the recorded kind, name and class is selected. If none
   matches, a `DefinitionLoadException` says the index is stale and to rebuild
   it;
4. the object is validated again (`validate_on_resolve`);
5. it is memoized and returned.

Definitions loaded while the index was being built are reused, so in
development nothing is executed twice.

## Typed resolution

Use `Registry::get($identity, $expectedClass)` to check the resolved type.
Applications can wrap this API in their own resolver contract and bind
that adapter in their service provider. The registry binds no package-specific
resolvers.

## Refreshing

`Registry::refresh()` forgets the index and loaded definitions; the next call
reloads them according to the cache policy. Runtime registrations are kept.

For constructor injection, type-hint `Gcego\Registry\Contracts\RegistryInterface`.
It resolves the same singleton used by the facade.
