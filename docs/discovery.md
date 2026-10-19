# Discovery

## Developer workflow

1. Create `app/GCEGo/Modules/Academics/Subjects/Subjects.php`, returning a `Blueprint` named `subjects`.
2. Use it: `app(Registry::class)->blueprint('subjects')`.

There is nothing to register. The test
`tests/Integration/DeveloperExperienceTest.php` exercises exactly this.

When does a new file appear?

| Setting | When |
| --- | --- |
| `cache.enabled = false` | on the next process (or after `refresh()`) |
| `cache.enabled = true`, `cache.verify = true` (non-production default) | on the next process; the fingerprint changes and the cache rebuilds |
| `cache.enabled = true`, `cache.verify = false` (production) | after `php artisan optimize` (or `gcego:registry-cache`) |

## Roots

```php
'discovery' => [
    'enabled' => true,
    'roots' => [
        ['path' => app_path('GCEGo/Modules'), 'kind' => 'blueprint', 'entrypoint' => 'directory'],
        ['path' => app_path('GCEGo/Pages'), 'kind' => 'page', 'entrypoint' => 'file'],
        ['path' => base_path('modules/admin'), 'kind' => 'blueprint', 'entrypoint' => 'directory', 'scope' => 'admin', 'required' => true],
    ],
    'ignore' => ['Tests', 'tests', 'Fixtures', 'fixtures'],
],
```

| Key | Meaning |
| --- | --- |
| `path` | the directory to scan (required) |
| `kind` | the definition kind of its entrypoints (required) |
| `entrypoint` | `directory`, `file`, or a regular expression (default `file`) |
| `scope` | prefix for identities: `blueprint:admin.subjects` |
| `required` | throw `DiscoveryException` when the directory is missing (default `false`) |

Roots are scanned in configuration order. A root configured twice is scanned
once, and a file reachable from two nested roots belongs to the first one. A
root whose kind is not registered is skipped and reported by the inspector.

## Entrypoint conventions

| Convention | Entrypoints | Not entrypoints |
| --- | --- | --- |
| `directory` | `Subjects/Subjects.php`, `Academics/Subjects/Subjects.php`, and files directly in the root | `Subjects/Table.php`, `Subjects/Form.php` |
| `file` | every `.php` file | |
| regex, e.g. `#\.definition\.php$#` | paths (relative, `/`-separated) that match | everything else |

With `directory`, supporting files live next to their entrypoint and are
never registered as independent definitions. The entrypoint may `require`
them itself.

## What is always skipped

- non-`.php` files;
- names starting with `.` or `_`;
- directories listed in `ignore`;
- symbolic links, both files and directories, which are never followed;
- anything whose real path resolves outside its root.

## Determinism

Entrypoints are ordered by root (configuration order), then by relative path
(byte order). The index preserves that order.

## Fingerprint

`DiscoveryResult::fingerprint()` hashes the roots plus each entrypoint's kind,
scope, path, size and modification time. `RegistryManager` adds providers and
registered kinds. A changed fingerprint means the cache is stale. Computing it
lists directories and stats files; it never includes them.
