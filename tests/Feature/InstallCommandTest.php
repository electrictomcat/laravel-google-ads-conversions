<?php

it('runs ad-conversions:install interactive wizard', function () {
    $this->artisan('ad-conversions:install')
        ->expectsQuestion('Which advertising channels do you want to configure?', ['google'])
        ->expectsOutputToContain('OmniSignal Setup Wizard')
        ->expectsOutputToContain('OmniSignal installation and setup completed!')
        ->assertSuccessful();
});
