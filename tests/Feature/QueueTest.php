<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use MiraFive\Laravel\Facades\Mira;
use MiraFive\Laravel\Queue\SendBatch;
use MiraFive\MiraError;

beforeEach(function (): void {
    config()->set('mirafive.queue', 'redis:analytics');
});

it('hands the batch to a job instead of sending it', function (): void {
    Bus::fake();

    Mira::track('signup', userId: 'u_42');
    app()->terminate();

    expect($this->transport->requests())->toBeEmpty();

    Bus::assertDispatchedTimes(SendBatch::class, 1);
    Bus::assertDispatched(SendBatch::class, function (SendBatch $job): bool {
        $batch = json_decode($job->payload, true);

        return $job->connection === 'redis' && $job->queue === 'analytics'
            && $batch['seq'] === 1 && $batch['events'][0]['event'] === 'signup' && $batch['events'][0]['user_id'] === 'u_42'
            && ! str_contains($job->payload, 'secretTail');
    });
});

it('delivers the same events and seq however often the job runs', function (): void {
    Bus::fake();
    Mira::track('signup', userId: 'u_42');
    app()->terminate();

    $job = Bus::dispatched(SendBatch::class)->first();

    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    [$first, $second] = $this->transport->requests();

    expect($first['batch']['events'])->toBe($second['batch']['events'])
        ->and($first['batch']['seq'])->toBe($second['batch']['seq'])
        ->and($first['headers']['Authorization'])->toBe('Bearer mira_sk_live_secretTail');
});

it('lets the queue retry a retryable failure', function (): void {
    $this->transport->answerNext(answer(503), answer(503), answer(503));

    app()->call([new SendBatch('{"stream":"s","seq":1,"events":[{"event_id":"x"}]}'), 'handle']);
})->throws(MiraError::class);

it('logs and drops a batch the ingest refuses for good', function (): void {
    Log::spy();
    $this->transport->answerNext(answer(400, ['error' => 'invalid_batch']));

    app()->call([new SendBatch('{"stream":"s","seq":1,"events":[{"event_id":"x"}]}'), 'handle']);

    Log::shouldHaveReceived('warning')->once();
});

it('logs and drops a payload that is not a batch', function (): void {
    Log::spy();

    app()->call([new SendBatch('not json'), 'handle']);

    expect($this->transport->requests())->toBeEmpty();
    Log::shouldHaveReceived('warning')->once();
});

it('runs inline on the sync connection', function (): void {
    config()->set('mirafive.queue', 'sync:default');

    Mira::track('signup');
    app()->terminate();

    expect($this->transport->batches())->toHaveCount(1);
});

it('drops queued batches quietly on a worker that is switched off', function (): void {
    config()->set('mirafive.enabled', false);
    Log::spy();
    $job = (new SendBatch('{"stream":"s","seq":1,"events":[{}]}'))->withFakeQueueInteractions();

    app()->call([$job, 'handle']);

    $job->assertNotFailed();
    expect($this->transport->requests())->toBeEmpty();
    Log::shouldNotHaveReceived('error');
});

it('fails the job without retrying on a worker without a secret key', function (): void {
    config()->set('mirafive.secret_key', null);
    Log::spy();
    $job = (new SendBatch('{"stream":"s","seq":1,"events":[{}]}'))->withFakeQueueInteractions();

    app()->call([$job, 'handle']);

    $job->assertFailed();
    expect($this->transport->requests())->toBeEmpty();
    Log::shouldHaveReceived('error')->once();
});
