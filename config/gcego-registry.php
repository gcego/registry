<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Registry
|--------------------------------------------------------------------------
|
| Where GCEGO looks for definitions and how the compiled index is cached.
| Publish with "php artisan vendor:publish --tag=gcego-registry-config".
|
*/

return [

    'discovery' => [
        'enabled' => env('GCEGO_REGISTRY_DISCOVERY', true),

        // Each root: a directory, the definition kind it holds, and which PHP
        // files in it are entrypoints:
        //   "directory" - a file named after its folder (Subjects/Subjects.php)
        //   "file"      - every PHP file
        //   a regex     - matched against the path relative to the root
        // Optional: "scope" (prefixes identities, e.g. "admin") and
        // "required" (throw when the directory is missing).
        // Roots whose kind is not registered are ignored until the application
        // registers a matching DefinitionKind.
        'roots' => [
            ['path' => app_path('GCEGo/Modules'), 'kind' => 'module', 'entrypoint' => 'directory'],
            ['path' => app_path('GCEGo/Pages'), 'kind' => 'page', 'entrypoint' => 'file'],
            ['path' => app_path('GCEGo/Widgets'), 'kind' => 'widget', 'entrypoint' => 'file'],
            ['path' => app_path('GCEGo/Layouts'), 'kind' => 'layout', 'entrypoint' => 'file'],
            ['path' => app_path('GCEGo/Actions'), 'kind' => 'action', 'entrypoint' => 'file'],
            ['path' => app_path('GCEGo/Navigation'), 'kind' => 'navigation', 'entrypoint' => 'file'],
            ['path' => app_path('GCEGo/Panels'), 'kind' => 'panel', 'entrypoint' => 'file'],
        ],

        // Directory names never scanned (names starting with "." or "_" are always skipped).
        'ignore' => ['Tests', 'tests', 'Fixtures', 'fixtures'],
    ],

    // Class-based definitions: Gcego\Registry\Contracts\DefinitionProvider
    // implementations, resolved through the container.
    'providers' => [],

    // Throw on the first invalid definition (true), or skip it and report it
    // through the inspector (false). Cache builds are always strict.
    'strict' => true,

    // Validate definitions again when they are loaded from the cached index.
    'validate_on_resolve' => true,

    'cache' => [
        'enabled' => env('GCEGO_REGISTRY_CACHE', true),

        'path' => env('GCEGO_REGISTRY_CACHE_PATH', base_path('bootstrap/cache/gcego-registry.php')),

        // Rescan the roots (listing and stat only) on first use in each
        // process and rebuild when files changed. Turn off in production and
        // build the cache on deploy with "php artisan optimize".
        'verify' => env('GCEGO_REGISTRY_VERIFY_CACHE', env('APP_ENV', 'production') !== 'production'),

        // When the cache is missing or corrupt: "build" it, or "fail".
        'missing' => env('GCEGO_REGISTRY_CACHE_MISSING', 'build'),
    ],

];
