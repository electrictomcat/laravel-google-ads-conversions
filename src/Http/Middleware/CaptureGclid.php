<?php

namespace ElectricTomCat\GoogleAdsConversions\Http\Middleware;

use Closure;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Support\ConsentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captures the GCLID, GBRAID, WBRAID, and configured UTM parameters off
 * the landing request, respecting GDPR/ePrivacy cookie consent gates, then
 * buffers a record of the visitor in cache so the syncToDatabase() pass can persist it.
 *
 * Register on the web group in your bootstrap/app.php.
 */
class CaptureGclid
{
    /**
     * Upper bound for any captured identifier or tracking parameter.
     *
     * Real click IDs are well under 200 characters; the default schema stores
     * them in a 255-character column.
     */
    public const MAX_CLICK_ID_LENGTH = 255;

    public function __construct(
        protected GoogleAdsConversions $tracker,
        protected ConsentManager $consentManager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $cookieConfig = (array) config('google-ads-conversions.cookies');
        $sessionKeys = (array) config('google-ads-conversions.session_keys', [
            'gclid' => 'google_ads_gclid',
            'gbraid' => 'google_ads_gbraid',
            'wbraid' => 'google_ads_wbraid',
        ]);

        $gclid = $this->scalarQuery($request, 'gclid');
        $gbraid = $this->scalarQuery($request, 'gbraid');
        $wbraid = $this->scalarQuery($request, 'wbraid');

        $primaryClickId = $gclid ?? $gbraid ?? $wbraid;

        $hasCookieConsent = $this->consentManager->hasCookieConsent($request);

        $visitorCookieName = $cookieConfig['visitor_id'] ?? 'google_ads_visitor_id';
        $visitorId = $request->cookie($visitorCookieName);

        if (! $visitorId) {
            $visitorId = (string) Str::uuid();
            if ($hasCookieConsent) {
                Cookie::queue($this->makeCookie($visitorCookieName, $visitorId, $cookieConfig));
            }
        }

        if ($primaryClickId) {
            // Ephemeral session storage for current request / visitor journey
            if ($gclid) {
                Session::put($sessionKeys['gclid'] ?? 'google_ads_gclid', $gclid);
                if ($hasCookieConsent) {
                    Cookie::queue($this->makeCookie($cookieConfig['gclid'] ?? 'google_ads_gclid', $gclid, $cookieConfig));
                }
            }

            if ($gbraid) {
                Session::put($sessionKeys['gbraid'] ?? 'google_ads_gbraid', $gbraid);
                if ($hasCookieConsent) {
                    Cookie::queue($this->makeCookie($cookieConfig['gbraid'] ?? 'google_ads_gbraid', $gbraid, $cookieConfig));
                }
            }

            if ($wbraid) {
                Session::put($sessionKeys['wbraid'] ?? 'google_ads_wbraid', $wbraid);
                if ($hasCookieConsent) {
                    Cookie::queue($this->makeCookie($cookieConfig['wbraid'] ?? 'google_ads_wbraid', $wbraid, $cookieConfig));
                }
            }

            $trackingData = $this->trackingData($request, $visitorId, $gclid, $gbraid, $wbraid);
            $this->tracker->bufferLeadData($primaryClickId, $trackingData);
        }

        return $next($request);
    }

    /**
     * Read a query parameter that must be a short, single-value string.
     *
     * Query strings can carry arrays (`?gclid[]=x`) and arbitrarily long values.
     * Both would otherwise reach string-typed parameters and column-width limits
     * further down the pipeline, so anything that isn't a plausible click
     * identifier is discarded here rather than blowing up later.
     */
    protected function scalarQuery(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || mb_strlen($value) > self::MAX_CLICK_ID_LENGTH) {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $cookieConfig
     */
    protected function makeCookie(string $name, string $value, array $cookieConfig): \Symfony\Component\HttpFoundation\Cookie
    {
        $secure = $cookieConfig['secure'] ?? config('session.secure', null);

        return Cookie::make(
            $name,
            $value,
            (int) ($cookieConfig['lifetime_minutes'] ?? 60 * 24 * 30),
            '/',
            $cookieConfig['domain'] ?? null,
            $secure,
            (bool) ($cookieConfig['http_only'] ?? false),
            false, // raw
            $cookieConfig['same_site'] ?? 'Lax',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function trackingData(
        Request $request,
        string $visitorId,
        ?string $gclid = null,
        ?string $gbraid = null,
        ?string $wbraid = null,
    ): array {
        $data = [
            'visitor_id' => $visitorId,
            'landing_page' => $request->getPathInfo(),
            'source' => $this->scalarQuery($request, 'utm_source') ?? 'google_ads',
        ];

        if ($gclid) {
            $data['gclid'] = $gclid;
        }

        if ($gbraid) {
            $data['gbraid'] = $gbraid;
        }

        if ($wbraid) {
            $data['wbraid'] = $wbraid;
        }

        foreach ((array) config('google-ads-conversions.tracked_query_parameters', []) as $param) {
            $data[$param] = $this->scalarQuery($request, (string) $param);
        }

        return $data;
    }
}
