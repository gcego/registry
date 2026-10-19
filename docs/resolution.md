# Resolution

## Identifiers

| Form | Example | Meaning |
| --- | --- | --- |
| qualified | `blueprint:subjects`, `blueprint:admin.subjects` | exact identity |
| bare name | `subjects` | the only unscoped definition with that name, of any kind |

A bare name that matches several unscoped definitions throws
`DefinitionNotFoundException` listing the qualified candidates. A missing
definition throws `DefinitionNotFoundException` with "Did you mean"
suggestions.

## Metadata only (no file is executed)

```php
$registry->has('subjects');
$registry->descriptor('subjects');   // DefinitionDescriptor
$registry->descriptor('subjects')->metadata;   // ['title' => ..., 'slug' => ..., 'roles' => 'table,action', ...]
$registry->names();                  // all names, index order
$registry->names('blueprint');
$registry->count();
$registry->count('page');
$registry->kinds();
$registry->descriptors(kind: 'blueprint');
$registry->descriptors(scope: 'admin');      // '' selects unscoped definitions
$registry->descriptors(sourceType: SourceType::Provider);
$registry->descriptors(location: '/full/path/Subjects.php');
$registry->index();                  // the immutable DefinitionIndex
```

## Loading

```php
$registry->get('subjects');                              // object
$registry->get('subjects', BlueprintInterface::class);   // typed; InvalidDefinitionException if not an instance
$registry->blueprint('subjects');                        // BlueprintInterface
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

## For runtimes: `BlueprintResolver`

The container binds `Gcego\Blueprints\Contracts\BlueprintResolver` to
`RegistryBlueprintResolver`, which covers the registry's unscoped blueprints:

```php
public function __construct(private BlueprintResolver $blueprints) {}

$this->blueprints->has('subjects');
$this->blueprints->resolve('subjects');
$this->blueprints->names();
```

## Refreshing

`$registry->refresh()` forgets the index and loaded definitions; the next call
reloads them according to the cache policy. Runtime registrations are kept.
