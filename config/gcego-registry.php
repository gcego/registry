<?php

declare(strict_types=1);

return [

    'discovery' => [

        'enabled' => env('GCEGO_REGISTRY_DISCOVERY', true),

        /*
        |--------------------------------------------------------------------------
        | Application Discovery Roots
        |--------------------------------------------------------------------------
        |
        | Additional application-defined roots, scanned before package roots.
        | Packages add their own roots with
        | Gcego\Registry\Discovery\DiscoveryRoots::add() from register().
        |
        | Each root: ['path' => ..., 'kind' => ..., 'entrypoint' => ...]
        |   entrypoint: "directory" (Subjects/Subjects.php), "file" (every
        |   PHP file) or a regex matched against the path within the root.
        | Optional: 'scope' (prefixes identities) and 'required' (throw when
        | the directory is missing). Roots of unregistered kinds are ignored.
        |
        */

        'roots' => [],

        /*
        |--------------------------------------------------------------------------
        | Ignored Directories
        |--------------------------------------------------------------------------
        */

        'ignore' => [
            'Tests',
            'tests',
            'Fixtures',
            'fixtures',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Explicit Definition Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [],

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    'strict' => true,

    'validate_on_resolve' => true,

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    'cache' => [

        'enabled' => env('GCEGO_REGISTRY_CACHE', true),

        'path' => env(
            'GCEGO_REGISTRY_CACHE_PATH',
            base_path('bootstrap/cache/gcego-registry.php')
        ),

        'verify' => env(
            'GCEGO_REGISTRY_VERIFY_CACHE',
            env('APP_ENV', 'production') !== 'production'
        ),

        'missing' => env(
            'GCEGO_REGISTRY_CACHE_MISSING',
            'build'
        ),
    ],
];
