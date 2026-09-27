<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use MiraFive\Flags\MiraFlags;
use MiraFive\Laravel\Client;
use MiraFive\Laravel\Facades\Mira as MiraFacade;
use MiraFive\Laravel\MiraFiveServiceProvider;
use MiraFive\Laravel\Settings;
use MiraFive\Mira;
use MiraFive\Mode;

it('binds the core client, flags and facade root as singletons', function (): void {
    expect(app(Mira::class))->toBe(app(Mira::class))
        ->and(app(MiraFlags::class))->toBe(app(MiraFlags::class))
        ->and(app(Client::class))->toBe(app(Client::class))
        ->and(MiraFacade::getFacadeRoot())->toBe(app(Client::class))
        ->and(MiraFacade::flags())->toBe(app(MiraFlags::class));
});

it('merges the default configuration', function (): void {
    expect(config('mirafive.mode'))->toBe('full')
        ->and(config('mirafive.script_mode'))->toBe('consentless')
        ->and(config('mirafive.script_url'))->toBe('https://cdn.mirafive.io/mira.js')
        ->and(config('mirafive.flags.refresh_seconds'))->toBe(30)
        ->and(config('mirafive.queue'))->toBeEmpty();
});

it('publishes the configuration under mirafive-config', function (): void {
    $paths = ServiceProvider::pathsToPublish(MiraFiveServiceProvider::class, 'mirafive-config');

    expect($paths)->toHaveCount(1)
        ->and(array_values($paths)[0])->toEndWith('config/mirafive.php');
});

it('reads settings from the configuration', function (): void {
    config()->set('mirafive.mode', 'consentless');
    config()->set('mirafive.queue', 'redis:analytics');
    config()->set('mirafive.host', 'https://eu.collector.test/');

    $settings = app(Settings::class);

    expect($settings->mode)->toBe(Mode::Consentless)
        ->and($settings->scriptMode)->toBe(Mode::Consentless)
        ->and($settings->queueConnection)->toBe('redis')
        ->and($settings->queue)->toBe('analytics')
        ->and($settings->host)->toBe('https://eu.collector.test')
        ->and($settings->keyNamespace())->toBe('mf_ab12cd34')
        ->and(app(Mira::class)->mode)->toBe(Mode::Consentless);
});

it('takes a bare queue name for the default connection', function (): void {
    expect(Settings::fromConfig(['queue' => 'mirafive']))
        ->queueConnection->toBeNull()
        ->queue->toBe('mirafive')
        ->and(Settings::fromConfig(['queue' => null])->queued())->toBeFalse();
});

it('refuses an unknown script mode', function (): void {
    Settings::fromConfig(['script_mode' => 'banner']);
})->throws(InvalidArgumentException::class, 'mirafive.script_mode is "full" or "consentless"');

it('refuses an unknown mode', function (): void {
    config()->set('mirafive.mode', 'sometimes');

    app(Mira::class);
})->throws(InvalidArgumentException::class, 'mirafive.mode is "full" or "consentless"');

it('shares the flag document between processes through the cache store', function (): void {
    config()->set('mirafive.flags.cache_store', 'array');
    $this->transport->queue(flagDocument());

    expect(app(MiraFlags::class)->for(userId: 'u_42')->enabled('new-checkout'))->toBeTrue();

    app()->forgetInstance(MiraFlags::class);

    expect(app(MiraFlags::class)->for(userId: 'u_42')->enabled('new-checkout'))->toBeTrue()
        ->and($this->transport->requests)->toHaveCount(1);
});
