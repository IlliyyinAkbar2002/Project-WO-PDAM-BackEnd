<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Middleware to force the session cookie SameSite attribute to 'None'.
 *
 * Notes:
 * - Browsers require the Secure flag when SameSite=None.
 * - This middleware sets the config value and then adjusts any queued cookies
 *   on the response to ensure they have samesite="None".
 */
class ForceSessionSameSiteNone
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Memaksa konfigurasi session agar SameSite=None
        config(['session.same_site' => 'none']);

        // Proceed with the request and get response
        $response = $next($request);

        // If the response has cookies queued (Laravel uses Symfony cookies),
        // ensure their SameSite attribute is set to 'None' and Secure is true.
        if (method_exists($response, 'headers')) {
            $headers = $response->headers;

            // Symfony Response stores cookies in a CookieJar accessible via headers->getCookies()
            if (method_exists($headers, 'getCookies')) {
                $cookies = $headers->getCookies();

                foreach ($cookies as $cookie) {
                    // Only update Symfony Cookie instances
                    if ($cookie instanceof SymfonyCookie) {
                        // Symfony Cookie is immutable; create a new one with the same properties
                        $newCookie = new SymfonyCookie(
                            $cookie->getName(),
                            $cookie->getValue(),
                            $cookie->getExpiresTime(),
                            $cookie->getPath(),
                            $cookie->getDomain(),
                            true, // secure - required for SameSite=None in browsers
                            $cookie->isHttpOnly(),
                            false, // raw
                            'None' // sameSite attribute
                        );

                        // Remove the old cookie and set the new one
                        $headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
                        $headers->setCookie($newCookie);
                    }
                }
            }
        }

        return $response;
    }
}
