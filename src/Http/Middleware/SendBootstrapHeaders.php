<?php

declare(strict_types=1);

namespace MiraFive\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use MiraFive\Flags\MiraFlags;
use MiraFive\Laravel\View\Tags;
use Symfony\Component\HttpFoundation\Response;

/** Global: a response whose view printed `@mirafiveFlags` must not land in a shared cache. */
final class SendBootstrapHeaders
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        if ($response instanceof Response && $request->attributes->get(Tags::BOOTSTRAPPED) === true) {
            $response->headers->add(MiraFlags::BOOTSTRAP_HEADERS);
        }

        return $response;
    }
}
