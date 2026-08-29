<?php

use ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions;
use Illuminate\Support\Facades\Blade;

it('renders blade directives for hidden click inputs', function () {
    GoogleAdsConversions::fake('gclid-blade-test');

    $rendered = Blade::render('@googleAdsGclid');

    expect($rendered)->toContain('<input type="hidden" name="gclid" value="gclid-blade-test">');

    $renderedAll = Blade::render('@googleAdsClickInputs');
    expect($renderedAll)->toContain('<input type="hidden" name="gclid" value="gclid-blade-test">');
});
