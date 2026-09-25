<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | SSR est piloté par variable d'environnement : désactivé par défaut (dev
    | rend en client-side, aucun process Node requis), activé en production où
    | le sidecar `inertia-ssr` (node bootstrap/ssr/ssr.js) écoute sur INERTIA_SSR_URL.
    |
    */

    'ssr' => [
        'enabled' => env('INERTIA_SSR_ENABLED', false),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
    ],

    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [resource_path('js/pages')],
        'page_extensions' => ['js', 'jsx', 'ts', 'tsx', 'vue'],
    ],

    'history' => [
        'encrypt' => false,
    ],

];
