<?php

declare(strict_types=1);

namespace MiraFive\Laravel;

use DateTimeInterface;

/** `Mira::forUser($request->user())`: the same calls with the user id filled in. */
final readonly class UserContext
{
    public function __construct(
        private Client $client,
        public string $userId,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @param  array{url?: string, title?: string, referrer?: string}|null  $page
     */
    public function track(
        string $name,
        array $properties = [],
        ?string $anonymousId = null,
        ?string $sessionId = null,
        DateTimeInterface|int|null $time = null,
        ?array $page = null,
    ): void {
        $this->client->track($name, $this->userId, $anonymousId, $sessionId, $properties, $time, $page);
    }

    /**
     * @param  array<string, mixed>  $traits
     */
    public function identify(array $traits = [], ?string $anonymousId = null): void
    {
        $this->client->identify($this->userId, $traits, $anonymousId);
    }
}
