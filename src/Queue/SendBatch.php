<?php

declare(strict_types=1);

namespace MiraFive\Laravel\Queue;

use Illuminate\Contracts\Queue\ShouldQueue;
use MiraFive\Mira;
use MiraFive\MiraError;
use Psr\Log\LoggerInterface;

/**
 * One buffered batch as the core encoded it. The worker sends it with its own key; the body is final, so a job that
 * runs twice is stored once.
 */
final class SendBatch implements ShouldQueue
{
    public int $tries = 5;

    public function __construct(
        public string $body,
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

    public function handle(Mira $mira, LoggerInterface $logger): void
    {
        try {
            $mira->deliverPrepared($this->body);
        } catch (MiraError $error) {
            if ($error->retryable) {
                throw $error;
            }

            $logger->warning('[mirafive] {message}', ['message' => $error->getMessage(), 'exception' => $error]);
        }
    }
}
