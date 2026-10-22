<?php

use App\Models\Utilisateur;

beforeEach(fn () => referentielsPublication());

it('envoie les en-têtes de sécurité sur les pages publiques', function () {
    $reponse = $this->get(route('fil.index'))->assertOk();
    $csp = (string) $reponse->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'self'")
        ->and($csp)->toContain("frame-ancestors 'none'")
        ->and($csp)->toContain("object-src 'none'")
        ->and($csp)->toContain("base-uri 'self'")
        ->and($csp)->toContain("form-action 'self' https://*.cinetpay.com")
        ->and($csp)->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9]+'/")
        ->and($reponse->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($reponse->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($reponse->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($reponse->headers->get('Permissions-Policy'))->toContain('geolocation=()')
        // HSTS seulement en HTTPS.
        ->and($reponse->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('ajoute HSTS et upgrade-insecure-requests en HTTPS', function () {
    $reponse = $this->get('https://localhost/')->assertOk();

    expect($reponse->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains')
        ->and((string) $reponse->headers->get('Content-Security-Policy'))->toContain('upgrade-insecure-requests');
});

it('n’autorise jamais la mise en cache de « Mon espace », de la publication ni des paiements', function (string $route) {
    $membre = Utilisateur::factory()->create();

    $reponse = $this->actingAs($membre)->get(route($route))->assertOk();

    expect((string) $reponse->headers->get('Cache-Control'))->toContain('no-store');
})->with(['espace.index', 'espace.notifications', 'alertes.index', 'espace.publications.index', 'espace.abonnement', 'publier.choix']);

it('sert une page hors ligne sans donnée de compte ni jeton', function () {
    $membre = Utilisateur::factory()->create(['nom' => 'Kouassi']);

    $page = $this->actingAs($membre)->get(route('hors-ligne'))->assertOk();

    $page->assertSee('Vous êtes hors connexion')
        ->assertDontSee('Kouassi')
        ->assertDontSee('csrf-token', false)
        ->assertDontSee('resources/js/app.js', false);
});

it('déclare le manifeste et l’icône de l’application', function () {
    $this->get(route('fil.index'))
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
        ->assertSee('id="invitation-installation"', false);

    $manifeste = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifeste['lang'])->toBe('fr')
        ->and($manifeste['display'])->toBe('standalone')
        ->and($manifeste['start_url'])->toBe('/')
        ->and(array_column($manifeste['icons'], 'sizes'))->toContain('192x192', '512x512')
        ->and(collect($manifeste['icons'])->contains('purpose', 'maskable'))->toBeTrue();

    foreach ($manifeste['icons'] as $icone) {
        [$largeur] = getimagesize(public_path(ltrim($icone['src'], '/')));
        expect($icone['sizes'])->toBe($largeur.'x'.$largeur);
    }
});
