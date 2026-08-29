<?php

it('runs ad-conversions:install interactive wizard', function () {
    $this->artisan('ad-conversions:install')
        ->expectsQuestion('Which advertising channels do you want to configure?', ['google'])
        ->expectsOutputToContain('OmniTrack Conversion Setup Wizard')
        ->expectsOutputToContain('Installation and setup completed!')
        ->assertSuccessful();
});
