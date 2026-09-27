<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use MiraFive\Laravel\Facades\Mira;

beforeEach(function (): void {
    $this->transport->queue(flagDocument());
    Route::get('/bootstrap', fn () => Mira::forUser(new GenericUser(['id' => 42]))->flags()->bootstrap());
});

it('reads flags as the user when the request does not opt out', function (): void {
    expect($this->get('/bootstrap')->getContent())->toContain('"unit"');
});

it('reads Sec-GPC and DNT from the current request as an opt-out', function (string $header): void {
    expect($this->get('/bootstrap', [$header => '1'])->getContent())->not->toContain('"unit"');
})->with(['Sec-GPC', 'DNT']);

it('lets an explicit optedOut win over the request', function (): void {
    Route::get('/explicit', fn () => Mira::forUser(new GenericUser(['id' => 42]))->flags(optedOut: false)->bootstrap());

    expect($this->get('/explicit', ['Sec-GPC' => '1'])->getContent())->toContain('"unit"');
});

it('is not opted out outside a request', function (): void {
    expect(Mira::forUser(new GenericUser(['id' => 42]))->flags()->bootstrap())->toContain('"unit"');
});
