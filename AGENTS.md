# AGENTS.md

`mirafive/sdk-laravel`: the Laravel integration of MIRA FIVE. It wraps `mirafive/sdk-php` and adds no transport or
flag evaluation of its own. The public surface is fixed by the `mirafive/sdk-laravel` section of `API.md` in the
protocol repository; change it there first.

## Local development

`mirafive/sdk-php` is not on Packagist yet. `composer.json` keeps requiring `mirafive/sdk-php: ^1.0`; for local work
use the gitignored `composer.local.json`, a copy of `composer.json` that adds a path repository to the sibling checkout:

```json
"repositories": [
    {"type": "path", "url": "../sdk-php", "options": {"symlink": true, "versions": {"mirafive/sdk-php": "1.0.0"}}}
]
```

```bash
COMPOSER=composer.local.json composer install
COMPOSER=composer.local.json composer check   # pint --test, phpstan (larastan, level max), pest
vendor/bin/pint                                # fix formatting
vendor/bin/pest                                # tests only
```

Keep `composer.local.json` in step with `composer.json` when dependencies change. CI installs from `composer.json`
and stays red until `mirafive/sdk-php` 1.0 is published on Packagist.

## Rules

- PHP 8.3 syntax and functions only (no property hooks, asymmetric visibility, pipe operator, `array_find`, …).
  CI runs PHP 8.3–8.5 against Laravel 11, 12 and 13; Laravel 11 needs `audit.block-insecure` off to install.
- Never reimplement delivery, retries or evaluation. If the core lacks a hook, describe the change for sdk-php.
- Octane: no request state in singletons. The core buffer is flushed after every request; anything per request
  lives on the request (see `Tags::BOOTSTRAPPED`) or is resolved from `Container::getInstance()` at call time.
- Build on the core's seams, never around them: `enabled`, `flushOnShutdown: false` (Laravel flushes on terminate),
  `handOff` + `deliverPrepared` for queues, `flagsRefreshSeconds`. The disabled client still throws on bad input.
- Tests never touch the network: bind `tests/Support/FakeTransport` as `MiraFive\Http\Transport` (`TestCase` does).
- Comments only for non-obvious constraints, one or two lines.
- Do not run git write commands; the maintainer commits.

## Releasing

To release, add a `## X.Y.Z — YYYY-MM-DD` section to `CHANGELOG.md`, commit, then `git tag vX.Y.Z && git push origin vX.Y.Z`. `.github/workflows/release.yml` checks the changelog, runs `composer check` and creates the GitHub release; Packagist picks the tag up by itself.
