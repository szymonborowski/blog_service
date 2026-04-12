<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Search Engine
    |--------------------------------------------------------------------------
    */
    'driver' => env('SCOUT_DRIVER', 'meilisearch'),

    /*
    |--------------------------------------------------------------------------
    | Index Prefix
    |--------------------------------------------------------------------------
    */
    'prefix' => env('SCOUT_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Queue Data Syncing
    |--------------------------------------------------------------------------
    */
    'queue' => env('SCOUT_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | After Commit
    |--------------------------------------------------------------------------
    */
    'after_commit' => false,

    /*
    |--------------------------------------------------------------------------
    | Chunk Sizes
    |--------------------------------------------------------------------------
    */
    'chunk' => [
        'searchable'   => 500,
        'unsearchable' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft Deletes
    |--------------------------------------------------------------------------
    | Handled manually via shouldBeSearchable() in each model.
    */
    'soft_delete' => false,

    /*
    |--------------------------------------------------------------------------
    | Identify User
    |--------------------------------------------------------------------------
    */
    'identify' => false,

    /*
    |--------------------------------------------------------------------------
    | Meilisearch Configuration
    |--------------------------------------------------------------------------
    */
    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key'  => env('MEILISEARCH_KEY'),

        'index-settings' => [
            'posts' => [
                'searchableAttributes' => ['title', 'excerpt', 'content', 'categories', 'tags'],
                'filterableAttributes' => ['published_at'],
                'sortableAttributes'   => ['published_at'],
                'rankingRules'         => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'],
                'typoTolerance'        => [
                    'enabled'          => true,
                    'minWordSizeForTypos' => [
                        'oneTypo'  => 5,
                        'twoTypos' => 9,
                    ],
                ],
            ],
            'categories' => [
                'searchableAttributes' => ['name', 'slug'],
            ],
            'tags' => [
                'searchableAttributes' => ['name', 'slug'],
            ],
        ],
    ],

];
