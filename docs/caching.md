# Caching

## Configuration

```php
'cache' => [
    'enabled' => env('GCEGO_REGISTRY_CACHE', true),
    'path' => env('GCEGO_REGISTRY_CACHE_PATH', base_path('bootstrap/cache/gcego-registry.php')),
    'verify' => env('GCEGO_REGISTRY_VERIFY_CACHE', env('APP_ENV', 'production') !== 'production'),
    'missing' => env('GCEGO_REGISTRY_CACHE_MISSING', 'build'),   // or 'fail'
],
```

`env()` is read only in the config file, so `config:cache` works.

## Modes

| `enabled` | `verify` | First use in a process |
| --- | --- | --- |
| `false` | n/a | discover, execute and validate every definition; no file written |
| `true` | `true` | scan roots (list and stat), compare fingerprints; read the manifest, or rebuild and write it |
| `true` | `false` | read the manifest; no scanning |

Missing or corrupt manifest:

- with `missing => 'build'`, the manifest is rebuilt under the lock;
- with `missing => 'fail'`, a `RegistryCacheException` explains how to build it.

No hot reload happens within a process: changes are picked up on the next
process, or after `Registry::refresh()`.

## Deployment

```bash
php artisan optimize          # includes gcego:registry-cache
# or
php artisan gcego:registry-cache
```

`php artisan optimize:clear` (or `gcego:registry-clear`) removes the manifest.
A build is always strict: if any definition is invalid the command fails and
no manifest is written. Restart long-running workers after deploying.

## Manifest format

The manifest is a PHP file returning an array. This is the same approach
Laravel uses for `bootstrap/cache`, so it is opcache-friendly and never
unserialized:

```php
return [
    'format' => 1,
    'generated_at' => '2026-10-17T01:30:00Z',
    'fingerprint' => '…sha256…',
    'definitions' => [
        [
            'identity' => 'blueprint:subjects',
            'kind' => 'blueprint',
            'name' => 'subjects',
            'scope' => null,
            'source' => ['type' => 'file', 'location' => '/var/www/app/GCEGo/Modules/Academics/Subjects/Subjects.php'],
            'class' => 'Gcego\\Blueprints\\Blueprint',
            'metadata' => ['title' => 'Subject Management', 'slug' => 'subjects', 'description' => null, 'icon' => null, 'roles' => ''],
        ],
    ],
    'checksum' => '…sha256 of format, fingerprint and definitions…',
];
```

Like Laravel's own caches, the paths are absolute. Build the manifest on the
machine (or in the image) that serves it.

## Safety

| Concern | Measure |
| --- | --- |
| Partial writes | the manifest is written to a temporary file in the same directory, then renamed into place (`Filesystem::replace()`) |
| Concurrent rebuilds | an exclusive `flock` on `<path>.lock`; a process that waited re-reads the manifest and does not rebuild if it is already fresh |
| Corruption and tampering | format, field types and a SHA-256 checksum are verified; every row is re-validated |
| Stale entries | a cached file that disappeared, or now defines something else, raises `DefinitionLoadException` telling you to rebuild |
| Object injection | only scalars are written; nothing is unserialized |
| Opcache | the manifest path is invalidated after every write and clear |

## Status

```php
app(RegistryManager::class)->status()->toArray();
// ['enabled' => true, 'path' => ..., 'exists' => true, 'valid' => true, 'fresh' => true,
//  'generatedAt' => '...', 'definitions' => 12, 'problem' => null]
```
