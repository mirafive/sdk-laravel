<?php

declare(strict_types=1);

namespace MiraFive\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use MiraFive\Laravel\Client;
use MiraFive\Laravel\Settings;
use MiraFive\Laravel\Testing\MiraFake;
use MiraFive\Mode;

/**
 * @method static void track(string $name, ?string $userId = null, ?string $anonymousId = null, ?string $sessionId = null, array<string, mixed> $properties = [], \DateTimeInterface|int|null $time = null, array{url?: string, title?: string, referrer?: string}|null $page = null)
 * @method static void identify(string $userId, array<string, mixed> $traits = [], ?string $anonymousId = null, \DateTimeInterface|int|null $time = null)
 * @method static \MiraFive\Receipt send(list<array<string, mixed>> $events, ?string $idempotencyKey = null)
 * @method static void flush()
 * @method static \MiraFive\Flags\MiraFlags flags()
 * @method static \MiraFive\Laravel\UserContext forUser(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static bool optedOut(\Illuminate\Http\Request $request)
 * @method static bool currentRequestOptedOut()
 *
 * @see Client
 */
final class Mira extends Facade
{
    /** Resolved on every call, so Octane sandboxes and fake() always see the current container. */
    protected static $cached = false;

    public static function fake(): MiraFake
    {
        $settings = self::getFacadeApplication()?->make(Settings::class);
        $fake = new MiraFake($settings instanceof Settings ? $settings->mode : Mode::Full);
        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
