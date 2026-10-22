<?php

namespace ElectricTomCat\GoogleAdsConversions\Support;

use Illuminate\Support\Facades\Http;

class GoogleAdsSetup
{
    public const OAUTH_AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    public const OAUTH_TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    public const ACCESSIBLE_CUSTOMERS_ENDPOINT = 'https://googleads.googleapis.com/v23/customers:listAccessibleCustomers';

    public const ADWORDS_SCOPE = 'https://www.googleapis.com/auth/adwords';

    public const DEFAULT_REDIRECT_URI = 'http://127.0.0.1:8085';

    public const ENV_SECTION_HEADER = '# Google Ads Offline Conversions';

    /**
     * Build the Google OAuth 2.0 authorization URL for offline Google Ads access.
     */
    public function buildAuthorizationUrl(string $clientId, string $redirectUri = self::DEFAULT_REDIRECT_URI): string
    {
        $query = http_build_query([
            'client_id' => trim($clientId),
            'redirect_uri' => trim($redirectUri),
            'response_type' => 'code',
            'scope' => self::ADWORDS_SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ], '', '&', PHP_QUERY_RFC3986);

        return self::OAUTH_AUTH_ENDPOINT.'?'.$query;
    }

    /**
     * Extract an authorization code from either a raw code string, URL-encoded code,
     * or a full redirect URL copied from the browser address bar.
     */
    public function extractAuthorizationCode(string $input): string
    {
        $trimmed = trim($input);

        if ($trimmed === '') {
            return '';
        }

        if (str_contains($trimmed, 'code=')) {
            $queryString = parse_url($trimmed, PHP_URL_QUERY);

            if (! is_string($queryString) || $queryString === '') {
                $queryString = ltrim($trimmed, '?');
            }

            $params = [];
            parse_str($queryString, $params);

            if (isset($params['code']) && is_string($params['code']) && $params['code'] !== '') {
                return trim($params['code']);
            }
        }

        return rawurldecode($trimmed);
    }

    /**
     * Exchange an OAuth 2.0 authorization code for a refresh token and access token.
     *
     * @return array{success: bool, refresh_token: ?string, access_token: ?string, error: ?string}
     */
    public function exchangeCodeForRefreshToken(
        string $clientId,
        string $clientSecret,
        string $codeInput,
        string $redirectUri = self::DEFAULT_REDIRECT_URI,
    ): array {
        $code = $this->extractAuthorizationCode($codeInput);

        if ($code === '') {
            return [
                'success' => false,
                'refresh_token' => null,
                'access_token' => null,
                'error' => 'Authorization code is empty.',
            ];
        }

        try {
            $response = Http::asForm()->timeout(15)->post(self::OAUTH_TOKEN_ENDPOINT, [
                'code' => $code,
                'client_id' => trim($clientId),
                'client_secret' => trim($clientSecret),
                'redirect_uri' => trim($redirectUri),
                'grant_type' => 'authorization_code',
            ]);

            $data = $response->json();
            $payload = is_array($data) ? $data : [];

            if (! $response->successful()) {
                $errorDesc = $payload['error_description'] ?? $payload['error'] ?? ('HTTP '.$response->status());

                return [
                    'success' => false,
                    'refresh_token' => null,
                    'access_token' => null,
                    'error' => (string) $errorDesc,
                ];
            }

            $refreshToken = $payload['refresh_token'] ?? null;
            $accessToken = $payload['access_token'] ?? null;

            if (! is_string($refreshToken) || $refreshToken === '') {
                return [
                    'success' => false,
                    'refresh_token' => null,
                    'access_token' => is_string($accessToken) ? $accessToken : null,
                    'error' => 'Google did not return a refresh_token. Ensure prompt=consent and access_type=offline were used.',
                ];
            }

            return [
                'success' => true,
                'refresh_token' => $refreshToken,
                'access_token' => is_string($accessToken) ? $accessToken : null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'refresh_token' => null,
                'access_token' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch accessible Google Ads customer IDs using an OAuth access token and developer token.
     *
     * @return array<int, string>
     */
    public function listAccessibleCustomers(string $accessToken, string $developerToken): array
    {
        if (trim($accessToken) === '' || trim($developerToken) === '') {
            return [];
        }

        try {
            $response = Http::withToken(trim($accessToken))
                ->withHeaders([
                    'developer-token' => trim($developerToken),
                ])
                ->timeout(10)
                ->get(self::ACCESSIBLE_CUSTOMERS_ENDPOINT);

            if (! $response->successful()) {
                return [];
            }

            $resourceNames = $response->json('resourceNames');
            if (! is_array($resourceNames)) {
                return [];
            }

            $ids = [];
            foreach ($resourceNames as $resourceName) {
                if (is_string($resourceName) && preg_match('/^customers\/(\d+)$/', $resourceName, $matches) === 1) {
                    $ids[] = $matches[1];
                }
            }

            return $ids;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Start a temporary one-shot HTTP server on the loopback redirect URI to capture the OAuth code.
     */
    public function listenForAuthorizationCode(
        string $redirectUri = self::DEFAULT_REDIRECT_URI,
        int $timeoutSeconds = 120,
    ): ?string {
        $host = parse_url($redirectUri, PHP_URL_HOST) ?: '127.0.0.1';
        $port = (int) (parse_url($redirectUri, PHP_URL_PORT) ?: 8085);

        $server = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
        if ($server === false) {
            return null;
        }

        try {
            $client = @stream_socket_accept($server, $timeoutSeconds);
            if ($client === false) {
                return null;
            }

            $requestLine = (string) fgets($client, 4096);
            $code = '';

            if (preg_match('/GET\s+([^\s]+)\s+HTTP/i', $requestLine, $matches) === 1) {
                $code = $this->extractAuthorizationCode($matches[1]);
            }

            $body = $code !== ''
                ? '<html><body style="font-family:sans-serif;text-align:center;padding:3rem;">'
                    .'<h2 style="color:#16a34a;">&#10003; Google Ads Authentication Complete</h2>'
                    .'<p>You can close this browser tab and return to your terminal.</p>'
                    .'</body></html>'
                : '<html><body style="font-family:sans-serif;text-align:center;padding:3rem;">'
                    .'<h2 style="color:#dc2626;">Authentication Code Missing</h2>'
                    .'<p>Please return to your terminal and paste the authorization code manually.</p>'
                    .'</body></html>';

            $response = "HTTP/1.1 200 OK\r\n"
                ."Content-Type: text/html; charset=UTF-8\r\n"
                .'Content-Length: '.strlen($body)."\r\n"
                ."Connection: close\r\n\r\n"
                .$body;

            @fwrite($client, $response);
            @fclose($client);

            return $code !== '' ? $code : null;
        } finally {
            @fclose($server);
        }
    }

    /**
     * Inject or update the Google Ads environment variables in a dedicated section of the .env file.
     *
     * @param  array<string, string|null>  $variables
     */
    public function writeEnvSection(array $variables, ?string $envPath = null): bool
    {
        $path = $envPath ?: app()->environmentFilePath();
        $content = is_file($path) ? (string) file_get_contents($path) : '';

        $remaining = [];

        foreach ($variables as $key => $value) {
            $formattedLine = $this->formatEnvLine($key, (string) ($value ?? ''));
            $pattern = '/^(?:#\s*)?'.preg_quote($key, '/').'\s*=.*$/m';

            if (preg_match($pattern, $content) === 1) {
                $content = (string) preg_replace($pattern, addcslashes($formattedLine, '\\$'), $content, 1);
            } else {
                $remaining[$key] = $formattedLine;
            }
        }

        if (! empty($remaining)) {
            $newLines = implode("\n", array_values($remaining));

            // If a GOOGLE_ADS_ variable or the section header already exists, append directly after the last GOOGLE_ADS_ line
            if (preg_match_all('/^GOOGLE_ADS_[A-Z0-9_]+\s*=.*$/m', $content, $matches, PREG_OFFSET_CAPTURE) && ! empty($matches[0])) {
                $lastMatch = end($matches[0]);
                $lastLine = $lastMatch[0];
                $lastPos = $lastMatch[1];
                $insertPos = $lastPos + strlen($lastLine);

                $content = substr($content, 0, $insertPos)."\n".$newLines.substr($content, $insertPos);
            } else {
                $trimmedContent = rtrim($content);
                $prefix = $trimmedContent === '' ? '' : $trimmedContent."\n\n";

                $sectionBlock = self::ENV_SECTION_HEADER."\n".$newLines."\n";
                $content = $prefix.$sectionBlock;
            }
        }

        if ($content !== '' && ! str_ends_with($content, "\n")) {
            $content .= "\n";
        }

        return file_put_contents($path, $content) !== false;
    }

    protected function formatEnvLine(string $key, string $value): string
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

        return "{$key}=\"{$escaped}\"";
    }
}
