<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API address and version
    |--------------------------------------------------------------------------
    |
    | The version is sent both as the X-API-VERSION header and as the path
    | prefix (https://api.dane.gov.pl/1.4/datasets); the path has priority.
    |
    */

    'base_url' => env('DANE_GOV_URL', 'https://api.dane.gov.pl'),

    'api_version' => env('DANE_GOV_API_VERSION', '1.4'),

    /*
    |--------------------------------------------------------------------------
    | Language and paging
    |--------------------------------------------------------------------------
    |
    | Language of messages and translated fields, sent as Accept-Language:
    | "pl" (default of the API) or "en". Lazy iterators request pages of
    | "per_page" records; the API accepts at most 100.
    |
    */

    'language' => env('DANE_GOV_LANG', 'pl'),

    'per_page' => env('DANE_GOV_PER_PAGE', 100),

    'timeout' => env('DANE_GOV_TIMEOUT', 30),

    /*
    | Retries after connection errors and HTTP 5xx answers of read requests.
    */

    'retries' => env('DANE_GOV_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Response cache
    |--------------------------------------------------------------------------
    |
    | JSON responses are cached for "ttl" seconds (0 disables caching).
    |
    */

    'cache' => [
        'enabled' => env('DANE_GOV_CACHE', true),
        'store' => env('DANE_GOV_CACHE_STORE'),
        'ttl' => env('DANE_GOV_CACHE_TTL', 3600),
        'prefix' => 'dane-gov',
    ],

    /*
    |--------------------------------------------------------------------------
    | File downloads
    |--------------------------------------------------------------------------
    |
    | Files are streamed to disk. "directory" is used when download() gets a
    | file name without a directory; null means storage/app/dane-gov.
    |
    */

    'download' => [
        'timeout' => env('DANE_GOV_DOWNLOAD_TIMEOUT', 600),
        'directory' => env('DANE_GOV_DOWNLOAD_DIRECTORY'),
    ],

];
