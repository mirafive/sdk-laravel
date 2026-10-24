<?php

declare(strict_types=1);

namespace MiraFive\Laravel\View;

use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Vite;
use InvalidArgumentException;
use MiraFive\Laravel\Settings;
use MiraFive\Mode;

/** What `@mirafiveScript` prints. */
final class Tags
{
    private const string QUEUE = 'window.mirafive=window.mirafive||function(){(mirafive.q=mirafive.q||[]).push(arguments)}';

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
