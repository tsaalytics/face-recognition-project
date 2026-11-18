<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ProtectBiometricRequests
{
    public static function applies(Request $request): bool
    {
        return $request->is('kiosk', 'kiosk/*', 'hr/kiosk', 'hr/kiosk/*', 'hr/employees/*/face');
    }

    public static function protect($response)
    {
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=()');

        return $response;
    }

    public function handle(Request $request, Closure $next)
    {
        if (! self::applies($request)) {
            return $next($request);
        }
        if ($request->isMethod('POST')) {
            $limit = $request->is('hr/kiosk/scan', 'kiosk/scan') ? 2 * 1024 * 1024 : 8 * 1024 * 1024;
            $length = $request->header('Content-Length');
            if (($length !== null && (! ctype_digit($length) || strlen($length) > 12 || (int) $length > $limit)) || strlen($request->getContent()) > $limit) {
                return self::protect(response()->json(['message' => 'Payload biometrik melebihi batas atau panjang tidak valid.'], 413));
            }
        }

        return self::protect($next($request));
    }
}
