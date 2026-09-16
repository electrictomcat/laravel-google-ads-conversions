<?php

namespace ElectricTomCat\GoogleAdsConversions\Http\Controllers;

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TrackConversionController extends Controller
{
    public function __invoke(Request $request, GoogleAdsConversions $tracker): JsonResponse
    {
        $validated = $request->validate([
            'event' => 'required|string',
            'value' => 'nullable|numeric',
            'currency' => 'nullable|string|size:3',
            'gclid' => 'nullable|string|max:255',
            'gbraid' => 'nullable|string|max:255',
            'wbraid' => 'nullable|string|max:255',
            'order_id' => 'nullable|string|max:255',
        ]);

        $recorded = $tracker->record(
            eventName: $validated['event'],
            value: isset($validated['value']) ? (float) $validated['value'] : null,
            currency: $validated['currency'] ?? null,
            gclid: $validated['gclid'] ?? null,
            gbraid: $validated['gbraid'] ?? null,
            wbraid: $validated['wbraid'] ?? null,
            orderId: $validated['order_id'] ?? null,
        );

        return response()->json([
            'success' => $recorded,
        ]);
    }
}
