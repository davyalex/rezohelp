<?php

use App\Models\Administrateur;

beforeEach(function () {
    referentielsPublication();
    $this->admin = Administrateur::factory()->administrateur()->create();
    $this->moderateur = Administrateur::factory()->moderateur()->create();
});

it('regroupe les liens de la barre latérale et marque la page active', function () {
    $html = (string) $this->actingAs($this->admin, 'admin')->get(route('admin.plans.index'))->assertOk()
        ->assertSeeInOrder(['Contenu', 'Membres du site', 'Veille IA', 'Monétisation', 'Configuration'])
        ->assertSee('Menu du compte')
        ->assertSee('Se déconnecter')
        ->getContent();

    preg_match_all('/<a href="([^"]+)" data-lien-menu\s+aria-current="page"/', $html, $actifs);
    expect($actifs[1])->toBe([route('admin.plans.index')]);
});

it('masque les groupes dont le modérateur n’a aucune permission', function () {
    $this->actingAs($this->moderateur, 'admin')->get(route('admin.tableau-de-bord'))->assertOk()
        ->assertSee('Contenu')
        ->assertDontSee('Monétisation')
        ->assertDontSee('Configuration');
});

it('rend la barre latérale réduite et les groupes repliés depuis les cookies, sans attendre le JS', function () {
    $html = (string) $this->actingAs($this->admin, 'admin')
        ->withUnencryptedCookies(['admin_menu_reduit' => '1', 'admin_menu_groupes' => 'configuration'])
        ->get(route('admin.tableau-de-bord'))->assertOk()
        ->getContent();

    expect($html)->toMatch('/<body[^>]*\sdata-reduit[\s>]/')
        // Groupe replié : hauteur 0 et liens retirés du parcours clavier (« invisible »).
        ->toMatch('/id="groupe-configuration"\s+class="[^"]*grid-rows-\[0fr\] invisible/')
        ->toMatch('/id="groupe-contenu"\s+class="[^"]*grid-rows-\[1fr\] visible/');
});
