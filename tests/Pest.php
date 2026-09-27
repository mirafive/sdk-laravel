<?php

declare(strict_types=1);

use MiraFive\Http\Response;
use MiraFive\Laravel\Tests\TestCase;

uses(TestCase::class)->in('Feature');

/**
 * @param  array<array-key, mixed>|null  $body
 * @param  array<string, string>  $headers
 */
function answer(int $status, ?array $body = null, array $headers = []): Response
{
    return new Response($status, $headers, $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR));
}

function flagDocument(): Response
{
    $everyone = fn (string $variant): array => [['x' => $variant]];

    return new Response(200, ['ETag' => 'W/"f-1"'], json_encode(['at' => (int) floor(microtime(true) * 1000), 'v' => 1, 'flags' => [
        'new-checkout' => ['s' => 'k3v9x0q2m7ta', 't' => 'b', 'u' => 'p', 'd' => 'off', 'r' => $everyone('on'), 'w' => 1],
        'banner' => ['s' => 'a1s2d3f4g5h6', 't' => 'c', 'u' => 'p', 'd' => 'shown', 'r' => $everyone('shown'), 'p' => ['shown' => '</script><script>alert(1)</script>'], 'w' => 1],
        'server-only' => ['s' => 'p0o9i8u7y6t5', 't' => 'c', 'u' => 'p', 'd' => 'on', 'r' => $everyone('on'), 'p' => ['on' => 'internal-token']],
    ]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
}
