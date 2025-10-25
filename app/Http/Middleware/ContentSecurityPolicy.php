<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Content Security Policy (CSP) Middleware
 * 
 * Adds CSP headers to protect against XSS, clickjacking, and other code injection attacks.
 * Updated to include all external resources used by the application.
 * 
 * Configuration via .env:
 * - CSP_ENABLED: Enable/disable CSP (default: true)
 * - CSP_REPORT_ONLY: Use report-only mode for testing (default: false)
 * - CSP_REPORT_URI: URI to send violation reports (optional)
 * 
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP
 */
class ContentSecurityPolicy
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
        $response = $next($request);

        // Skip CSP for non-HTML responses
        $contentType = $response->headers->get('Content-Type', '');
        if (!str_contains($contentType, 'text/html') && !empty($contentType)) {
            return $response;
        }

        // Check if CSP is enabled
        if (!env('CSP_ENABLED', true)) {
            return $response;
        }

        // Build CSP policy
        $policy = $this->buildPolicy();

        // Choose header based on report-only mode
        $headerName = env('CSP_REPORT_ONLY', false) 
            ? 'Content-Security-Policy-Report-Only' 
            : 'Content-Security-Policy';

        // Add CSP header
        $response->headers->set($headerName, $policy);

        // Add X-Content-Type-Options to prevent MIME sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Add X-Frame-Options for older browsers
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $response;
    }

    /**
     * Build the CSP policy string
     *
     * @return string
     */
    protected function buildPolicy(): string
    {
        $directives = [
            // Default: only allow from same origin
            "default-src" => "'self'",
            
            // Scripts: self + CDN + inline scripts (needed for Laravel/Blade)
            "script-src" => implode(' ', [
                "'self'",
                "'unsafe-inline'",  // Needed for inline <script> tags in Blade
                "'unsafe-eval'",    // Needed for some JS frameworks
                "https://cdnjs.cloudflare.com",       // CDN for various libraries
                "https://code.jquery.com",             // jQuery
                "https://cdn.jsdelivr.net",            // jsDelivr CDN (SweetAlert2, Chart.js, etc.)
                "https://maxcdn.bootstrapcdn.com",     // Bootstrap
                "https://kit.fontawesome.com",         // Font Awesome
                "https://unpkg.com",                   // Unpkg CDN (Popper.js, Tippy.js)
                "https://www.google.com",              // reCAPTCHA
                "https://www.gstatic.com",             // reCAPTCHA
                "https://www.recaptcha.net",           // reCAPTCHA alternative domain
                "https://www.clarity.ms",               //Clarity
                "https://scripts.clarity.ms",
            ]),
            
            // Styles: self + inline styles (needed for Blade and CSS-in-JS)
            "style-src" => implode(' ', [
                "'self'",
                "'unsafe-inline'",  // Needed for inline styles
                "https://cdnjs.cloudflare.com",
                "https://cdn.jsdelivr.net",           // jsDelivr CDN
                "https://maxcdn.bootstrapcdn.com",    // Bootstrap
                "https://fonts.googleapis.com",       // Google Fonts
                "https://unpkg.com",                  // Unpkg CDN
            ]),
            
            // Images: self + data URIs + external sources
            "img-src" => implode(' ', [
                "'self'",
                "data:",    // For inline images
                "https:",   // Allow HTTPS images (for user avatars, CDN icons, etc.)
                "blob:",    // For dynamically generated images
            ]),
            
            // Fonts: self + data URIs + CDN
                "font-src" => implode(' ', [
                    "'self'",
                    "data:",
                    "https://cdnjs.cloudflare.com",
                    "https://fonts.gstatic.com",          // Google Fonts
                    "https://kit.fontawesome.com",        // Font Awesome Kit
                    "https://ka-f.fontawesome.com",       // Font Awesome CDN (actual font files)
                    "https://maxcdn.bootstrapcdn.com",    // Bootstrap icons/fonts
                    "https://cdn.jsdelivr.net",           // jsDelivr CDN
                    "https://unpkg.com",                  // Unpkg CDN
                ]),
            
            // AJAX/Fetch: self + API endpoints
            "connect-src" => implode(' ', [
                "'self'",
                "https://www.google.com",              // reCAPTCHA
                "https://www.gstatic.com",             // reCAPTCHA
                "https://www.recaptcha.net",           // reCAPTCHA alternative
                "https://cdnjs.cloudflare.com",        // CDN resources
                "https://cdn.jsdelivr.net",            // jsDelivr CDN
                "https://ka-f.fontawesome.com",        // Font Awesome analytics
                "https://c.clarity.ms",                // MS Clarity
                "https://l.clarity.ms/collect",
            ]),
            
            // Frames: self only + reCAPTCHA
            "frame-src" => implode(' ', [
                "'self'",
                "https://www.google.com",              // reCAPTCHA
                "https://www.recaptcha.net",           // reCAPTCHA alternative
            ]),
            
            // Objects: none (blocks Flash, Java, etc.)
            "object-src" => "'none'",
            
            // Base URI: self only (prevents <base> tag injection)
            "base-uri" => "'self'",
            
            // Form actions: self only
            "form-action" => "'self'",
            
            // Frame ancestors: self only (prevents clickjacking)
            "frame-ancestors" => "'self'",
        ];

        // Only add upgrade-insecure-requests in production (not in report-only mode)
        if (!env('CSP_REPORT_ONLY', false) && app()->environment('production')) {
            $directives['upgrade-insecure-requests'] = "";
        }

        // Add report URI if configured
        if ($reportUri = env('CSP_REPORT_URI')) {
            $directives['report-uri'] = $reportUri;
        }

        // Build policy string
        $policy = [];
        foreach ($directives as $directive => $value) {
            if (empty($value)) {
                $policy[] = $directive;
            } else {
                $policy[] = "{$directive} {$value}";
            }
        }

        return implode('; ', $policy);
    }
}