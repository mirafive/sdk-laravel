<?php

declare(strict_types=1);

namespace MiraFive\Laravel\View;

use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Vite;
use InvalidArgumentException;
use MiraFive\Laravel\Settings;
use MiraFive\MiraError;
use MiraFive\ScriptTag as Tag;
use Psr\Log\LoggerInterface;

/** What `@mirafiveScript` prints: the browser SDK with the public key. */
final readonly class ScriptTag
{
    private const array OPTIONS = ['consent', 'autocapture', 'src', 'host', 'debug', 'nonce'];

    public function __construct(
        private Settings $settings,
        private Container $app,
        private LoggerInterface $logger,
    ) {}

    /**
     * Nothing when disabled or without a public key. A secret key is never printed: the tag is
     * replaced by an HTML comment and the problem is logged.
     *
     * @param  array<string, mixed>  $options  consent ('auto', 'granted', 'denied', 'analytics'), autocapture (bool or ['forms' => false, …]), src, host, debug, nonce
     */
    public function render(array $options = []): string
    {
        $unknown = array_diff(array_keys($options), self::OPTIONS);

        if ($unknown !== []) {
            throw new InvalidArgumentException('@mirafiveScript does not know '.implode(', ', $unknown).'; it takes '.implode(', ', self::OPTIONS).'.');
        }

        if (! $this->settings->enabled) {
            return '';
        }

        try {
            return Tag::render(
                publicKey: $this->settings->publicKey,
                host: self::string($options, 'host') ?? $this->settings->host,
                src: self::string($options, 'src') ?? $this->settings->scriptSrc,
                consent: self::string($options, 'consent') ?? $this->settings->consent,
                autocapture: self::autocapture($options['autocapture'] ?? $this->settings->autocapture),
                debug: ($options['debug'] ?? false) === true,
                nonce: self::string($options, 'nonce') ?? $this->viteNonce(),
            );
        } catch (MiraError $error) {
            $this->logger->error('[mirafive] '.$error->getMessage());

            return '<!-- MIRA FIVE: the script tag was not printed, see the log -->';
        }
    }

    private function viteNonce(): ?string
    {
        return class_exists(Vite::class) && $this->app->bound(Vite::class) ? $this->app->make(Vite::class)->cspNonce() : null;
    }

    /**
     * @return bool|array<string, bool>
     */
    private static function autocapture(mixed $value): bool|array
    {
        if (is_bool($value)) {
            return $value;
        }

        $switches = [];

        foreach (is_array($value) ? $value : [] as $name => $on) {
            $switches[(string) $name] = $on === true;
        }

        return $switches;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function string(array $options, string $name): ?string
    {
        $value = $options[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
