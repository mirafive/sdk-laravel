<?php

declare(strict_types=1);

namespace MiraFive\Laravel\Console;

use Illuminate\Console\Command;
use MiraFive\Laravel\Settings;
use MiraFive\Mira;
use MiraFive\MiraError;
use MiraFive\ScriptTag;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'mirafive:check')]
final class CheckCommand extends Command
{
    protected $signature = 'mirafive:check';

    protected $description = 'Check the MIRA FIVE keys and host: the ingest answers whether the key works and stores nothing';

    public function handle(Settings $settings, Mira $mira): int
    {
        $this->components->twoColumnDetail('Host', $settings->host);
        $this->components->twoColumnDetail('Secret key', $settings->maskedSecretKey());
        $this->components->twoColumnDetail('Public key', $settings->publicKey === '' ? '(none, @mirafiveScript prints nothing)' : $settings->publicKey);
        $this->components->twoColumnDetail('Delivery', $settings->queued() ? 'queue '.($settings->queueConnection ?? 'default').':'.($settings->queue ?? 'default') : 'after the response');

        if (! $settings->enabled) {
            $this->components->error('MIRA FIVE is switched off (MIRAFIVE_ENABLED=false).');

            return self::FAILURE;
        }

        if ($settings->publicKey !== '' && ScriptTag::isSecretKey($settings->publicKey)) {
            $this->components->error('MIRAFIVE_PUBLIC_KEY holds a secret key. Use the public key (mira_pk_…); @mirafiveScript refuses to print a secret key.');

            return self::FAILURE;
        }

        if ($settings->secretKey === '') {
            $this->components->error('No secret key: set MIRAFIVE_SECRET_KEY to the project\'s secret key (mira_sk_…).');

            return self::FAILURE;
        }

        if (str_starts_with($settings->secretKey, Mira::PUBLIC_KEY_PREFIX)) {
            $this->components->error('MIRAFIVE_SECRET_KEY holds a public key (mira_pk_…). Server code needs the secret key (mira_sk_…).');

            return self::FAILURE;
        }

        try {
            $mira->checkConnection();
        } catch (MiraError $error) {
            $this->components->error($error->getMessage());

            return self::FAILURE;
        }

        $this->components->info('The secret key and host work. Nothing was stored.');

        return self::SUCCESS;
    }
}
