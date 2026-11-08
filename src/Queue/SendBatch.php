<?php

declare(strict_types=1);

namespace MiraFive\Laravel\Queue;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use MiraFive\Laravel\Settings;
use MiraFive\Mira;
use MiraFive\MiraError;
use Psr\Log\LoggerInterface;

/**
 * One batch as the client handed it off. The worker sends it with its own key; the events and
 * their sequence number are final, so a job that runs twice is stored once.
 */
final class SendBatch implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 5;

    public function __construct(
        public string $payload,
        public ?string $connection = null,
        public ?string $queue = null,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(Mira $mira, Settings $settings, LoggerInterface $logger): void
    {
        // Switched off on this worker: dropped on purpose, as everything else is.
        if (! $settings->enabled) {
            return;
        }

        if ($settings->secretKey === '') {
            $logger->error('[mirafive] A queued batch cannot be sent: MIRAFIVE_SECRET_KEY is not set on this worker.');
            $this->fail(new MiraError('no secret key on this worker: set MIRAFIVE_SECRET_KEY'));

            return;
        }

        try {
            $mira->deliverPrepared($this->payload);
        } catch (MiraError $error) {
            if ($error->retryable) {
                throw $error;
            }

            $logger->warning('[mirafive] {message}', ['message' => $error->getMessage(), 'exception' => $error, 'event_ids' => $error->eventIds]);
        }
    }
}
