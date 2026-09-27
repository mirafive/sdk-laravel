<?php

declare(strict_types=1);

namespace MiraFive\Laravel\Tests;

use MiraFive\Http\Transport;
use MiraFive\Laravel\Facades\Mira;
use MiraFive\Laravel\MiraFiveServiceProvider;
use MiraFive\Laravel\Tests\Support\FakeTransport;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const string SECRET_KEY = 'mf_ab12cd34_secretTail';

    protected FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = new FakeTransport;
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
        $app['config']->set('mirafive.host', 'https://collector.test');
        $app['config']->set('cache.default', 'array');
    }
}
