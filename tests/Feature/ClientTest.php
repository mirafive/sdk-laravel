<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Route;
use Laravel\Octane\Events\RequestTerminated;
use MiraFive\Http\Transport;
use MiraFive\Laravel\Facades\Mira;
use MiraFive\Laravel\MiraFiveServiceProvider;
use MiraFive\Laravel\Settings;
use MiraFive\Mira as Client;

it('binds one configured client', function (): void {
    $client = app(Client::class);

    expect($client)->toBe(app(Client::class))
        ->and($client->host)->toBe('https://ingest.test')
        ->and($client->sends)->toBeTrue();
});

it('sends what a request tracked after the response', function (): void {
    Route::post('/checkout', function () {
        Mira::track('checkout_completed', userId: 'u_42', properties: ['items' => 3]);
        Mira::revenue(4900, 'EUR', userId: 'u_42', entryId: 'stripe:in_1');

        expect($this->transport->requests())->toBe([]);

        return 'ok';
    });

    $this->post('/checkout')->assertOk();
    app()->terminate();

    $request = $this->transport->requests()[0];

    expect($request['url'])->toBe('https://ingest.test/v1/batch')
        ->and($request['headers']['Authorization'])->toBe('Bearer mira_sk_live_secretTail')
        ->and(array_column($this->sent(), 'event'))->toBe(['checkout_completed', '$revenue']);
});

it('sends as the signed-in user', function (): void {
    $user = new User;
    $user->forceFill(['id' => 42]);

    Mira::forUser($user)->track('report_exported');
    Mira::flush();

    expect($this->sent()[0]['user_id'])->toBe('42');
});

it('sends the request\'s IP and user agent', function (): void {
    $request = Request::create('/x', server: ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'Safari']);

    Mira::forRequest($request)->track('viewed');
    Mira::flush();

    expect($this->sent()[0]['context'])->toMatchArray(['ip' => '203.0.113.7', 'user_agent' => 'Safari']);
});

it('makes stable event ids', function (): void {
    expect(Mira::eventIdFor('order:123:paid'))->toBe('dc7e1871-544e-8ba6-bf35-0db3d8768abf');
});

it('sends nothing and logs nothing without a secret key', function (): void {
    config()->set('mirafive.secret_key', null);
    app()->forgetInstance(Client::class);
    app()->forgetInstance(Settings::class);

    Mira::track('signup');
    app()->terminate();

    expect(app(Client::class)->sends)->toBeFalse()
        ->and($this->transport->requests())->toBe([]);
});

it('sends nothing when switched off', function (): void {
    config()->set('mirafive.enabled', false);
    app()->forgetInstance(Client::class);
    app()->forgetInstance(Settings::class);

    Mira::track('signup');
    app()->terminate();

    expect($this->transport->requests())->toBe([]);
});

it('flushes after each Octane request in the request sandbox', function (): void {
    Mira::track('octane');
    event(new RequestTerminated(app(), app()));

    expect($this->transport->requests())->toHaveCount(1);
});

it('flushes after each queued job', function (): void {
    Mira::track('in_a_job');
    event(new JobProcessed('sync', Mockery::mock(Job::class)));

    expect($this->transport->requests())->toHaveCount(1);
});

it('adds the client to octane.warm when Octane is configured', function (): void {
    expect(config('octane.warm'))->toBeNull();

    config()->set('octane.warm', ['view']);
    (new MiraFiveServiceProvider(app()))->boot();

    expect(config('octane.warm'))->toBe(['view', Transport::class, Client::class]);
});
