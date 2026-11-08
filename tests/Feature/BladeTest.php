<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\View\ViewException;
use MiraFive\Laravel\Settings;
use MiraFive\Laravel\View\ScriptTag;

it('prints the browser SDK with the public key', function (): void {
    expect(Blade::render('@mirafiveScript'))->toBe(<<<'HTML'
        <script>
          window.mira = window.mira || function () {
            (mira.q = mira.q || []).push(arguments);
          };
        </script>
        <script async src="https://ingest.test/sdk/mira.js"
          data-key="mira_pk_live_123"></script>
        HTML);
});

it('loads from the CDN for the default host', function (): void {
    config()->set('mirafive.host', 'https://events.mirafive.io');

    expect(Blade::render('@mirafiveScript'))->toContain('src="https://cdn.mirafive.io/sdk/mira.js"'."\n".'  data-host="https://events.mirafive.io"');
});

it('takes consent and autocapture from the config and the directive', function (): void {
    config()->set('mirafive.script.consent', 'analytics');
    config()->set('mirafive.script.autocapture', ['forms' => false]);

    expect(Blade::render('@mirafiveScript'))->toContain('data-consent="analytics"'."\n".'  data-forms="false"')
        ->and(Blade::render("@mirafiveScript(['consent' => 'granted', 'autocapture' => false, 'nonce' => 'abc'])"))
        ->toContain('data-consent="granted"')
        ->toContain('data-autocapture="false"')
        ->toContain('<script nonce="abc">');
});

it('prints nothing without a public key or when switched off', function (): void {
    config()->set('mirafive.public_key', null);
    expect(Blade::render('@mirafiveScript'))->toBe('');

    config()->set('mirafive.public_key', 'mira_pk_1');
    config()->set('mirafive.enabled', false);
    app()->forgetInstance(Settings::class);
    app()->forgetInstance(ScriptTag::class);
    expect(Blade::render('@mirafiveScript'))->toBe('');
})->after(fn () => app()->forgetInstance(Settings::class));

it('never prints a secret key', function (): void {
    config()->set('mirafive.public_key', 'mira_sk_live_secretTail');
    Log::spy();

    $html = Blade::render('@mirafiveScript');

    expect($html)->toBe('<!-- MIRA FIVE: the script tag was not printed, see the log -->')
        ->and($html)->not->toContain('secretTail');
    Log::shouldHaveReceived('error')->once();
});

it('refuses unknown options', function (): void {
    Blade::render("@mirafiveScript(['mode' => 'full'])");
})->throws(ViewException::class, '@mirafiveScript does not know mode');
