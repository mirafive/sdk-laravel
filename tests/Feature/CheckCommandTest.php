<?php

declare(strict_types=1);

it('passes when the ingest takes the key', function (): void {
    $this->transport->answerNext(answer(200, ['ok' => true, 'key' => 'secret']));

    $this->artisan('mirafive:check')
        ->expectsOutputToContain('mira_sk_live…')
        ->expectsOutputToContain('The secret key and host work')
        ->assertSuccessful();

    expect($this->transport->requests()[0]['method'])->toBe('GET')
        ->and($this->transport->requests()[0]['url'])->toEndWith('/v1/check');
});

it('fails for a key the ingest does not know', function (): void {
    $this->transport->answerNext(answer(401, ['error' => 'unknown_key']));

    $this->artisan('mirafive:check')->expectsOutputToContain('unknown_key')->assertFailed();
});

it('fails without a secret key, with a public key in its place, or a secret key as the public key', function (array $config, string $message): void {
    foreach ($config as $key => $value) {
        config()->set($key, $value);
    }

    $this->artisan('mirafive:check')->expectsOutputToContain($message)->assertFailed();

    expect($this->transport->requests())->toBe([]);
})->with([
    'no key' => [['mirafive.secret_key' => null], 'No secret key'],
    'public key' => [['mirafive.secret_key' => 'mira_pk_live_1'], 'holds a public key'],
    'secret as public' => [['mirafive.public_key' => 'mira_sk_live_1'], 'MIRAFIVE_PUBLIC_KEY holds a secret key'],
    'switched off' => [['mirafive.enabled' => false], 'switched off'],
]);
