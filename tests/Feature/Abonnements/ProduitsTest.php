<?php

use App\Actions\Commande\ActiverCommande;
use App\Actions\Commande\CreerCommande;
use App\Enums\CleDroit;
use App\Enums\StatutPublication;
use App\Enums\TypeMiseEnAvant;
use App\Exceptions\OperationImpossibleException;
use App\Models\Abonnement;
use App\Models\Credit;
use App\Models\Entreprise;
use App\Models\MiseEnAvant;
use App\Models\Plan;
use App\Models\Produit;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Services\Droits;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    referentielsPublication();
    Notification::fake();
    Produit::query()->update(['actif' => true]);
    $this->membre = Utilisateur::factory()->create();
    $this->publication = Publication::factory()->serviceOffre()->publiee(now()->subDays(3))->create(['auteur_id' => $this->membre->id]);
});

function produit(string $code): Produit
{
    return Produit::query()->where('code', $code)->firstOrFail();
}

function payer(Utilisateur $acheteur, Utilisateur|Entreprise $titulaire, Produit|Plan $article, ?Publication $publication = null): void
{
    $commande = app(CreerCommande::class)->executer($acheteur, $titulaire, $article, $publication);
    app(ActiverCommande::class)->executer($commande, 'wave');
}

it('remonte une publication payée en tête du fil', function () {
    $this->travelTo(now()->addMinute());
    payer($this->membre, $this->membre, produit('remontee'), $this->publication);

    expect($this->publication->refresh()->tri_le?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and(MiseEnAvant::query()->sole()->type)->toBe(TypeMiseEnAvant::Remontee);
});

it('refuse une remontée moins de 24 h après la précédente', function () {
    payer($this->membre, $this->membre, produit('remontee'), $this->publication);

    expect(fn () => app(CreerCommande::class)->executer($this->membre, $this->membre, produit('remontee'), $this->publication))
        ->toThrow(OperationImpossibleException::class, 'moins de 24 heures');
});

it('refuse de mettre en avant une publication qui n’est pas en ligne ou pas à soi', function () {
    $enAttente = Publication::factory()->serviceOffre()->enAttente()->create(['auteur_id' => $this->membre->id]);
    $autre = Publication::factory()->serviceOffre()->publiee()->create();

    expect(fn () => app(CreerCommande::class)->executer($this->membre, $this->membre, produit('sponsorise_fil_7j'), $enAttente))
        ->toThrow(OperationImpossibleException::class, 'en ligne')
        ->and(fn () => app(CreerCommande::class)->executer($this->membre, $this->membre, produit('une_commune_7j'), $autre))
        ->toThrow(OperationImpossibleException::class, 'vos publications');
});

it('crée la mise en avant pour la durée du produit, dans la commune de la publication', function () {
    payer($this->membre, $this->membre, produit('une_commune_7j'), $this->publication);

    $mise = MiseEnAvant::query()->sole();
    expect($mise->type)->toBe(TypeMiseEnAvant::UneCommune)
        ->and($mise->commune_id)->toBe($this->publication->commune_id)
        ->and($mise->fin_le?->toDateString())->toBe(now()->addDays(7)->toDateString())
        ->and($mise->suspendue_le)->toBeNull();
});

it('garde la mise en avant en pause si la publication est sortie du fil avant le paiement', function () {
    $commande = app(CreerCommande::class)->executer($this->membre, $this->membre, produit('sponsorise_fil_7j'), $this->publication);
    $this->publication->forceFill(['statut' => StatutPublication::EnAttente])->save();

    app(ActiverCommande::class)->executer($commande, 'orange');

    expect(MiseEnAvant::query()->sole()->suspendue_le)->not->toBeNull();
});

it('applique une remontée payée au retour en ligne de la publication', function () {
    $commande = app(CreerCommande::class)->executer($this->membre, $this->membre, produit('remontee'), $this->publication);
    $this->publication->forceFill(['statut' => StatutPublication::EnAttente])->save();
    app(ActiverCommande::class)->executer($commande, 'orange');

    expect(MiseEnAvant::query()->sole()->suspendue_le)->not->toBeNull();

    $this->travel(1)->hours();
    $this->publication->forceFill(['statut' => StatutPublication::Publiee])->save();

    expect(MiseEnAvant::query()->sole()->suspendue_le)->toBeNull()
        ->and($this->publication->refresh()->tri_le?->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('crédite une offre d’emploi supplémentaire au titulaire', function () {
    parametre('quotas_actifs', '1');
    $entreprise = Entreprise::factory()->create(['proprietaire_id' => $this->membre->id]);
    Publication::factory()->emploi()->publiee()->count(3)->create(['auteur_id' => $this->membre->id, 'entreprise_id' => $entreprise->id]);
    expect(Droits::pour($entreprise)->restant(CleDroit::EmploiOffresMois))->toBe(0);

    payer($this->membre, $entreprise, produit('offre_emploi_unite'));

    expect(Credit::query()->sole()->titulaire_id)->toBe($entreprise->id)
        ->and(Droits::pour($entreprise)->restant(CleDroit::EmploiOffresMois))->toBe(1);
});

it('remonte gratuitement dans la limite des remontées incluses, puis bloque', function () {
    abonnerArtisanPro($this->membre);

    $this->actingAs($this->membre)->post(route('espace.publications.remonter', $this->publication))
        ->assertSessionHas('statut', 'Votre publication est remontée en tête du fil.');
    expect(MiseEnAvant::query()->sole()->commande_id)->toBeNull()
        ->and(Droits::pour($this->membre)->restant(CleDroit::PublicationRemonteesMois))->toBe(3);

    // Une seconde remontée le même jour est refusée (délai de 24 h).
    $this->post(route('espace.publications.remonter', $this->publication))->assertSessionHas('erreur');
});

it('refuse la remontée incluse sans formule qui la prévoit', function () {
    $this->actingAs($this->membre)->post(route('espace.publications.remonter', $this->publication))
        ->assertSessionHas('erreur', 'Vous avez utilisé toutes les remontées incluses dans votre formule ce mois-ci.');

    expect(MiseEnAvant::query()->count())->toBe(0);
});

it('réserve la remontée à l’auteur', function () {
    $this->actingAs(Utilisateur::factory()->create())->post(route('espace.publications.remonter', $this->publication))->assertForbidden();
});

function abonnerArtisanPro(Utilisateur $membre): void
{
    Abonnement::factory()->create([
        'titulaire_id' => $membre->id,
        'plan_id' => Plan::query()->where('code', 'artisan_pro_30j')->firstOrFail()->id,
    ]);
}
