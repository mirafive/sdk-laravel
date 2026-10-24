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
