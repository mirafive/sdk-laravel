<?php

declare(strict_types=1);

return [

    // Off, or without a secret key, the server client sends nothing and needs no key; off also omits the tracker tag.
    'enabled' => (bool) env('MIRAFIVE_ENABLED', true),

    // The secret key of a server source. Server side only: never render it into a page.
    'secret_key' => env('MIRAFIVE_SECRET_KEY'),

    // The public key of a website source, printed by @mirafiveScript.
    'website_key' => env('MIRAFIVE_WEBSITE_KEY'),

    'host' => env('MIRAFIVE_HOST', 'https://events.mirafive.io'),

    // Server events: "full" or "consentless". Consentless refuses userId, anonymousId and sessionId.
    'mode' => env('MIRAFIVE_MODE', 'full'),

    // The tracker printed by @mirafiveScript: "consentless" needs no consent banner, "full" waits for one.
    'script_mode' => env('MIRAFIVE_SCRIPT_MODE', 'consentless'),

    // null sends after the response. A queue name ("mirafive") or "connection:queue" ("redis:mirafive") hands each
    // batch to a job instead.
    'queue' => env('MIRAFIVE_QUEUE'),

    'script_url' => env('MIRAFIVE_SCRIPT_URL', 'https://cdn.mirafive.io/mira.js'),

    'flags' => [
        'refresh_seconds' => (int) env('MIRAFIVE_FLAGS_REFRESH', 30),

        // The Laravel cache store that shares the flag document between requests; null is the default store.
        'cache_store' => env('MIRAFIVE_FLAGS_CACHE'),
    ],

];
