<?php

declare(strict_types=1);

namespace MiraFive\Laravel\Testing;

use DateTimeInterface;
use MiraFive\Laravel\Client;
use MiraFive\Mira;
use MiraFive\Mode;
use MiraFive\Receipt;
use PHPUnit\Framework\Assert;

/**
 * Records calls instead of sending them. Input is still checked by the core, so an event the collector would refuse
 * fails the test as it would fail in production. Flags answer their fallbacks.
 *
 * @phpstan-type TrackedEvent array{name: string, userId: string|null, anonymousId: string|null, sessionId: string|null, properties: array<array-key, mixed>, time: DateTimeInterface|int|null, page: array<array-key, mixed>|null}
 * @phpstan-type Identified array{userId: string, traits: array<string, mixed>, anonymousId: string|null}
 */
class MiraFake extends Client
{
    /** @var list<TrackedEvent> */
    private array $tracked = [];

    /** @var list<Identified> */
    private array $identified = [];

    public function __construct(Mode $mode = Mode::Full)
    {
        $mira = new Mira(key: '', host: Mira::DEFAULT_HOST, mode: $mode, enabled: false, flushOnShutdown: false);

        parent::__construct($mira, $mira->flags(...));
    }

    public function track(
        string $name,
        ?string $userId = null,
        ?string $anonymousId = null,
        ?string $sessionId = null,
        array $properties = [],
        DateTimeInterface|int|null $time = null,
        ?array $page = null,
    ): void {
        parent::track($name, $userId, $anonymousId, $sessionId, $properties, $time, $page);
        $this->tracked[] = compact('name', 'userId', 'anonymousId', 'sessionId', 'properties', 'time', 'page');
    }

    public function identify(string $userId, array $traits = [], ?string $anonymousId = null, DateTimeInterface|int|null $time = null): void
    {
        parent::identify($userId, $traits, $anonymousId, $time);
        $this->identified[] = compact('userId', 'traits', 'anonymousId');
    }

    public function send(array $events, ?string $idempotencyKey = null): Receipt
    {
        $receipt = parent::send($events, $idempotencyKey);

        foreach ($events as $event) {
            $this->tracked[] = [
                'name' => is_string($event['name'] ?? null) ? $event['name'] : '',
                'userId' => is_string($event['userId'] ?? null) ? $event['userId'] : null,
                'anonymousId' => is_string($event['anonymousId'] ?? null) ? $event['anonymousId'] : null,
                'sessionId' => is_string($event['sessionId'] ?? null) ? $event['sessionId'] : null,
                'properties' => is_array($event['properties'] ?? null) ? $event['properties'] : [],
                'time' => ($event['time'] ?? null) instanceof DateTimeInterface || is_int($event['time'] ?? null) ? $event['time'] : null,
                'page' => is_array($event['page'] ?? null) ? $event['page'] : null,
            ];
        }

        return $receipt;
    }

    /**
     * Events recorded by track() and send(), optionally only those with this name.
     *
     * @return list<TrackedEvent>
     */
    public function tracked(?string $name = null): array
    {
        return array_values(array_filter($this->tracked, fn (array $event): bool => $name === null || $event['name'] === $name));
    }

    /**
     * @return list<Identified>
     */
    public function identified(): array
    {
        return $this->identified;
    }

    /**
     * @param  (callable(TrackedEvent): bool)|int|null  $callback  a filter, or how many times exactly
     */
    public function assertTracked(string $name, callable|int|null $callback = null): void
    {
        if (is_int($callback)) {
            $count = count($this->tracked($name));
            Assert::assertSame($callback, $count, "The event [{$name}] was tracked {$count} times instead of {$callback} times.");

            return;
        }

        Assert::assertNotEmpty($this->matching($name, $callback), "The expected event [{$name}] was not tracked.");
    }

    /**
     * @param  (callable(TrackedEvent): bool)|null  $callback
     */
    public function assertNotTracked(string $name, ?callable $callback = null): void
    {
        Assert::assertEmpty($this->matching($name, $callback), "The unexpected event [{$name}] was tracked.");
    }

    /**
     * @param  (callable(Identified): bool)|null  $callback
     */
    public function assertIdentified(?string $userId = null, ?callable $callback = null): void
    {
        $matching = array_filter(
            $this->identified,
            fn (array $call): bool => ($userId === null || $call['userId'] === $userId) && ($callback === null || $callback($call)),
        );

        Assert::assertNotEmpty($matching, $userId === null ? 'No user was identified.' : "The user [{$userId}] was not identified.");
    }

    /** Nothing was tracked, sent or identified. */
    public function assertNothingTracked(): void
    {
        $names = array_map(fn (array $event): string => $event['name'], $this->tracked);

        Assert::assertEmpty($names, 'Events were tracked: '.implode(', ', $names).'.');
        Assert::assertEmpty($this->identified, 'A user was identified.');
    }

    /**
     * @param  (callable(TrackedEvent): bool)|null  $callback
     * @return list<TrackedEvent>
     */
    private function matching(string $name, ?callable $callback): array
    {
        return array_values(array_filter($this->tracked($name), fn (array $event): bool => $callback === null || $callback($event)));
    }
}
