<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default database connection
    |--------------------------------------------------------------------------
    |
    | Leave null to use the application's default connection. You can override
    | per report using the "connection" column.
    |
    */
    'connection' => null,

    /*
    |--------------------------------------------------------------------------
    | Cache driver
    |--------------------------------------------------------------------------
    |
    | Cache driver to use when caching report output. If null, the default cache
    | store will be used.
    |
    */
    'cache_store' => null,

    /*
    |--------------------------------------------------------------------------
    | Default cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | Set a default TTL for cached reports. Can be overridden per report or per
    | call by passing the "cache_ttl" option.
    |
    */
    'cache_ttl' => 0,

    /*
    |--------------------------------------------------------------------------
    | Table name
    |--------------------------------------------------------------------------
    |
    | The database table that stores report definitions.
    |
    */
    'table' => 'reports',
];
