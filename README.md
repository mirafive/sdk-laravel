# MIRA FIVE for Laravel

[MIRA FIVE](https://mirafive.io) for Laravel 12 and 13: revenue attribution and analytics by Cloo
GmbH, compliant with GDPR and TDDDG. One package for both sides of your app:

- **Server events:** a `Mira` facade for events, revenue and touchpoints only your server knows.
  They are sent after the response, so your users never wait for MIRA FIVE, or handed to a queue.
- **The browser SDK:** `@mirafiveScript` prints the script tag with your public key and the consent
  mode you choose.
- **Tests:** `Mira::fake()` records instead of sending, with assertions.

It builds on [`mirafive/sdk-php`](https://github.com/mirafive/sdk-php); everything documented
there works here too. PHP 8.3 or newer.

## Install

```sh
composer require mirafive/sdk-laravel
```

The service provider and the `Mira` facade are discovered automatically. Add the project's keys
(MIRA FIVE dashboard, Settings, Sources) to `.env`:

```dotenv
MIRAFIVE_SECRET_KEY=mira_sk_…
MIRAFIVE_PUBLIC_KEY=mira_pk_…
```

Then check them: the ingest answers whether the key works, and stores nothing.

```sh
php artisan mirafive:check
```

## Quickstart

**1. The browser SDK.** In your layout's `<head>`:

```blade
@mirafiveScript
```

It follows your consent banner (CMP) by default and tracks page views, engagement, outbound
clicks, downloads and form submits on its own. Options: `@mirafiveScript(['consent' => 'analytics'])`.

**2. Who the user is.** For example in a listener for Laravel's `Login` event:

```php
use Illuminate\Auth\Events\Login;
use MiraFive\Laravel\Facades\Mira;

Event::listen(function (Login $event): void {
    Mira::identify(
        userId: (string) $event->user->getAuthIdentifier(),
        traits: ['email' => $event->user->email, 'plan' => $event->user->plan],
        externalIds: ['stripe_customer' => $event->user->stripe_id], // links Stripe's money to this user
    );
});
```

**3. What only your server knows.** Anywhere in a request, job or command:

```php
use MiraFive\Laravel\Facades\Mira;

Mira::forUser($request->user())->track(event: 'report_exported', properties: ['format' => 'csv']);

// Money, in integer minor units: 4900 is 49.00 EUR.
Mira::revenue(
    amount: $invoice->total,
    currency: $invoice->currency,
    userId: (string) $invoice->user_id,
    entryId: 'stripe:'.$invoice->stripe_id,                    // the Stripe connector's copy counts once
    eventId: Mira::eventIdFor('invoice:'.$invoice->id.':paid'), // a retried webhook counts once
);
```

Events wait in memory and leave after the response was sent. Nothing else to set up.

## Configuration

Everything has an environment variable; publish the file only to change the script defaults:

```sh
php artisan vendor:publish --tag=mirafive-config
```

| Variable                     | Default                      | Meaning                                                                                                                     |
| ---------------------------- | ---------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| `MIRAFIVE_SECRET_KEY`        | (none)                       | The project's secret key, `mira_sk_…`, for server events and revenue. Never in a page                                       |
| `MIRAFIVE_PUBLIC_KEY`        | (none)                       | The project's public key, `mira_pk_…`, printed by `@mirafiveScript`                                                         |
| `MIRAFIVE_HOST`              | `https://events.mirafive.io` | The ingest origin, for a self-hosted or local MIRA FIVE                                                                     |
| `MIRAFIVE_ENABLED`           | `true`                       | `false`: nothing leaves the app and `@mirafiveScript` prints nothing; input is still checked                                |
| `MIRAFIVE_QUEUE`             | (none)                       | A queue (`mirafive`) or `connection:queue` (`redis:mirafive`): batches go to a job instead of being sent after the response |
| `MIRAFIVE_SCRIPT_URL`        | the MIRA FIVE CDN            | Where the browser SDK loads from; for another host, `{host}/sdk/mira.js`                                                    |
| `MIRAFIVE_CONSENT`           | `auto`                       | The script's consent mode: `auto` (follow the CMP), `granted`, `denied`, `analytics` (analytics yes, marketing no)          |
| `MIRAFIVE_TIMEOUT_MS`        | `3000`                       | Per request to the ingest                                                                                                   |
| `MIRAFIVE_FLUSH_DEADLINE_MS` | `3000`                       | What one flush may spend on attempts and waits together                                                                     |
| `MIRAFIVE_MAX_RETRIES`       | `2`                          | Retries per batch on network errors, timeouts, 408, 429 and 5xx                                                             |

`config/mirafive.php` also has `script.autocapture`: `true`, `false`, or parts to turn off, such
as `['forms' => false, 'outbound' => false]`.

Without a secret key nothing is sent and nothing is logged, which keeps local development quiet;
`php artisan mirafive:check` tells you why.

## API

`MiraFive\Laravel\Facades\Mira` is the container's `MiraFive\Mira`; inject `MiraFive\Mira` if you
prefer. All methods take named arguments and never throw: problems are logged as warnings through
Laravel's logger.

| Method                                                                                              | What it sends                                                                                                                                       |
| --------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Mira::track(event, userId, anonymousId, properties, …)`                                            | A custom event. Names starting with `$` are reserved                                                                                                |
| `Mira::identify(userId, traits, externalIds, …)`                                                    | Who the user is. PII traits are encrypted at rest. `externalIds`: `stripe_customer`, `revenuecat_app_user`, `shopify_customer`, `shopware_customer` |
| `Mira::group(groupId, userId, traits, …)`                                                           | The company or account a user belongs to                                                                                                            |
| `Mira::revenue(amount, currency, entryId, entryKind, refundOf, product, subscription, …)`           | One revenue ledger entry, in integer minor units                                                                                                    |
| `Mira::touchpoint(kind, userId, channel, source, medium, campaign, answer, code, …)`                | A touchpoint that is not a visit: a survey answer, an offline contact, a code                                                                       |
| `Mira::forUser($user)`                                                                              | A client that sends as this user (`getAuthIdentifier()`)                                                                                            |
| `Mira::forRequest($request)`                                                                        | A client that sends the request's IP (for geo, never stored) and user agent with every event                                                        |
| `Mira::with(userId, anonymousId, consent, context)`                                                 | A client with these defaults; the call's own values win                                                                                             |
| `Mira::eventIdFor('order:123:paid')`                                                                | A stable event id from your own key, identical in every MIRA FIVE SDK                                                                               |
| `Mira::flush()`                                                                                     | Sends now (also happens after the response, each job and each Octane request)                                                                       |

Every event method also takes `eventId`, `timestamp` (a date, epoch milliseconds or ISO string;
past dates are fine for backfills), `consent` and `context` (`ip`, `userAgent`, `page`, `app`,
`locale`, `timezone`, `location`). The [sdk-php README](https://github.com/mirafive/sdk-php#api-reference)
documents every argument.

### Revenue

```php
// A subscription payment (the first one and every renewal).
Mira::revenue(
    amount: 999,
    currency: 'EUR',
    userId: 'user_42',
    entryId: 'revenuecat:txn_1000000123',
    product: 'premium_monthly',
    subscription: ['id' => 'sub_42', 'interval' => 'month'], // or intervalCount, periodStart, periodEnd
);

// A refund takes the money back, and the credit of the channels that earned it.
Mira::revenue(
    amount: -999,
    currency: 'EUR',
    userId: 'user_42',
    entryKind: 'refund',
    entryId: 'revenuecat:txn_1000000123:refund',
    refundOf: 'revenuecat:txn_1000000123',
);
```

`entryKind` is `charge` (the default), `subscription_payment` (the default with `subscription`),
`refund` (the default for a negative amount), `dispute` or `dispute_won`. `amount` is what the
customer paid, VAT included. `19.99` is refused: send `1999`.

### Touchpoints

```php
// "How did you hear about us?" on your sign-up form.
Mira::touchpoint(
    kind: 'survey',
    userId: (string) $user->id,
    source: $request->string('heard_from')->value(),     // podcast, instagram, a friend, …
    answer: $request->string('heard_from_text')->value(), // passes MIRA's PII guard
    eventId: Mira::eventIdFor('survey:'.$user->id),
);

// A redeemed partner code.
Mira::touchpoint(kind: 'code', userId: (string) $user->id, channel: 'affiliate', source: 'partner_x', code: 'PARTNER20');
```

A touchpoint needs a user or anonymous id and a `channel` or a `source`. Kinds: `survey`,
`offline`, `code`, `custom`.

## Consent tiers

Server events default to `not_applicable`: a paid invoice is a fact of your business. For events
that describe what a visitor did, pass what your consent banner recorded:

```php
Mira::forUser($user)->track(event: 'search', consent: ['analytics' => true, 'marketing' => false]);
// or MiraFive\Consent::granted(), Consent::analyticsOnly(), Consent::denied()
```

| `consent` on the event                           | Tier    | What MIRA keeps                                                                               |
| ------------------------------------------------ | ------- | --------------------------------------------------------------------------------------------- |
| `not_applicable` (default), no `userId`          | T1      | The event, `anonymous_id` if given, geo from `context.ip` (the IP is discarded)               |
| `not_applicable` with `userId`                   | T2      | Plus `user_id`, traits (PII traits encrypted at rest), revenue per person                     |
| `{ analytics: 'granted' }`                       | T1 / T2 | As above                                                                                      |
| `{ analytics: 'granted', marketing: 'granted' }` | T3      | Plus click ids, and later forwarding to ad platforms                                          |
| `{ analytics: 'denied' \| 'unknown' }`           | T0      | No `user_id`, `anonymous_id` or `session_id`: the event counts, but is not linked to a person |

The browser SDK printed by `@mirafiveScript` follows your consent banner itself (`consent: auto`).

## The script tag

```blade
@mirafiveScript
@mirafiveScript(['consent' => 'analytics', 'autocapture' => ['forms' => false]])
```

| Option        | Default                    | Meaning                                                                                                 |
| ------------- | -------------------------- | ------------------------------------------------------------------------------------------------------- |
| `consent`     | `MIRAFIVE_CONSENT`, `auto` | `auto` follows the CMP; `granted`, `denied`, or `analytics` (analytics yes, marketing no)               |
| `autocapture` | `true`                     | `false` turns off all automatic events; or parts: `spa`, `engagement`, `outbound`, `downloads`, `forms` |
| `src`, `host` | from the config            | Where the SDK loads from and where it sends                                                             |
| `debug`       | `false`                    | Logs what the SDK does to the browser console                                                           |
| `nonce`       | Vite's CSP nonce           | A Content Security Policy nonce for both tags                                                           |

It prints the tag the MIRA FIVE dashboard shows: a stub that queues `mira(…)` calls made before the
SDK has loaded, then the SDK. Nothing is printed when `MIRAFIVE_ENABLED=false` or without a public
key. A secret key is never printed: the tag becomes an HTML comment and the problem is logged.

## Queues and Octane

By default batches are sent after the response (in `terminating`), so users never wait. To keep
even that off your web servers, hand the batches to a queue:

```dotenv
MIRAFIVE_QUEUE=redis:mirafive
```

Each batch becomes a `MiraFive\Laravel\Queue\SendBatch` job. The job carries no key: the worker
sends with its own `MIRAFIVE_SECRET_KEY`, so set it on workers too. A failed delivery is retried by
the queue (5 tries, backing off 10 s, 1 min, 5 min, 15 min) with the same events and sequence
number, so a job that runs twice is counted once. A batch the ingest refuses for good is logged and
dropped; a worker without a key fails the job.

Events tracked inside a job leave when the job is done. Under **Octane**, events leave after each
request, task and tick, and the client is added to `octane.warm`, so its connection is reused.

## Testing

```php
use MiraFive\Laravel\Facades\Mira;

it('records the purchase', function () {
    $fake = Mira::fake();

    $this->post('/checkout', ['plan' => 'pro'])->assertOk();

    $fake->assertTracked('checkout_completed');
    $fake->assertRevenue(fn (array $event) => $event['revenue']['amount'] === 4900
        && $event['revenue']['currency'] === 'EUR');
    $fake->assertIdentified('42', fn (array $event) => $event['traits']['plan'] === 'pro');
    $fake->assertNoErrors();
});
```

`Mira::fake()` swaps in a real client on a recording transport: input is checked exactly as in
production, so an event the ingest would refuse fails the test. Assertions: `assertTracked(name,
callback|times)`, `assertNotTracked`, `assertIdentified`, `assertGrouped`, `assertRevenue`,
`assertTouchpoint`, `assertNothingTracked`, `assertNoErrors`; plus `events(?name)`, `errors()` and
`clear()`. Callbacks get the wire event with snake_case keys (`user_id`, `properties`, `revenue`,
`traits`, `external_ids`).

## Troubleshooting

Start with `php artisan mirafive:check`: it shows the host, the keys and the delivery mode, and
asks the ingest whether the key works; nothing is stored or counted.

- **Nothing arrives, nothing is logged.** `MIRAFIVE_SECRET_KEY` is not set (or the config is
  cached: `php artisan config:clear`).
- **`batch dropped: HTTP 401 unknown_key`.** The key is wrong, revoked or from another host.
- **`MIRAFIVE_SECRET_KEY holds a public key`.** Server code needs `mira_sk_…`; `mira_pk_…` is for
  the script tag.
- **`@mirafiveScript` prints an HTML comment.** `MIRAFIVE_PUBLIC_KEY` holds a secret key; the log
  says so. Rotate that key if the page was ever served with it elsewhere.
- **Queued batches never arrive.** Run a worker for the queue in `MIRAFIVE_QUEUE`, and set
  `MIRAFIVE_SECRET_KEY` on it.
- **`revenue: amount must be an integer in minor units`.** Send cents: `1999`, not `19.99`.

## Development

```sh
composer install
composer check   # pint --test, phpstan (max, strict, Larastan), pest
```

## License

MIT, Cloo GmbH. See [LICENSE](LICENSE).
