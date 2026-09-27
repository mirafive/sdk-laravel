<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Route;
use Laravel\Octane\Events\RequestTerminated;
use MiraFive\Laravel\Facades\Mira;
use MiraFive\Laravel\Tests\TestCase;
use MiraFive\Mira as Core;

require_once __DIR__.'/../Stubs/Octane/RequestTerminated.php';

it('sends what a request buffered once, after the response', function (): void {
    Route::get('/signup', function () {
        Mira::track('signup', userId: 'u_42', properties: ['plan' => 'pro']);
        Mira::identify('u_42', ['plan' => 'pro']);

        return response(count($this->transport->requests));
    });

    $this->get('/signup')->assertContent('0');

    app()->terminate();
    app(Core::class)->flush();
    app(Core::class)->__destruct();

    expect($this->transport->batches())->toHaveCount(1)
        ->and($this->transport->batch(0)['events'])->toHaveCount(2)
        ->and($this->transport->batch(0)['events'][0])->toMatchArray(['name' => 'signup', 'userId' => 'u_42', 'properties' => ['plan' => 'pro']])
        ->and($this->transport->batch(0)['events'][1]['name'])->toBe('$identify')
        ->and($this->transport->batches()[0]['url'])->toBe('https://collector.test/v1/batch')
        ->and($this->transport->batches()[0]['headers']['Authorization'])->toBe('Bearer '.TestCase::SECRET_KEY);
});

it('does not build a client just to flush it', function (): void {
    app()->terminate();

    expect(app()->resolved(Core::class))->toBeFalse();
});

it('flushes when an Octane request ends', function (): void {
    Mira::track('signup');

    event(new RequestTerminated(app(), app()));

    expect($this->transport->batches())->toHaveCount(1);
});

it('flushes after each queued job', function (): void {
    Mira::track('export finished');

    event(new JobProcessed('redis', Mockery::mock(Job::class)));

    expect($this->transport->batches())->toHaveCount(1);
});
