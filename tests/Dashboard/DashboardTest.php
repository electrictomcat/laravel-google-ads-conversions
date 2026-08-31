<?php

use ElectricTomCat\GoogleAdsConversions\Models\Lead;
it('renders the embedded analytics dashboard when it is explicitly enabled', function () {
    Lead::create([
        'gclid' => 'gclid-dashboard-test',
        'conversions' => [[
            'event' => 'Quote Form',
            'value' => 200.0,
            'currency' => 'USD',
            'status' => 'uploaded',
            'timestamp' => now()->timestamp,
        ]],
    ]);

    $response = $this->get('/ad-conversions');

    $response->assertOk();
    $response->assertSee('OmniSignal');
    $response->assertSee('Quote Form');
    $response->assertSee('200.00');
});
