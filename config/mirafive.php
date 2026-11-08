<?php

declare(strict_types=1);

return [

    // false: nothing leaves the app and @mirafiveScript prints nothing; input is still checked.
    'enabled' => (bool) env('MIRAFIVE_ENABLED', true),

    // The project's secret key (mira_sk_…), for server events and revenue. Never render it into a page.
    'secret_key' => env('MIRAFIVE_SECRET_KEY'),

    // The project's public key (mira_pk_…), printed by @mirafiveScript for the browser SDK.
    'public_key' => env('MIRAFIVE_PUBLIC_KEY'),

    // The ingest origin.
    'host' => env('MIRAFIVE_HOST', 'https://events.mirafive.io'),

    // null sends after the response. A queue name ("mirafive") or "connection:queue" ("redis:mirafive")
    // hands each batch to a job instead, so the request never waits for MIRA FIVE.
    'queue' => env('MIRAFIVE_QUEUE'),

    // What @mirafiveScript prints. Options passed to the directive win.
    'script' => [
        // Where the browser SDK loads from. Default: the MIRA FIVE CDN, or {host}/sdk/mira.js for another host.
        'src' => env('MIRAFIVE_SCRIPT_URL'),
        // auto (follow the consent banner), granted, denied, or analytics (analytics yes, marketing no).
        'consent' => env('MIRAFIVE_CONSENT', 'auto'),
        // false turns off all automatic events; or turn off parts: ['forms' => false, 'outbound' => false].
        'autocapture' => true,
    ],

    // Delivery inside a request: keep these short.
    'timeout_ms' => (int) env('MIRAFIVE_TIMEOUT_MS', 3000),
    'flush_deadline_ms' => (int) env('MIRAFIVE_FLUSH_DEADLINE_MS', 3000),
    'max_retries' => (int) env('MIRAFIVE_MAX_RETRIES', 2),

];
