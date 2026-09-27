<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use MiraFive\Laravel\Facades\Mira;

dataset('off', [
    'disabled' => fn () => config()->set('mirafive.enabled', false),
    'without a secret key' => fn () => config()->set('mirafive.secret_key', null),
]);

it('records nothing and reports nothing', function (Closure $switchOff): void {
    $switchOff();
    Log::spy();

    Mira::track('signup', userId: 'u_42', properties: ['plan' => 'pro']);
    Mira::identify('u_42');
    $receipt = Mira::send([['name' => 'order completed']]);
    Mira::flush();
    app()->terminate();

    expect($this->transport->requests)->toBeEmpty()
        ->and($receipt->dropped)->toBe(0)
        ->and(Mira::flags()->for(userId: 'u_42')->enabled('new-checkout', true))->toBeTrue()
        ->and(Mira::flags()->for(userId: 'u_42')->variant('pricing', 'control'))->toBe('control');

    Log::shouldNotHaveReceived('warning');
})->with('off');

it('prints no tracker tag when disabled', function (): void {
    config()->set('mirafive.enabled', false);
    config()->set('mirafive.website_key', 'mf_ab12cd34_site');

    expect(Blade::render('@mirafiveScript'))->toBe('');
});
