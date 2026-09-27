<?php

declare(strict_types=1);

namespace MiraFive\Laravel;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MiraFive\Flags\MiraFlags;
use MiraFive\Mira;
use MiraFive\MiraError;
use MiraFive\Receipt;

/** What the `Mira` facade calls: the container's `MiraFive\Mira` and `MiraFlags`, plus Laravel conveniences. */
class Client
{
    /**
     * @param  Closure(): MiraFlags  $flags
     */
    public function __construct(
        private readonly Mira $mira,
        private readonly Closure $flags,
    ) {}

    /**
     * Buffers one event; sent after the response. Pass your own pseudonymous user id, never an email address.
     *
     * @param  array<string, mixed>  $properties
     * @param  array{url?: string, title?: string, referrer?: string}|null  $page
     */
    public function track(
        string $name,
        ?string $userId = null,
        ?string $anonymousId = null,
        ?string $sessionId = null,
        array $properties = [],
        DateTimeInterface|int|null $time = null,
        ?array $page = null,
    ): void {
        $this->mira->track($name, $userId, $anonymousId, $sessionId, $properties, $time, $page);
    }

    /**
     * @param  array<string, mixed>  $traits
     */
    public function identify(string $userId, array $traits = [], ?string $anonymousId = null, DateTimeInterface|int|null $time = null): void
    {
        $this->mira->identify($userId, $traits, $anonymousId, $time);
    }

    /**
     * Sends now and returns the receipt, also in queue mode.
     *
     * @param  list<array<string, mixed>>  $events
     *
     * @throws MiraError
     */
    public function send(array $events, ?string $idempotencyKey = null): Receipt
    {
        return $this->mira->send($events, $idempotencyKey);
    }

    public function flush(): void
    {
        $this->mira->flush();
    }

    public function flags(): MiraFlags
    {
        return ($this->flags)();
    }

    /** Tracks, identifies and reads flags as this user, with getAuthIdentifier() as the user id. */
    public function forUser(Authenticatable $user): UserContext
    {
        $id = $user->getAuthIdentifier();

        if (! is_string($id) && ! is_int($id)) {
            throw new InvalidArgumentException('forUser() needs a user whose getAuthIdentifier() is a string or an integer.');
        }

        return new UserContext($this, (string) $id);
    }

    /** Whether the request opts out of tracking (`Sec-GPC: 1` or `DNT: 1`), for MiraFlags::for(optedOut: …). */
    public function optedOut(Request $request): bool
    {
        return $request->headers->get('Sec-GPC') === '1' || $request->headers->get('DNT') === '1';
    }
}
