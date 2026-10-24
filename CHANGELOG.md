# Changelog

## Unreleased

- Revenue events may carry `payment_id`, the payment provider's id of the payment (1–255 characters), next to `revenue` and `currency`. Nothing changes in the API: pass it in the properties. The README shows a Stripe example.
- The default ingest host is `https://collect.mirafive.io`.

- **Breaking:** feature flags and A/B experiments are removed: `Mira::flags()`, `UserContext::flags()`, the `MiraFlags` container binding, the `@mirafiveFlags` Blade directive, the `SendBootstrapHeaders` middleware, `Mira::currentRequestOptedOut()` and the `flags.refresh_seconds` and `flags.cache_store` config keys (`MIRAFIVE_FLAGS_REFRESH`, `MIRAFIVE_FLAGS_CACHE`). The delivery pause of the core client now uses the default cache store.

## 1.0.0 — 2026-09-27

First release, on `mirafive/sdk-php` 1.0 (hand-off, `deliverPrepared`, `enabled` and `flushOnShutdown`).

- Auto-discovered `MiraFiveServiceProvider` and `Mira` facade; `config/mirafive.php` (publish tag `mirafive-config`).
- `MiraFive\Mira` and `MiraFive\Flags\MiraFlags` from the container, flushed when the application terminates, after
  each Octane request, task and tick, and after each queued job. Warmed under Octane.
- Disabled mode (`enabled` false or no secret key): nothing is sent and no key is needed; input is still checked.
- Queued delivery (`MIRAFIVE_QUEUE`): each buffered batch is handed off encoded to a `SendBatch` job, which the worker
  delivers byte for byte with its own key. `send()` stays synchronous.
- Separate modes for server events (`mode`, default `full`) and the tracker tag (`script_mode`, default `consentless`).
- Flag document shared between requests through a Laravel cache store.
- Blade `@mirafiveScript` (hosted tracker with the website key, Vite CSP nonce) and `@mirafiveFlags($unit)` (bootstrap
  block; the response is sent with `Cache-Control: private, no-store`).
- `Mira::forUser($user)` and `Mira::optedOut($request)`.
- `Mira::fake()` with `assertTracked`, `assertNotTracked`, `assertIdentified` and `assertNothingTracked`.
- `php artisan mirafive:check` sends `$install_check` and prints the receipt.
