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
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use MiraFive\Http\CurlTransport;
use MiraFive\Http\StreamTransport;
use MiraFive\Http\Transport;
use MiraFive\Laravel\Console\CheckCommand;
use MiraFive\Laravel\Queue\SendBatch;
use MiraFive\Laravel\View\ScriptTag;
use MiraFive\Mira;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Throwable;

final class MiraFiveServiceProvider extends ServiceProvider
{
    /** Octane runs these after each request, task and tick; listening to a class that is not installed is harmless. */
    private const array OCTANE_EVENTS = [
        'Laravel\Octane\Events\RequestTerminated',
        'Laravel\Octane\Events\TaskTerminated',
        'Laravel\Octane\Events\TickTerminated',
    ];

    /** Events tracked inside a job leave when the job is done, not when the worker stops. */
    private const array QUEUE_EVENTS = [
        'Illuminate\Queue\Events\JobProcessed',
        'Illuminate\Queue\Events\JobExceptionOccurred',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mirafive.php', 'mirafive');

        $this->app->singleton(Settings::class, static function (Application $app): Settings {
            $config = $app->make(Config::class)->get('mirafive');

            return Settings::fromConfig(is_array($config) ? $config : []);
        });

        $this->app->singletonIf(Transport::class, static fn (): Transport => extension_loaded('curl') ? new CurlTransport : new StreamTransport);

        $this->app->singleton(Mira::class, static function (Application $app): Mira {
            $settings = $app->make(Settings::class);
            $queued = $settings->queued();

            return new Mira(
                secretKey: $settings->secretKey,
                host: $settings->host,
                timeoutMs: $settings->timeoutMs,
                flushDeadlineMs: $settings->flushDeadlineMs,
                maxRetries: $settings->maxRetries,
                logger: $app->make(LoggerInterface::class),
                cache: $settings->sends() ? self::cache($app) : null,
                transport: $app->make(Transport::class),
                // Without a key nothing is sent and nothing is logged; `php artisan mirafive:check` tells why.
                enabled: $settings->enabled && ($settings->secretKey !== '' || $queued),
                // Laravel flushes after the response (terminating), never in a shutdown function.
                flushOnShutdown: false,
                handOff: $queued ? self::handOff($settings) : null,
            );
        });

        $this->app->singleton(ScriptTag::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/mirafive.php' => $this->app->configPath('mirafive.php')], 'mirafive-config');
            $this->commands([CheckCommand::class]);
        }

        $this->callAfterResolving('blade.compiler', static function (BladeCompiler $blade): void {
            $tag = '\Illuminate\Container\Container::getInstance()->make(\MiraFive\Laravel\View\ScriptTag::class)';
            $blade->directive('mirafiveScript', static fn (string $expression): string => "<?php echo {$tag}->render({$expression}); ?>");
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

    /** Sends what the request buffered, if the client was used at all. */
    public static function flush(Container $app): void
    {
        if ($app->resolved(Mira::class)) {
            $app->make(Mira::class)->flush();
        }
    }

    private static function cache(Application $app): ?CacheInterface
    {
        try {
            return $app->make(CacheFactory::class)->store();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return Closure(string): void
     */
    private static function handOff(Settings $settings): Closure
    {
        return static function (string $payload) use ($settings): void {
            // Resolved per batch: under Octane the current container is the request's sandbox, and Bus::fake() swaps it.
            IlluminateContainer::getInstance()->make(Bus::class)->dispatch(new SendBatch($payload, $settings->queueConnection, $settings->queue));
        };
    }

    /**
     * Octane otherwise builds these per request: a new cURL handle each time. They hold no request
     * state; the buffer is flushed after every request.
     */
    private function warmUnderOctane(): void
    {
        $config = $this->app->make(Config::class);
        $warm = $config->get('octane.warm');

        if (is_array($warm)) {
            $config->set('octane.warm', array_values(array_unique([...array_filter($warm, is_string(...)), Transport::class, Mira::class])));
        }
    }
}
