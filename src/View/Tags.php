<?php

declare(strict_types=1);

namespace MiraFive\Laravel\View;

use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use InvalidArgumentException;
use MiraFive\Flags\UserFlags;
use MiraFive\Laravel\Client;
use MiraFive\Laravel\Settings;
use MiraFive\Mode;

/** What `@mirafiveScript` and `@mirafiveFlags` print. */
final class Tags
{
    private const string QUEUE = 'window.mirafive=window.mirafive||function(){(mirafive.q=mirafive.q||[]).push(arguments)}';

    private const array UNIT_KEYS = ['userId', 'anonymousId', 'properties', 'consent', 'optedOut'];

    /** Request attribute set once a bootstrap was printed; SendBootstrapHeaders reads it. */
    public const string BOOTSTRAPPED = 'mirafive.bootstrapped';

    public function __construct(
        private readonly Settings $settings,
        private readonly Container $app,
    ) {}

    /**
     * The hosted tracker with the website key. Nothing without a key or when disabled.
     *
     * @param  array<string, string|bool|null>  $attributes  more `data-*` attributes (`true` prints a bare one) or a `nonce`
     */
    public function script(array $attributes = []): string
    {
        if (! $this->settings->enabled || $this->settings->websiteKey === '') {
            return '';
        }

        $nonce = $attributes['nonce'] ?? $this->viteNonce();
        unset($attributes['nonce']);
        $nonce = is_string($nonce) && $nonce !== '' ? ' nonce="'.e($nonce).'"' : '';

        $tag = [
            'defer' => true,
            'src' => $this->settings->scriptUrl,
            'data-key' => $this->settings->websiteKey,
            'data-host' => $this->settings->host === Settings::DEFAULT_HOST ? null : $this->settings->host,
            'data-mode' => $this->settings->scriptMode === Mode::Full ? 'full' : null,
            ...$attributes,
        ];

        return "<script{$nonce}>".self::QUEUE."</script>\n<script{$nonce}".self::attributes($tag).'></script>';
    }

    /**
     * The flag bootstrap block for the browser SDK. The response then goes out with `Cache-Control: private, no-store`.
     *
     * @param  UserFlags|array{userId?: string|int|null, anonymousId?: string|null, properties?: array<string, mixed>, consent?: array{experiments?: bool, targeting?: bool}, optedOut?: bool}  $unit
     */
    public function flags(UserFlags|array $unit = []): string
    {
        if ($this->app->bound('request')) {
            $this->app->make(Request::class)->attributes->set(self::BOOTSTRAPPED, true);
        }

        return ($unit instanceof UserFlags ? $unit : $this->flagsFor($unit))->bootstrap();
    }

    /**
     * @param  array<array-key, mixed>  $unit
     */
    private function flagsFor(array $unit): UserFlags
    {
        $unknown = array_diff(array_keys($unit), self::UNIT_KEYS);

        if ($unknown !== []) {
            throw new InvalidArgumentException('@mirafiveFlags takes userId, anonymousId, properties, consent and optedOut, not '.implode(', ', $unknown).'.');
        }

        $userId = $unit['userId'] ?? null;
        $anonymousId = $unit['anonymousId'] ?? null;
        $properties = $unit['properties'] ?? [];
        $consent = $unit['consent'] ?? [];
        $optedOut = $unit['optedOut'] ?? $this->requestOptedOut();

        if ((! is_string($userId) && ! is_int($userId) && $userId !== null) || (! is_string($anonymousId) && $anonymousId !== null)
            || ! is_array($properties) || ! is_array($consent) || ! is_bool($optedOut)) {
            throw new InvalidArgumentException('@mirafiveFlags: userId and anonymousId are strings, properties and consent arrays, optedOut a bool.');
        }

        /** @var array<string, mixed> $properties */
        /** @var array{experiments?: bool, targeting?: bool} $consent */
        return $this->app->make(Client::class)->flags()->for(
            $userId === null ? null : (string) $userId,
            $anonymousId,
            $properties,
            $consent,
            $optedOut,
        );
    }

    private function requestOptedOut(): bool
    {
        return $this->app->bound('request') && $this->app->make(Client::class)->optedOut($this->app->make(Request::class));
    }

    private function viteNonce(): ?string
    {
        return class_exists(Vite::class) ? $this->app->make(Vite::class)->cspNonce() : null;
    }

    /**
     * @param  array<string, string|bool|null>  $attributes
     */
    private static function attributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            if (preg_match('/^[a-z][a-z0-9-]*$/', $name) !== 1) {
                throw new InvalidArgumentException("@mirafiveScript: \"{$name}\" is not an attribute name.");
            }

            if ($value === null || $value === false) {
                continue;
            }

            $html .= $value === true ? " {$name}" : " {$name}=\"".e($value).'"';
        }

        return $html;
    }
}
