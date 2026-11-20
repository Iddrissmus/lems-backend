<?php

namespace App\Support\Http;

use Illuminate\Http\Middleware\TrustProxies;

/**
 * Lets the API believe the forwarded headers of a reverse proxy or tunnel
 * (ngrok, Cloudflare Tunnel, Nginx): the real client IP (rate limits and the
 * audit log depend on it) and https. Without it, every caller looks like the
 * proxy and generated links say http.
 *
 * Off by default, because trusting every sender lets anyone fake their IP.
 * TRUSTED_PROXIES is "" (off), "*" (any; fine for a tunnel demo) or a comma
 * separated list of proxy IP addresses or ranges (production).
 */
class ProxyTrust
{
    public static function configure(string $setting): void
    {
        $setting = trim($setting);

        if ($setting === '') {
            return;
        }

        TrustProxies::at($setting === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $setting)))));
    }
}
