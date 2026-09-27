<?php

declare(strict_types=1);

it('sends an install check and prints the receipt', function (): void {
    $this->transport->queue(answer(202, ['batch' => '0192d4a8-7b1c-4e8a-9c1d-2b3e4f5a6b7c', 'accepted' => 0, 'dropped' => 1, 'reason' => 'install_check']));

    $this->artisan('mirafive:check')
        ->expectsOutputToContain('mf_ab12cd34_…')
        ->expectsOutputToContain('0192d4a8-7b1c-4e8a-9c1d-2b3e4f5a6b7c')
        ->expectsOutputToContain('install_check')
        ->doesntExpectOutputToContain('secretTail')
        ->assertSuccessful();

    expect($this->transport->batch(0)['events'])->toHaveCount(1)
        ->and($this->transport->batch(0)['events'][0]['name'])->toBe('$install_check');
});

it('fails with the protocol code when the key is refused', function (): void {
    $this->transport->queue(answer(401, ['code' => 'unauthorized', 'detail' => 'unknown key']));

    $this->artisan('mirafive:check')->expectsOutputToContain('unauthorized')->assertFailed();
});

it('fails when the host is not a MIRA FIVE collector', function (): void {
    $this->artisan('mirafive:check')->expectsOutputToContain('not with reason')->assertFailed();
});

it('fails without a secret key', function (): void {
    config()->set('mirafive.secret_key', '');

    $this->artisan('mirafive:check')->expectsOutputToContain('MIRAFIVE_SECRET_KEY')->assertFailed();

    expect($this->transport->requests)->toBeEmpty();
});

it('fails when disabled', function (): void {
    config()->set('mirafive.enabled', false);

    $this->artisan('mirafive:check')->expectsOutputToContain('disabled')->assertFailed();
});

it('sends directly in queue mode', function (): void {
    config()->set('mirafive.queue', 'analytics');
    $this->transport->queue(answer(202, ['batch' => 'b', 'accepted' => 0, 'dropped' => 1, 'reason' => 'install_check']));

    $this->artisan('mirafive:check')->assertSuccessful();
});
