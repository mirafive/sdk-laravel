# Changelog

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
