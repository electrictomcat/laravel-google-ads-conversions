<?php

namespace ElectricTomCat\GoogleAdsConversions\Http\Middleware;

use Closure;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captures the GCLID, GBRAID, WBRAID, and configured UTM parameters off
 * the landing request, then buffers a record of the visitor in cache so
 * the syncToDatabase() pass can persist it.
 *
 * Register on the web group in your bootstrap/app.php.
 */
class CaptureGclid
{
    public function __construct(protected GoogleAdsConversions $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        $cookieConfig = (array) config('google-ads-conversions.cookies');

        $gclidCookie = $cookieConfig['gclid'] ?? 'google_ads_gclid';
        $visitorCookie = $cookieConfig['visitor_id'] ?? 'google_ads_visitor_id';
        $sessionKey = config('google-ads-conversions.session_key', 'google_ads_gclid');

        $gclid = $request->query('gclid')
            ?? $request->query('gbraid')
            ?? $request->query('wbraid');

        $visitorId = $request->cookie($visitorCookie);

        if (! $visitorId) {
            $visitorId = (string) Str::uuid();
            Cookie::queue($this->makeCookie($visitorCookie, $visitorId, $cookieConfig));
        }

        if ($gclid) {
            Session::put($sessionKey, $gclid);
            Cookie::queue($this->makeCookie($gclidCookie, $gclid, $cookieConfig));

            $this->tracker->bufferLeadData($gclid, $this->trackingData($request, $visitorId));
        }

        return $next($request);
    }

    /**
     * @param  array<string, mixed>  $cookieConfig
     */
    protected function makeCookie(string $name, string $value, array $cookieConfig): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::make(
            $name,
            $value,
            (int) ($cookieConfig['lifetime_minutes'] ?? 60 * 24 * 30),
            '/',
            $cookieConfig['domain'] ?? null,
            (bool) ($cookieConfig['secure'] ?? true),
            false, // httpOnly: false so JS analytics can read if needed
            false, // raw
            $cookieConfig['same_site'] ?? 'Lax',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function trackingData(Request $request, string $visitorId): array
    {
        $data = [
            'visitor_id' => $visitorId,
            'landing_page' => $request->getPathInfo(),
            'source' => $request->query('utm_source', 'google_ads'),
        ];

        foreach ((array) config('google-ads-conversions.tracked_query_parameters', []) as $param) {
            $data[$param] = $request->query($param);
        }

        return $data;
    }
}
