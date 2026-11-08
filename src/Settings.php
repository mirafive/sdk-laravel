<?php

declare(strict_types=1);

namespace MiraFive\Laravel;

use MiraFive\Mira;

/** `config/mirafive.php`, read and checked once. */
final readonly class Settings
{
    /**
     * @param  bool|array<string, bool>  $autocapture
     */
    public function __construct(
        public bool $enabled = true,
        public string $secretKey = '',
        public string $publicKey = '',
        public string $host = Mira::DEFAULT_HOST,
        public ?string $queueConnection = null,
        public ?string $queue = null,
        public ?string $scriptSrc = null,
        public string $consent = 'auto',
        public bool|array $autocapture = true,
        public int $timeoutMs = 3_000,
        public int $flushDeadlineMs = 3_000,
        public int $maxRetries = 2,
    ) {}

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        [$connection, $queue] = self::queue($config['queue'] ?? null);
        $script = is_array($config['script'] ?? null) ? $config['script'] : [];
        $autocapture = $script['autocapture'] ?? true;

        return new self(
            enabled: filter_var($config['enabled'] ?? true, FILTER_VALIDATE_BOOL),
            secretKey: self::string($config['secret_key'] ?? null) ?? '',
            publicKey: self::string($config['public_key'] ?? null) ?? '',
            host: rtrim(self::string($config['host'] ?? null) ?? Mira::DEFAULT_HOST, '/'),
            queueConnection: $connection,
            queue: $queue,
            scriptSrc: self::string($script['src'] ?? null),
            consent: self::string($script['consent'] ?? null) ?? 'auto',
            autocapture: is_bool($autocapture) ? $autocapture : self::switches($autocapture),
            timeoutMs: self::int($config['timeout_ms'] ?? null, 3_000),
            flushDeadlineMs: self::int($config['flush_deadline_ms'] ?? null, 3_000),
            maxRetries: self::int($config['max_retries'] ?? null, 2),
        );
    }

    /** Whether server events leave the app. */
    public function sends(): bool
    {
        return $this->enabled && $this->secretKey !== '';
    }

    public function queued(): bool
    {
        return $this->queue !== null || $this->queueConnection !== null;
    }

    /** The key with its secret part hidden, safe to print: `mira_sk_ab12…`. */
    public function maskedSecretKey(): string
    {
        return $this->secretKey === '' ? '(none)' : substr($this->secretKey, 0, 12).'…';
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

        if ($value === null || in_array(strtolower($value), ['false', 'null', 'sync:'], true)) {
            return [null, null];
        }

        if (! str_contains($value, ':')) {
            return [null, $value];
        }

        [$connection, $queue] = explode(':', $value, 2);

        return [self::string($connection), self::string($queue)];
    }

    /**
     * @return array<string, bool>
     */
    private static function switches(mixed $value): array
    {
        $switches = [];

        foreach (is_array($value) ? $value : [] as $name => $on) {
            if (is_string($name)) {
                $switches[$name] = filter_var($on, FILTER_VALIDATE_BOOL);
            }
        }

        return $switches;
    }

    private static function int(mixed $value, int $default): int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : $default;
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
