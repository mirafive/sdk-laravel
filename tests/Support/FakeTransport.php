<?php

declare(strict_types=1);

namespace MiraFive\Laravel\Tests\Support;

use MiraFive\Http\Response;
use MiraFive\Http\Transport;
use Throwable;

/** Answers queued responses in order; without one, a batch is accepted and a flag fetch finds nothing new. */
final class FakeTransport implements Transport
{
    /** @var list<Response|Throwable> */
    private array $answers = [];

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string|null}> */
    public array $requests = [];

    public function queue(Response|Throwable ...$answers): self
    {
        array_push($this->answers, ...$answers);

        return $this;
    }

    public function request(string $method, string $url, array $headers, ?string $body, int $timeoutMs): Response
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        $answer = array_shift($this->answers);

        if ($answer instanceof Throwable) {
            throw $answer;
        }

        if ($answer !== null) {
            return $answer;
        }

        if ($method !== 'POST') {
            return new Response(304);
        }

        $batch = json_decode($body ?? '', true);

        return new Response(202, [], json_encode([
            'batch' => $batch['batch'] ?? null,
            'accepted' => count($batch['events'] ?? []),
            'dropped' => 0,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return list<array{method: string, url: string, headers: array<string, string>, body: string|null}> */
    public function batches(): array
    {
        return array_values(array_filter($this->requests, fn (array $request): bool => str_ends_with($request['url'], '/v1/batch')));
    }

    /** @return array<array-key, mixed> */
    public function batch(int $index): array
    {
        return json_decode($this->batches()[$index]['body'] ?? '', true, flags: JSON_THROW_ON_ERROR);
    }
}
