<?php

declare(strict_types=1);

namespace Laravel\Octane\Events;

use Illuminate\Contracts\Foundation\Application;

/** The shape of Octane's event, for tests without laravel/octane installed. */
final class RequestTerminated
{
    public function __construct(public Application $app, public Application $sandbox) {}
}
