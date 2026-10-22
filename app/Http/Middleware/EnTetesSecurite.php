<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité de toutes les pages (CONTEXTE §16) : CSP, HSTS en HTTPS, protection contre
 * l'intégration dans une autre page, le reniflage de type MIME et les fuites de référent.
 *
 * - Scripts : uniquement ceux du site, avec le nonce posé par Vite ; 'unsafe-eval' reste nécessaire
 *   à Alpine, qui évalue les expressions x-data / @click (D-062).
 * - Styles : 'unsafe-inline' pour les attributs style (reçus, SweetAlert2, x-show).
 * - Formulaires : le site, et le guichet CinetPay vers lequel une commande redirige.
 */
class EnTetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $reponse = $next($request);

        $reponse->headers->set('Content-Security-Policy', $this->politique($request));
        $reponse->headers->set('X-Frame-Options', 'DENY');
        $reponse->headers->set('X-Content-Type-Options', 'nosniff');
        $reponse->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $reponse->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $reponse->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $reponse->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $reponse;
    }

    private function politique(Request $request): string
    {
        $vite = $this->serveurVite();
        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-".Vite::cspNonce()."'", "'unsafe-eval'", ...$vite],
            'style-src' => ["'self'", "'unsafe-inline'", ...$vite],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'", ...$vite, ...array_map(fn (string $origine): string => (string) preg_replace('#^http#', 'ws', $origine), $vite)],
            'manifest-src' => ["'self'"],
            'worker-src' => ["'self'"],
            'form-action' => ["'self'", 'https://*.cinetpay.com'],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        $politique = implode('; ', array_map(fn (string $nom, array $sources): string => $nom.' '.implode(' ', $sources), array_keys($directives), $directives));

        return $request->isSecure() ? $politique.'; upgrade-insecure-requests' : $politique;
    }

    /**
     * Serveur de développement Vite (npm run dev) : autorisé seulement quand il tourne.
     *
     * @return list<string>
     */
    private function serveurVite(): array
    {
        $fichier = Vite::hotFile();

        if (! Vite::isRunningHot() || ! is_file($fichier)) {
            return [];
        }

        $parties = parse_url(trim((string) file_get_contents($fichier)));

        if (! is_array($parties) || ! isset($parties['scheme'], $parties['host'])) {
            return [];
        }

        return [$parties['scheme'].'://'.$parties['host'].(isset($parties['port']) ? ':'.$parties['port'] : '')];
    }
}
