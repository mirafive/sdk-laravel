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

it('hands the buffered batch to a job instead of sending it', function (): void {
    Bus::fake();

    Mira::track('signup', userId: 'u_42');
    app()->terminate();

    expect($this->transport->requests)->toBeEmpty();

    Bus::assertDispatchedTimes(SendBatch::class, 1);
    Bus::assertDispatched(SendBatch::class, function (SendBatch $job): bool {
        $batch = json_decode($job->body, true);

        return $job->connection === 'redis' && $job->queue === 'analytics'
            && $batch['events'][0]['name'] === 'signup' && $batch['events'][0]['userId'] === 'u_42'
            && ! str_contains($job->body, 'secretTail');
    });
});

it('delivers the body byte for byte however often the job runs', function (): void {
    Bus::fake();
    Mira::track('signup', userId: 'u_42', time: 1_727_430_000_000);
    app()->terminate();

    $job = Bus::dispatched(SendBatch::class)->first();

    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    expect($this->transport->batches())->toHaveCount(2)
        ->and($this->transport->batches()[0]['body'])->toBe($job->body)
        ->and($this->transport->batches()[1]['body'])->toBe($job->body)
        ->and($this->transport->batches()[0]['headers']['Authorization'])->toBe('Bearer mf_ab12cd34_secretTail');
});

it('sends send() immediately in queue mode', function (): void {
    Bus::fake();

    $receipt = Mira::send([['name' => 'order completed']], idempotencyKey: 'order-981');

    expect($receipt->accepted)->toBe(1)
        ->and($this->transport->batches())->toHaveCount(1);

    Bus::assertNothingDispatched();
});

it('lets the queue retry a retryable failure', function (): void {
    $this->transport->queue(answer(503), answer(503), answer(503));

    app()->call([new SendBatch(json_encode(['v' => 1, 'batch' => '0192d4a8-7b1c-4e8a-9c1d-2b3e4f5a6b7c', 'mode' => 'full', 'events' => [['name' => 'signup']]])), 'handle']);
})->throws(MiraError::class);

it('logs and drops a batch the collector refuses', function (): void {
    Log::spy();
    $this->transport->queue(answer(400, ['code' => 'validation_failed', 'detail' => 'no']));

    app()->call([new SendBatch(json_encode(['v' => 1, 'batch' => '0192d4a8-7b1c-4e8a-9c1d-2b3e4f5a6b7c', 'mode' => 'full', 'events' => [['name' => 'signup']]])), 'handle']);

    Log::shouldHaveReceived('warning')->once();
});

it('logs and drops a job body that is not a batch', function (): void {
    Log::spy();

    app()->call([new SendBatch('not json'), 'handle']);

    expect($this->transport->requests)->toBeEmpty();
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
    $job = (new SendBatch(json_encode(['v' => 1, 'batch' => '0192d4a8-7b1c-4e8a-9c1d-2b3e4f5a6b7c', 'mode' => 'full', 'events' => [['name' => 'signup']]])))->withFakeQueueInteractions();

    app()->call([$job, 'handle']);

    $job->assertNotFailed();
    expect($this->transport->requests)->toBeEmpty();
    Log::shouldNotHaveReceived('error');
});

it('fails the job without retrying on a worker without a secret key', function (): void {
    config()->set('mirafive.secret_key', null);
    Log::spy();
    $job = (new SendBatch(json_encode(['v' => 1, 'batch' => '0192d4a8-7b1c-4e8a-9c1d-2b3e4f5a6b7c', 'mode' => 'full', 'events' => [['name' => 'signup']]])))->withFakeQueueInteractions();

    app()->call([$job, 'handle']);

    $job->assertFailed();
    expect($this->transport->requests)->toBeEmpty();
    Log::shouldHaveReceived('error')->once();
});
