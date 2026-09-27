<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use MiraFive\Laravel\Client;
use MiraFive\Laravel\Facades\Mira;
use PHPUnit\Framework\ExpectationFailedException;

it('records calls instead of sending them', function (): void {
    $fake = Mira::fake();

    Mira::track('signup', userId: 'u_42', properties: ['plan' => 'pro']);
    Mira::track('signup', userId: 'u_43');
    Mira::identify('u_42', ['plan' => 'pro']);
    $receipt = Mira::send([['name' => 'order completed', 'properties' => ['revenue' => 99]]]);
    app()->terminate();

    $fake->assertTracked('signup');
    $fake->assertTracked('signup', 2);
    $fake->assertTracked('signup', fn (array $event): bool => $event['properties'] === ['plan' => 'pro']);
    $fake->assertTracked('order completed', fn (array $event): bool => $event['properties']['revenue'] === 99);
    $fake->assertNotTracked('refund');
    $fake->assertNotTracked('signup', fn (array $event): bool => $event['userId'] === 'u_99');
    $fake->assertIdentified('u_42');
    $fake->assertIdentified('u_42', fn (array $call): bool => $call['traits'] === ['plan' => 'pro']);

    expect($receipt->accepted)->toBe(1)
        ->and($this->transport->requests)->toBeEmpty();
});

it('fails when the expectation is not met', function (Closure $assertion): void {
    $fake = Mira::fake();
    Mira::track('signup');

    expect(fn () => $assertion($fake))->toThrow(ExpectationFailedException::class);
})->with([
    'tracked' => fn ($fake) => $fake->assertTracked('refund'),
    'count' => fn ($fake) => $fake->assertTracked('signup', 2),
    'not tracked' => fn ($fake) => $fake->assertNotTracked('signup'),
    'identified' => fn ($fake) => $fake->assertIdentified('u_42'),
    'nothing' => fn ($fake) => $fake->assertNothingTracked(),
]);

it('asserts that nothing was tracked', function (): void {
    Mira::fake()->assertNothingTracked();
});

it('is what the container hands out while faked', function (): void {
    $fake = Mira::fake();

    app(Client::class)->track('signup');

    $fake->assertTracked('signup');
});

it('tracks and identifies as the signed-in user', function (): void {
    $fake = Mira::fake();
    $user = Mira::forUser(new GenericUser(['id' => 42]));

    $user->track('signup', ['plan' => 'pro']);
    $user->identify(['plan' => 'pro']);

    $fake->assertTracked('signup', fn (array $event): bool => $event['userId'] === '42');
    $fake->assertIdentified('42');
    expect($user->flags()->enabled('new-checkout'))->toBeFalse();
});

it('still refuses what the collector would refuse', function (): void {
    Mira::fake();

    Mira::track('$made-up');
})->throws(InvalidArgumentException::class);

it('keeps the configured mode', function (): void {
    config()->set('mirafive.mode', 'consentless');
    Mira::fake();

    Mira::track('signup', userId: 'u_42');
})->throws(InvalidArgumentException::class, 'consentless');
