<?php

declare(strict_types=1);

namespace MiraFive\Laravel\Tests;

use MiraFive\Http\Transport;
use MiraFive\Laravel\Facades\Mira;
use MiraFive\Laravel\MiraFiveServiceProvider;
use MiraFive\Testing\RecordingTransport;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const string SECRET_KEY = 'mira_sk_live_secretTail';

    public const string PUBLIC_KEY = 'mira_pk_live_123';

    protected RecordingTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = new RecordingTransport;
        $this->app?->instance(Transport::class, $this->transport);
    }

    protected function getPackageProviders($app): array
    {
        return [MiraFiveServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Mira' => Mira::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('mirafive.secret_key', self::SECRET_KEY);
        $app['config']->set('mirafive.public_key', self::PUBLIC_KEY);
        $app['config']->set('mirafive.host', 'https://ingest.test');
        $app['config']->set('cache.default', 'array');
    }

    /**
     * Every event sent so far.
     *
     * @return list<array<string, mixed>>
     */
    protected function sent(): array
    {
        return array_merge(...array_map(static fn (array $batch): array => $batch['events'] ?? [], $this->transport->batches()));
    }
}
