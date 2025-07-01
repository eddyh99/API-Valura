<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class SecurityHeaders implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Nothing needed before
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // HTTP Strict Transport Security: 2 years, include subdomains, preload
        $response->setHeader('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');

        // Prevent clickjacking
        $response->setHeader('X-Frame-Options', 'DENY');

        // Prevent MIME-type sniffing
        $response->setHeader('X-Content-Type-Options', 'nosniff');

        // Referrer policy
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions policy (formerly Feature-Policy)
        $response->setHeader('Permissions-Policy', implode(', ', [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=()'
        ]));

        // Expect CT for Certificate Transparency enforcement
        $response->setHeader('Expect-CT', 'max-age=86400, enforce, report-uri="/ct-report-endpoint"');

        // Cross-Origin isolation for advanced security
        $response->setHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->setHeader('Cross-Origin-Embedder-Policy', 'require-corp');

        return $response;
    }
}
