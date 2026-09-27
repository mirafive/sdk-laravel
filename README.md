# MIRA FIVE for Laravel

Privacy-first analytics and feature flags for Laravel, hosted in the EU: track business events from your controllers
and jobs, evaluate flags in-process, and add the website tracker with one Blade directive. Events are sent after the
response, so your pages never wait for analytics.

## Install

```bash
composer require mirafive/sdk-laravel
```

PHP 8.3+, Laravel 11, 12 or 13. The service provider and the `Mira` facade are discovered automatically. To change
the defaults, publish the configuration:

```bash
php artisan vendor:publish --tag=mirafive-config
```

## Quickstart

Create a **server source** and a **website source** in MIRA FIVE and put their keys in `.env`:

```dotenv
MIRAFIVE_SECRET_KEY=mf_ab12cd34_…    # server source, stays on the server
MIRAFIVE_WEBSITE_KEY=mf_ef56gh78_…   # website source, public
```

Track from a controller:

```php
use MiraFive\Laravel\Facades\Mira;

public function store(Request $request)
{
    $user = User::create($request->validated());

    Mira::track('signup', userId: (string) $user->id, properties: ['plan' => $user->plan]);

    return redirect()->route('dashboard');
}
```

Identify after login, with the user's internal id and a few traits:

```php
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use MiraFive\Laravel\Facades\Mira;

// AppServiceProvider::boot()
Event::listen(Login::class, fn (Login $event) => Mira::forUser($event->user)->identify(['plan' => $event->user->plan]));
```

`Mira::forUser($request->user())` fills in `getAuthIdentifier()` as the user id for `track()`, `identify()` and
`flags()`.

Add the tracker to your layout's `<head>`:

```blade
<head>
    @mirafiveScript
</head>
```

It prints the hosted script with your website key, `data-mode="full"` when `MIRAFIVE_SCRIPT_MODE=full` (plus `data-host` when
you use your own host), and a Vite CSP nonce when one is set. More tracker attributes go in as an array:

```blade
@mirafiveScript(['data-autocapture' => true, 'data-site-search' => 'q'])
```

Verify the setup:

```bash
php artisan mirafive:check
```

It sends a `$install_check` event (never stored or billed) and prints the receipt.

## Consent & privacy

MIRA FIVE has two collection modes, `full` and `consentless`, set separately for server events (`MIRAFIVE_MODE`,
default `full`) and for the tracker (`MIRAFIVE_SCRIPT_MODE`, default `consentless`):

- **`full`** may carry `userId`, `anonymousId` and `sessionId`. Server events assume your site already holds consent
  or another lawful basis for the person they describe; the SDK cannot ask. A tracker in full mode stores nothing
  until your consent banner grants it (`mirafive('consent', true)`).
- **`consentless`** carries no identifiers at all and needs no banner, which is why it is the tracker's default. On
  the server, passing an identifier throws an `InvalidArgumentException`, so a misconfiguration shows up on the first
  call.

Rules for both modes:

- **Never send an email address as `userId`.** Use your internal id (`42`, a UUID). `forUser()` uses
  `getAuthIdentifier()`, which is the primary key for Eloquent users.
- **No personal data in event names or properties**: no names, emails, phone numbers or free text a person typed.
- **The secret key never reaches a page.** `@mirafiveScript` prints only the website key.

## Configuration

| Key | Env | Default | |
|---|---|---|---|
| `enabled` | `MIRAFIVE_ENABLED` | `true` | Off, or without a secret key: nothing is sent and no key is needed, flags answer their fallbacks. Off also omits the tracker tag |
| `secret_key` | `MIRAFIVE_SECRET_KEY` | — | Secret key of a server source |
| `website_key` | `MIRAFIVE_WEBSITE_KEY` | — | Public key of a website source, for `@mirafiveScript` |
| `host` | `MIRAFIVE_HOST` | `https://events.mirafive.io` | Collector, with scheme |
| `mode` | `MIRAFIVE_MODE` | `full` | Server events: `full` or `consentless` |
| `script_mode` | `MIRAFIVE_SCRIPT_MODE` | `consentless` | The tracker from `@mirafiveScript`: `consentless` or `full` |
| `queue` | `MIRAFIVE_QUEUE` | `null` | `null` sends after the response; `mirafive` or `redis:mirafive` (connection:queue) dispatches a job per batch |
| `script_url` | `MIRAFIVE_SCRIPT_URL` | `https://cdn.mirafive.io/mira.js` | Tracker script, e.g. a self-hosted copy |
| `flags.refresh_seconds` | `MIRAFIVE_FLAGS_REFRESH` | `30` | How old the flag document may get (at least 10) |
| `flags.cache_store` | `MIRAFIVE_FLAGS_CACHE` | default store | Laravel cache store that shares the flag document between requests |

Delivery failures go to your default log channel as warnings. `MiraFive\Mira` and `MiraFive\Flags\MiraFlags` can be
injected directly; to send through your own HTTP client, bind `MiraFive\Http\Transport` (e.g. to
`MiraFive\Http\Psr18Transport`) before they are resolved.

## Queues & Octane

**When events are sent.** `track()` and `identify()` buffer. The buffer is sent once, when Laravel terminates (after
the response under PHP-FPM), after each Octane request, task and tick, and after each queued job; the core's own
shutdown flush is switched off. Every 100 events it is sent early. `send()` is always immediate.

**Queued delivery.** With `MIRAFIVE_QUEUE` set, each buffered batch is encoded once and handed to a
`MiraFive\Laravel\Queue\SendBatch` job, so the request does not wait for MIRA FIVE at all. The job carries the body,
never the key: the worker sends it with its own configuration. The body is final and keeps its batch id, so a job
that runs twice is stored once. Retryable failures are retried by the queue (5 tries, backoff up to 15 minutes);
refused batches are logged and dropped. A worker without
`MIRAFIVE_SECRET_KEY` logs an error and fails the job instead of dropping it; one with `MIRAFIVE_ENABLED=false` drops it
quietly. Batches are up to 1 MiB of JSON (SQS allows 256 KB). `send()` never queues:
it always sends immediately and returns the collector's receipt.

**Octane.** The package is safe in long-running workers: no request state is kept in singletons, and the buffer is
flushed at the end of every request. It adds `MiraFive\Mira`, `MiraFlags`, the transport and the facade's client to
`octane.warm`, so a worker keeps one HTTP connection and one flag document instead of building them per request.

## Flags

```php
$flags = Mira::flags()->for(
    userId: (string) $user->id,
    properties: ['plan' => $user->plan],
    consent: ['experiments' => true, 'targeting' => false],
    optedOut: Mira::optedOut($request),   // Sec-GPC: 1 or DNT: 1
);

$flags->enabled('new-checkout');           // bool
$flags->variant('pricing-test');           // ?string
$flags->config('limits', ['max' => 3]);    // the variant's value
```

Or `Mira::forUser($request->user())->flags(properties: [...])`, which reads the opt-out from the current request
unless you pass `optedOut`. Reads never throw; without a document every flag
answers its fallback. The document is fetched on first use and shared between requests through the cache store.

**Bootstrap in Blade.** Hand the server's answers to the browser SDK so the first paint shows the right variant:

```blade
<head>
    @mirafiveFlags(['userId' => auth()->id(), 'properties' => ['plan' => auth()->user()?->plan]])
    @mirafiveScript(['data-flags' => true])
</head>
```

The unit takes `userId`, `anonymousId`, `properties`, `consent` and `optedOut` (read from `Sec-GPC`/`DNT` when left
out). You can also pass flags you already read: `@mirafiveFlags($flags)`. Only flags your website reads are included,
and every value is escaped so it cannot end the script.

A page carrying a bootstrap must never be stored by a shared cache. The package adds `Cache-Control: private,
no-store` to every response whose view printed `@mirafiveFlags`; nothing to configure. If you print
`$flags->bootstrap()` yourself, send the header yourself:
`response($html)->withHeaders(MiraFive\Flags\MiraFlags::BOOTSTRAP_HEADERS)`.

## Testing with `Mira::fake()`

```php
use MiraFive\Laravel\Facades\Mira;

it('tracks the signup', function () {
    $mira = Mira::fake();

    $this->post('/register', [...])->assertRedirect();

    $mira->assertTracked('signup');
    $mira->assertTracked('signup', fn (array $event) => $event['properties']['plan'] === 'pro');
    $mira->assertTracked('signup', 1);                 // exactly once
    $mira->assertNotTracked('refund');
    $mira->assertIdentified((string) $user->id);
});

it('stays quiet', function () {
    Mira::fake()->assertNothingTracked();              // nothing tracked, sent or identified
});
```

The fake records `track()`, `identify()` and `send()` through the facade and through an injected
`MiraFive\Laravel\Client`. Input is still checked, so an event MIRA FIVE would refuse fails the test. Flags answer their
fallbacks. Code that injects `MiraFive\Mira` directly is not faked; set `MIRAFIVE_ENABLED=false` in `phpunit.xml` so
it sends nothing.

## Troubleshooting

- **Nothing arrives.** Run `php artisan mirafive:check`. Then look for `[mirafive]` warnings in your log.
- **`unauthorized`.** `MIRAFIVE_SECRET_KEY` is missing, wrong or revoked; it must be the secret key of a *server*
  source. After changing `.env` in production, run `php artisan config:cache` again.
- **`website_key_as_bearer`.** The website key ended up in `MIRAFIVE_SECRET_KEY`. Server code needs the secret key.
- **`InvalidArgumentException: A consentless client may not send userId`.** `MIRAFIVE_MODE` is `consentless`; drop the
  identifiers or switch to `full` where you hold consent.
- **No tracker tag.** `MIRAFIVE_WEBSITE_KEY` is empty or `MIRAFIVE_ENABLED` is false.
- **Queued batches never arrive.** A worker must run the configured queue (`php artisan queue:work redis --queue=mirafive`).
- **Flags always answer the fallback.** Check `Mira::flags()->status()` and `->evaluate($key)->errorCode`:
  `NOT_READY` means no document (see the log), `FLAG_NOT_FOUND` that the flag is not served to this source.

## For AI agents

A prompt for an agent adding MIRA FIVE to a Laravel application:

```text
Add MIRA FIVE analytics to this Laravel application with the Composer package mirafive/sdk-laravel.

1. Run `composer require mirafive/sdk-laravel` (PHP 8.3+, Laravel 11–13). The provider and the `Mira` facade are
   auto-discovered; do not register them by hand.
2. Add to .env.example (empty values) and ask me for the real values for .env:
   MIRAFIVE_SECRET_KEY=   (secret key of a server source; never print it into HTML or JavaScript)
   MIRAFIVE_WEBSITE_KEY=  (public key of a website source)
   Only add MIRAFIVE_HOST, MIRAFIVE_MODE or MIRAFIVE_SCRIPT_MODE if I ask for them. The tracker is consentless by
   default; set MIRAFIVE_SCRIPT_MODE=full only if the site has a consent banner that calls mirafive('consent', …).
3. Put `@mirafiveScript` inside <head> of the main Blade layout(s), once per page.
4. Track the signup where the user is created:
   `\MiraFive\Laravel\Facades\Mira::track('signup', userId: (string) $user->getAuthIdentifier(), properties: ['plan' => $plan]);`
   Use the internal user id, never an email address. No personal data in event names or properties.
   Identify on login: listen to Illuminate\Auth\Events\Login and call
   `Mira::forUser($event->user)->identify(['plan' => ...]);`
5. Do not call flush(); the package sends after the response, after Octane requests and after queued jobs.
6. In tests, call `$mira = Mira::fake();` and assert with `$mira->assertTracked('signup')`.
   Set <env name="MIRAFIVE_ENABLED" value="false"/> in phpunit.xml.
7. Verify with `php artisan mirafive:check`: it must end with "The key and host work". Report the output.
```

Facts for agents:

- Package `mirafive/sdk-laravel`, namespace `MiraFive\Laravel`, facade `MiraFive\Laravel\Facades\Mira` (alias `Mira`),
  wrapping `mirafive/sdk-php` (`MiraFive\Mira`, `MiraFive\Flags\MiraFlags`, both container singletons).
- Env: `MIRAFIVE_SECRET_KEY`, `MIRAFIVE_WEBSITE_KEY`, `MIRAFIVE_HOST`, `MIRAFIVE_MODE` (server, default `full`), `MIRAFIVE_SCRIPT_MODE` (tracker, default `consentless`),
  `MIRAFIVE_QUEUE`, `MIRAFIVE_ENABLED`, `MIRAFIVE_SCRIPT_URL`, `MIRAFIVE_FLAGS_REFRESH`, `MIRAFIVE_FLAGS_CACHE`.
  Config file `config/mirafive.php`, publish tag `mirafive-config`.
- Facade: `track(name, userId:, anonymousId:, sessionId:, properties:, time:, page:)`, `identify(userId, traits)`,
  `send(events, idempotencyKey:)` (immediate, returns `MiraFive\Receipt`, throws `MiraFive\MiraError`), `flush()`,
  `flags()`, `forUser($user)`, `optedOut($request)`, `fake()`.
- Buffered events are flushed on terminate, Octane `RequestTerminated`, and after each queued job. Never call
  `flush()` in normal code.
- Blade: `@mirafiveScript` / `@mirafiveScript([...data attributes])`, `@mirafiveFlags($unit)` (sets
  `Cache-Control: private, no-store` automatically).
- Without a secret key, or with `MIRAFIVE_ENABLED=false`, nothing is sent and no key is needed; invalid events still throw.
- With `MIRAFIVE_QUEUE`, buffered batches go to `SendBatch` jobs; `send()` is always synchronous.
- `php artisan mirafive:check` proves key and host; success is a receipt with reason `install_check`.
- `userId` is the internal id, never an email. The secret key never goes to a browser.

## License

MIT, see [LICENSE](LICENSE). Copyright (c) 2026 Cloo GmbH.
