<?php

declare(strict_types=1);

namespace MiraFive\Laravel\Facades;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use InvalidArgumentException;
use MiraFive\Laravel\Settings;
use MiraFive\Mira as Client;
use MiraFive\Testing\MiraFake;

/**
 * The container's `MiraFive\Mira`. Events wait until the response was sent, then leave in batches
 * (or go to the queue, with `mirafive.queue`).
 *
 * @method static void track(string $event, ?string $userId = null, ?string $anonymousId = null, array<array-key, mixed>|object $properties = [], ?string $eventId = null, \DateTimeInterface|int|string|null $timestamp = null, \MiraFive\Consent|string|array<string, mixed>|null $consent = null, array<string, mixed>|null $context = null)
 * @method static void identify(string $userId, array<array-key, mixed>|object $traits = [], array<string, string> $externalIds = [], ?string $anonymousId = null, ?string $eventId = null, \DateTimeInterface|int|string|null $timestamp = null, \MiraFive\Consent|string|array<string, mixed>|null $consent = null, array<string, mixed>|null $context = null)
 * @method static void group(string $groupId, ?string $userId = null, array<array-key, mixed>|object $traits = [], ?string $anonymousId = null, ?string $eventId = null, \DateTimeInterface|int|string|null $timestamp = null, \MiraFive\Consent|string|array<string, mixed>|null $consent = null, array<string, mixed>|null $context = null)
 * @method static void revenue(int|float $amount, string $currency, ?string $userId = null, ?string $anonymousId = null, ?string $entryId = null, ?string $entryKind = null, int|float|null $fee = null, int|float|null $tax = null, ?string $refundOf = null, ?string $product = null, array<string, mixed>|null $subscription = null, array<array-key, mixed>|object $properties = [], ?string $eventId = null, \DateTimeInterface|int|string|null $timestamp = null, \MiraFive\Consent|string|array<string, mixed>|null $consent = null, array<string, mixed>|null $context = null)
 * @method static void touchpoint(string $kind, ?string $userId = null, ?string $anonymousId = null, ?string $channel = null, ?string $source = null, ?string $medium = null, ?string $campaign = null, ?string $content = null, ?string $campaignId = null, ?string $adGroupId = null, ?string $adId = null, ?string $code = null, ?string $answer = null, ?string $externalRef = null, array<array-key, mixed>|object $properties = [], ?string $eventId = null, \DateTimeInterface|int|string|null $timestamp = null, \MiraFive\Consent|string|array<string, mixed>|null $consent = null, array<string, mixed>|null $context = null)
 * @method static \MiraFive\Mira with(?string $userId = null, ?string $anonymousId = null, \MiraFive\Consent|string|array<string, mixed>|null $consent = null, array<string, mixed>|null $context = null)
 * @method static string eventIdFor(string $key)
 * @method static void flush()
 * @method static \MiraFive\BatchResult deliverPrepared(string $payload)
 * @method static void checkConnection()
 * @method static \MiraFive\PersonErasure deletePerson(string $userId)
 *
 * @see Client
 */
final class Mira extends Facade
{
    /** Resolved on every call, so Octane sandboxes and fake() always see the current container. */
    protected static $cached = false;

    /** A client that sends as this user: `getAuthIdentifier()` becomes the user id. */
    public static function forUser(Authenticatable $user): Client
    {
        $id = $user->getAuthIdentifier();

        if (! is_string($id) && ! is_int($id)) {
            throw new InvalidArgumentException('Mira::forUser() needs a user whose getAuthIdentifier() is a string or an integer.');
        }

        return self::client()->with(userId: (string) $id);
    }

    /**
     * A client that sends this request's IP and user agent with every event, for geo and the
     * device. MIRA uses the IP at ingest and never stores it.
     */
    public static function forRequest(Request $request): Client
    {
        return self::client()->with(context: array_filter([
            'ip' => $request->ip(),
            'userAgent' => $request->userAgent(),
        ], static fn (?string $value): bool => $value !== null && $value !== ''));
    }

    /** Records instead of sending, for tests. Returns the fake with its assertions. */
    public static function fake(): MiraFake
    {
        $app = self::getFacadeApplication();
        $settings = $app?->make(Settings::class);
        $fake = MiraFake::create($settings instanceof Settings ? $settings->host : null);
        $app?->instance(Client::class, $fake->mira);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }

    private static function client(): Client
    {
        $client = self::getFacadeRoot();

        if (! $client instanceof Client) {
            throw new InvalidArgumentException('The Mira facade needs MiraFive\Mira in the container.');
        }

        return $client;
    }
}
