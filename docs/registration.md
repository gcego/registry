# Registration

Definitions enter the registry in three ways. Register their application-owned
kinds first; see [extensions](extensions.md). Files and providers return built
objects, and the registry handles discovery, validation, indexing and resolution.

## 1. Definition files (discovered)

A discovered entrypoint must `return` either:

- **a definition object**, such as `return new PageDefinition('home', 'Home');`;
- **the class name of a `DefinitionProvider`**, such as `return Subjects::class;`.

Anything else (no return value, an array, output, an exception, a syntax
error) is a `DefinitionLoadException` naming the file.

## 2. Class-based providers

```php
use App\Definitions\PageDefinition;
use Gcego\Registry\Contracts\DefinitionProvider;
use Illuminate\Contracts\Config\Repository;

final readonly class AcademicsProvider implements DefinitionProvider
{
    public function __construct(private Repository $config) {}   // resolved by the container

    public function definitions(): iterable
    {
        yield new PageDefinition('topics', 'Topics');
        yield new PageDefinition('lessons', 'Lessons');
    }
}
```

Use a provider in either of two ways: list it in `gcego-registry.providers`,
or return its class name from a discovered file. The registry never scans
classes. Each yielded object's kind is the single registered kind whose
contract it implements; zero or several equally specific matching kinds is an error. A matching
subclass contract takes precedence over its parent contract.

## 3. Runtime registration

```php
use App\Definitions\PageDefinition;
use Gcego\Registry\Facades\Registry;

Registry::register(new PageDefinition('reports', 'Reports'));
Registry::register(new PageDefinition('settings', 'Settings'), scope: 'admin');
```

The object is validated immediately but does not trigger discovery, so it is
safe to call from a service provider. Runtime registrations are not cached, and
they persist until the process ends.

## Validation

For every definition, the `DefinitionRegistrar`:

1. checks it implements the kind's contract;
2. names it through the kind;
3. validates it through the kind;
4. records a descriptor with the kind's scalar metadata.

Validation errors carry the definition identity, source and validation details:

```text
Definition "page:home" (file /app/GCEGo/Pages/home.php) is invalid:
- title: a page needs a title
```

## Duplicates

| Situation | Result |
| --- | --- |
| Same identity, same source (re-registration) | collapsed (idempotent) |
| Same identity, different sources | `DuplicateDefinitionException` naming both |
| Same runtime object registered twice | no-op |
| Different runtime object, same identity | `DuplicateDefinitionException` |
| Same name in different kinds or scopes | allowed: different identities |

## Strict mode

With `gcego-registry.strict` set to `true` (the default), the first problem
throws. With `false`, a broken source is skipped and recorded:
`Registry::failures()` lists the skipped sources, and the inspector reports
them. Cache builds are always strict, so a broken definition can never be
cached.
