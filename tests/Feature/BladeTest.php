<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\ViewException;

beforeEach(function (): void {
    config()->set('mirafive.website_key', 'mf_ab12cd34_site');
});

it('prints the tracker with the website key', function (): void {
    config()->set('mirafive.host', 'https://events.mirafive.io');

    expect(Blade::render('@mirafiveScript'))->toBe(
        '<script>window.mirafive=window.mirafive||function(){(mirafive.q=mirafive.q||[]).push(arguments)}</script>'."\n"
        .'<script defer src="https://cdn.mirafive.io/mira.js" data-key="mf_ab12cd34_site"></script>',
    );
});

it('adds a custom host and takes more attributes', function (): void {

    $html = Blade::render("@mirafiveScript(['data-autocapture' => true, 'data-site-search' => 'q,s', 'data-hash' => false])");

    expect($html)->toContain('data-host="https://collector.test"')
        ->toContain(' data-autocapture data-site-search="q,s"')
        ->not->toContain('data-hash');
});

it('follows script_mode, not the server mode', function (): void {
    config()->set('mirafive.mode', 'consentless');
    config()->set('mirafive.script_mode', 'full');

    expect(Blade::render('@mirafiveScript'))->toContain(' data-mode="full"');
});

it('escapes what it prints', function (): void {
    config()->set('mirafive.website_key', '"><script>alert(1)</script>');

    $html = Blade::render("@mirafiveScript(['data-site-search' => '\"onload=\"x'])");

    expect($html)->toContain('data-key="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"')
        ->toContain('data-site-search="&quot;onload=&quot;x"')
        ->and(substr_count($html, '<script'))->toBe(2);
});

it('refuses an attribute name that could break out of the tag', function (): void {
    Blade::render("@mirafiveScript(['onload=x data-a' => true])");
})->throws(ViewException::class, 'is not an attribute name');

it('uses the Vite CSP nonce', function (): void {
    Vite::useCspNonce('n0nce');

    expect(substr_count(Blade::render('@mirafiveScript'), 'nonce="n0nce"'))->toBe(2);
});

it('prints nothing without a website key', function (): void {
    config()->set('mirafive.website_key', null);

    expect(Blade::render('@mirafiveScript'))->toBe('');
});
