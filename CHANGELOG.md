# Changelog

## 2.1.0 — 2026-11-09

### Changed

- Requires [`mirafive/sdk-php`](https://github.com/mirafive/sdk-php) 2.1, which deprecates `fee`
  and `tax` on `revenue()`: MIRA FIVE counts revenue as the customer paid it, VAT included.
  Passing them still works and does nothing; they are removed in 3.0.0.

## 2.0.0 — 2026-11-08

A rewrite for the MIRA FIVE platform, on [`mirafive/sdk-php`](https://github.com/mirafive/sdk-php)
2.0. The 1.x package spoke the retired v1 collector protocol; 2.0 speaks the MIRA FIVE ingest and
mirrors the Node SDK method for method. Laravel 12 and 13.

### New

- `Mira::group()`, `Mira::revenue()` (the revenue ledger: integer minor units, `entryId`,
  `entryKind`, `fee`, `tax`, `refundOf`, `product`, subscriptions) and `Mira::touchpoint()` (survey
  answers, offline contacts, redeemed codes).
- `Mira::identify()` takes `externalIds`, so connectors link Stripe, RevenueCat, Shopify and
  Shopware money to the user.
- Per-event `consent` and `context`; `Mira::forRequest($request)` sends the request's IP and user
  agent with every event.
- `Mira::eventIdFor()`, identical to the Node SDK, for facts that may be reported twice.
- `Mira::fake()` returns `MiraFive\Testing\MiraFake` with `assertRevenue()`, `assertTouchpoint()`,
  `assertGrouped()` and `assertNoErrors()` next to the 1.x assertions.
- `@mirafiveScript` prints the MIRA FIVE browser SDK with `consent` and `autocapture` options, and
  never prints a secret key.
- `mirafive:check` checks both keys and the host without storing anything.

### Changed

- `MIRAFIVE_WEBSITE_KEY` is now `MIRAFIVE_PUBLIC_KEY` (`mira_pk_…`); secret keys are `mira_sk_…`.
- The default host is `https://events.mirafive.io`; the browser SDK loads from
  `https://cdn.mirafive.io/sdk/mira.js` (`MIRAFIVE_SCRIPT_URL`).
- The facade is the container's `MiraFive\Mira`; all methods take named arguments and never throw.
- `Mira::forUser()` returns a client with the user filled in, with every method, not only `track`
  and `identify`.
- Queue jobs carry `{stream, seq, events}`; the worker adds `sent_at` when it sends.

### Removed

- `MIRAFIVE_MODE`, `MIRAFIVE_SCRIPT_MODE` and the consentless and full modes: consent is per event,
  and the browser SDK follows your consent banner (`MIRAFIVE_CONSENT`).
- `Mira::send()`, idempotency keys and `Receipt`: use `eventId: Mira::eventIdFor(…)` and
  `Mira::flush()`.
- `Mira::optedOut()`: pass the visitor's consent with the event instead.
- `sessionId`, `$install_check`, `payment_id` and `mf_` keys.

### Migrating from 1.x

1. `composer require mirafive/sdk-laravel:^2.0`.
2. In `.env`: set `MIRAFIVE_SECRET_KEY` to the new `mira_sk_…` key, rename `MIRAFIVE_WEBSITE_KEY`
   to `MIRAFIVE_PUBLIC_KEY` with the `mira_pk_…` key, and drop `MIRAFIVE_MODE` and
   `MIRAFIVE_SCRIPT_MODE`. Remove `MIRAFIVE_HOST` if it pointed to `collect.mirafive.io`.
3. If you published `config/mirafive.php`, publish it again (`--force`) and reapply your changes.
4. Update calls to named arguments: `Mira::track('signup', userId: 'u1', properties: […])` becomes
   `Mira::track(event: 'signup', userId: 'u1', properties: […])`; `time:` is `timestamp:`.
5. Move money from properties to `Mira::revenue(amount: 4900, currency: 'EUR', entryId: …)`.
6. Replace `Mira::send($events, idempotencyKey: …)` with one call per event and
   `eventId: Mira::eventIdFor(…)`.
7. Run `php artisan mirafive:check`.
