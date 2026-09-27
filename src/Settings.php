<?php

declare(strict_types=1);

namespace MiraFive\Laravel;

use InvalidArgumentException;
use MiraFive\Mira;
use MiraFive\Mode;

/** `config/mirafive.php`, read and checked once. */
final readonly class Settings
{
    public const string DEFAULT_HOST = Mira::DEFAULT_HOST;

    public const string DEFAULT_SCRIPT_URL = 'https://cdn.mirafive.io/mira.js';

    public function __construct(
        public bool $enabled = true,
        public string $secretKey = '',
        public string $websiteKey = '',
        public string $host = self::DEFAULT_HOST,
        public Mode $mode = Mode::Full,
        public Mode $scriptMode = Mode::Consentless,
        public ?string $queueConnection = null,
        public ?string $queue = null,
        public string $scriptUrl = self::DEFAULT_SCRIPT_URL,
        public int $refreshSeconds = 30,
        public ?string $cacheStore = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        $flags = is_array($config['flags'] ?? null) ? $config['flags'] : [];
        [$connection, $queue] = self::queue($config['queue'] ?? null);

        return new self(
            enabled: filter_var($config['enabled'] ?? true, FILTER_VALIDATE_BOOL),
            secretKey: self::string($config['secret_key'] ?? null) ?? '',
            websiteKey: self::string($config['website_key'] ?? null) ?? '',
            host: rtrim(self::string($config['host'] ?? null) ?? self::DEFAULT_HOST, '/'),
            mode: self::mode('mode', $config['mode'] ?? null, Mode::Full),
            scriptMode: self::mode('script_mode', $config['script_mode'] ?? null, Mode::Consentless),
            queueConnection: $connection,
            queue: $queue,
            scriptUrl: self::string($config['script_url'] ?? null) ?? self::DEFAULT_SCRIPT_URL,
            refreshSeconds: is_numeric($flags['refresh_seconds'] ?? null) ? (int) $flags['refresh_seconds'] : 30,
            cacheStore: self::string($flags['cache_store'] ?? null),
        );
    }

    /** Whether server events and flags reach MIRA FIVE. */
    public function sends(): bool
    {
        return $this->enabled && $this->secretKey !== '';
    }

    public function queued(): bool
    {
        return $this->queue !== null || $this->queueConnection !== null;
    }

    /** The namespace part of the secret key (`mf_ab12cd34`), safe to print. */
    public function keyNamespace(): string
    {
        $cut = strrpos($this->secretKey, '_');

        return $cut === false ? 'default' : substr($this->secretKey, 0, $cut);
    }

    private static function mode(string $key, mixed $value, Mode $default): Mode
    {
        $value = self::string($value);

        if ($value === null) {
            return $default;
        }

        return Mode::tryFrom($value) ?? throw new InvalidArgumentException("mirafive.{$key} is \"full\" or \"consentless\", not \"{$value}\".");
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private static function queue(mixed $value): array
    {
        if ($value === true) {
            return [null, 'default'];
        }

        $value = self::string($value);

        if ($value === null || in_array(strtolower($value), ['false', 'null'], true)) {
            return [null, null];
        }

        if (! str_contains($value, ':')) {
            return [null, $value];
        }

        [$connection, $queue] = explode(':', $value, 2);

        return [self::string($connection), self::string($queue)];
    }

    private static function string(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
