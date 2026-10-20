<?php

it('affiche la page d’accueil en français', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<html lang="fr">', false)
        ->assertSee('Rezohelp')
        ->assertSee('Aller au contenu');
});

it('ne charge pas le bundle du back-office sur le site public', function () {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('admin.js')
        ->assertDontSee('jquery', false);
});
