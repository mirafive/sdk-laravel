<?php

declare(strict_types=1);

namespace MiraFive\Laravel;

use Closure;
use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use MiraFive\Flags\MiraFlags;
use MiraFive\Http\CurlTransport;
use MiraFive\Http\StreamTransport;
use MiraFive\Http\Transport;
use MiraFive\Laravel\Console\CheckCommand;
use MiraFive\Laravel\Http\Middleware\SendBootstrapHeaders;
use MiraFive\Laravel\Queue\SendBatch;
use MiraFive\Mira;
use Psr\Log\LoggerInterface;

final class MiraFiveServiceProvider extends ServiceProvider
{
    /** Octane runs these after each request, task and tick; listening to a class that is not installed is harmless. */
    private const array OCTANE_EVENTS = [
        'Laravel\Octane\Events\RequestTerminated',
        'Laravel\Octane\Events\TaskTerminated',
        'Laravel\Octane\Events\TickTerminated',
    ];

    private const array QUEUE_EVENTS = [
        'Illuminate\Queue\Events\JobProcessed',
        'Illuminate\Queue\Events\JobExceptionOccurred',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mirafive.php', 'mirafive');

        $this->app->singleton(Settings::class, function (Application $app): Settings {
            $config = $app->make(Config::class)->get('mirafive');

            return Settings::fromConfig(is_array($config) ? $config : []);
        });

        $this->app->singletonIf(Transport::class, fn (): Transport => extension_loaded('curl') ? new CurlTransport : new StreamTransport);

        $this->app->singleton(Mira::class, function (Application $app): Mira {
            $settings = $app->make(Settings::class);
            $sends = $settings->sends();

            return new Mira(
                key: $settings->secretKey,
                host: $settings->host,
                mode: $settings->mode,
                transport: $app->make(Transport::class),
                logger: $app->make(LoggerInterface::class),
                cache: $sends ? $app->make(CacheFactory::class)->store($settings->cacheStore) : null,
                enabled: $sends,
                flushOnShutdown: false,
                flagsRefreshSeconds: $settings->refreshSeconds,
                handOff: $sends && $settings->queued() ? self::handOff($settings) : null,
            );
        });

        $this->app->singleton(MiraFlags::class, fn (Application $app): MiraFlags => $app->make(Mira::class)->flags());

        $this->app->singleton(Client::class, fn (Application $app): Client => new Client(
            $app->make(Mira::class),
            fn (): MiraFlags => $app->make(MiraFlags::class),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/mirafive.php' => $this->app->configPath('mirafive.php')], 'mirafive-config');
            $this->commands([CheckCommand::class]);
        }

        $this->callAfterResolving('blade.compiler', function (BladeCompiler $blade): void {
            $tags = '\Illuminate\Container\Container::getInstance()->make(\MiraFive\Laravel\View\Tags::class)';
            $blade->directive('mirafiveScript', fn (string $expression): string => "<?php echo {$tags}->script({$expression}); ?>");
            $blade->directive('mirafiveFlags', fn (string $expression): string => "<?php echo {$tags}->flags({$expression}); ?>");
        });

        $this->callAfterResolving(HttpKernelContract::class, function (HttpKernelContract $kernel): void {
            if ($kernel instanceof HttpKernel) {
                $kernel->pushMiddleware(SendBootstrapHeaders::class);
            }
        });

        $this->app->terminating(static function (Container $app): void {
            self::flush($app);
        });

        $events = $this->app->make(Events::class);

        foreach (self::OCTANE_EVENTS as $event) {
            $events->listen($event, static function (object $event): void {
                $sandbox = property_exists($event, 'sandbox') ? $event->sandbox : null;

                if ($sandbox instanceof Container) {
                    self::flush($sandbox);
                }
            });
        }

        foreach (self::QUEUE_EVENTS as $event) {
            $events->listen($event, static function (): void {
                self::flush(IlluminateContainer::getInstance());
            });
        }

        $this->warmUnderOctane();
    }

    /** Sends what the request buffered. The core's own shutdown flush is off: this is the only one. */
    public static function flush(Container $app): void
    {
        if ($app->resolved(Mira::class)) {
            $app->make(Mira::class)->flush();
        }
    }

    /**
     * @return Closure(string, string): void
     */
    private static function handOff(Settings $settings): Closure
    {
        return static function (string $body) use ($settings): void {
            // Resolved per batch: under Octane the current container is the request's sandbox, and Bus::fake() swaps it.
            IlluminateContainer::getInstance()->make(Bus::class)->dispatch(new SendBatch($body, $settings->queueConnection, $settings->queue));
        };
    }

    /**
     * Octane otherwise builds these per request: a new cURL handle and an empty flag document each time. They hold no
     * request state; the buffer is flushed after every request.
     */
    private function warmUnderOctane(): void
    {
        $config = $this->app->make(Config::class);
        $warm = $config->get('octane.warm');

        if (is_array($warm)) {
            $config->set('octane.warm', array_values(array_unique([...array_filter($warm, is_string(...)), Transport::class, Mira::class, MiraFlags::class, Client::class])));
        }
    }
}
