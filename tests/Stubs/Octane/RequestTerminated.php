<?php

declare(strict_types=1);

namespace Laravel\Octane\Events;

use Illuminate\Contracts\Container\Container;

/** The shape of Octane's event, for tests without Octane installed. */
final class RequestTerminated
{
    public function __construct(public Container $app, public Container $sandbox) {}
}
