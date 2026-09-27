<?php

declare(strict_types=1);

namespace MiraFive\Laravel\Console;

use Illuminate\Console\Command;
use MiraFive\Laravel\Client;
use MiraFive\Laravel\Settings;
use MiraFive\MiraError;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'mirafive:check')]
final class CheckCommand extends Command
{
    protected $signature = 'mirafive:check';

    protected $description = 'Send a $install_check event to prove the MIRA FIVE secret key and host work';

    public function handle(Settings $settings, Client $client): int
    {
        if (! $settings->enabled) {
            $this->components->error('MIRA FIVE is disabled (mirafive.enabled is false).');

            return self::FAILURE;
        }

        if ($settings->secretKey === '') {
            $this->components->error('No secret key: set MIRAFIVE_SECRET_KEY to the secret key of a server source.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Host', $settings->host);
        $this->components->twoColumnDetail('Key', $settings->keyNamespace().'_…');
        $this->components->twoColumnDetail('Mode', $settings->mode->value);

        try {
            $receipt = $client->send([['name' => '$install_check']]);
        } catch (MiraError $error) {
            $this->components->error("{$error->errorCode}: {$error->getMessage()}");

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Batch', $receipt->batch);
        $this->components->twoColumnDetail('Accepted', (string) $receipt->accepted);
        $this->components->twoColumnDetail('Dropped', $receipt->dropped.($receipt->reason === null ? '' : " ({$receipt->reason})"));

        if ($receipt->reason !== 'install_check') {
            $this->components->warn('MIRA FIVE answered, but not with reason "install_check". Is the host a MIRA FIVE collector?');

            return self::FAILURE;
        }

        $this->components->info('The key and host work. Install checks are never stored or billed.');

        return self::SUCCESS;
    }
}
