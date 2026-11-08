<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use MiraFive\Laravel\Facades\Mira;

it('records what the app sent instead of sending it', function (): void {
    $fake = Mira::fake();

    Route::post('/signup', function () {
        Mira::identify('u_1', traits: ['plan' => 'pro'], externalIds: ['stripe_customer' => 'cus_1']);
        Mira::track('signup', userId: 'u_1');
        Mira::touchpoint('survey', userId: 'u_1', source: 'podcast');

        return 'ok';
    });

    $this->post('/signup')->assertOk();

    $fake->assertIdentified('u_1', fn (array $event): bool => $event['external_ids'] === ['stripe_customer' => 'cus_1']);
    $fake->assertTracked('signup', 1);
    $fake->assertTouchpoint(fn (array $event): bool => $event['properties']['source'] === 'podcast');
    $fake->assertNotTracked('login');
    $fake->assertNoErrors();

    expect($this->transport->requests())->toBe([]);
});

it('fails a test on input the ingest would refuse', function (): void {
    $fake = Mira::fake();

    Mira::revenue(19.99, 'EUR');

    $fake->assertNothingTracked();
    expect($fake->errors()[0]->getMessage())->toBe('revenue: amount must be an integer in minor units (1999 for 19.99)');
});
