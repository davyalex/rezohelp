<?php

namespace App\Http\Controllers\Espace;

use App\Actions\Commande\RemonterPublication;
use App\Actions\Publication\CloturerPublication;
use App\Actions\Publication\DupliquerPublication;
use App\Actions\Publication\ModifierPublication;
use App\Actions\Publication\ProlongerPublication;
use App\Enums\CleDroit;
use App\Enums\ParcoursPublication;
use App\Enums\StatutPublication;
use App\Enums\TypeMiseEnAvant;
use App\Enums\TypeProduit;
use App\Exceptions\OperationImpossibleException;
use App\Exceptions\PublicationImpossibleException;
use App\Exceptions\QuotaAtteintException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PublierController;
use App\Http\Requests\Espace\CloturerPublicationRequest;
use App\Http\Requests\Publication\PublierEmploiRequest;
use App\Http\Requests\Publication\PublierFormationRequest;
use App\Http\Requests\Publication\PublierPublicationRequest;
use App\Http\Requests\Publication\PublierServiceDemandeRequest;
use App\Http\Requests\Publication\PublierServiceOffreRequest;
use App\Models\Entreprise;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Services\Droits;
use App\Services\Offres;
use App\Services\RemplissagePublication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * « Mon espace → Mes publications » (CONTEXTE §11.7).
 */
class PublicationController extends Controller
{
    public const int PAR_PAGE = 15;

    public function index(Request $request, Offres $offres): View
    {
        $membre = $this->membre($request);
        $publications = Publication::query()
            ->where('auteur_id', $membre->id)
            ->select(['id', 'auteur_id', 'entreprise_id', 'type', 'sous_type', 'statut', 'titre', 'slug', 'motif_rejet', 'motif_rejet_detail', 'motif_cloture',
                'nb_vues', 'nb_jaime', 'nb_commentaires', 'nb_partages', 'nb_reponses', 'nb_prolongations', 'publiee_le', 'expire_le', 'cree_le'])
            ->with(['misesEnAvant' => fn ($requete) => $requete
                ->where('type', '!=', TypeMiseEnAvant::Remontee->value)
                ->where(fn ($r) => $r->whereNull('fin_le')->orWhere('fin_le', '>', now()->subDays(30)))
                ->latest('cree_le')])
            ->latest('cree_le')->latest('id')
            ->paginate(self::PAR_PAGE);

        // Droits de chaque titulaire présent sur la page : remontées incluses, statistiques avancées (§14.3).
        $titulaires = collect([$membre])->concat(
            Entreprise::query()->whereIn('id', $publications->pluck('entreprise_id')->filter()->unique())->get(['id', 'nom', 'proprietaire_id']),
        );
        $droits = $titulaires->mapWithKeys(function (Utilisateur|Entreprise $titulaire): array {
            $droits = Droits::pour($titulaire);

            return [$titulaire->getMorphClass().':'.$titulaire->getKey() => [
                'remontees' => $droits->restant(CleDroit::PublicationRemonteesMois),
                'statistiques' => $droits->peut(CleDroit::StatistiquesAvancees),
            ]];
        });

        return view('espace.publications.index', [
            'publications' => $publications,
            'droits' => $droits,
            'produits' => collect([TypeProduit::Remontee, TypeProduit::UneCommune, TypeProduit::SponsoriseFil])
                ->mapWithKeys(fn (TypeProduit $type): array => [$type->value => $offres->produit($type)])
                ->filter(),
        ]);
    }

    public function edit(Request $request, Publication $publication, RemplissagePublication $remplissage): View
    {
        $this->authorize('update', $publication);

        return view('publier.formulaire', PublierController::donneesFormulaire(
            $this->membre($request),
            ParcoursPublication::depuisPublication($publication->type, $publication->sous_type),
            $publication,
            $remplissage->valeursFormulaire($publication),
        ));
    }

    public function update(Publication $publication, ModifierPublication $modifier): RedirectResponse|View
    {
        $requete = $this->requete(ParcoursPublication::depuisPublication($publication->type, $publication->sous_type));
        $etaitBrouillon = $publication->statut === StatutPublication::Brouillon;

        try {
            $publication = $modifier->executer($publication, $requete->membre(), $requete->validated(), $requete->photos(), $requete->enBrouillon());
        } catch (QuotaAtteintException) {
            return PublierController::quotaAtteint($requete->membre(), $requete->parcours());
        } catch (PublicationImpossibleException $exception) {
            return back()->withInput()->withErrors(['titre' => $exception->getMessage()]);
        }

        // Un brouillon que l'on publie mène au même écran de confirmation qu'une nouvelle publication.
        if ($etaitBrouillon && $publication->statut !== StatutPublication::Brouillon) {
            return redirect()->route('publier.confirmation', $publication->id);
        }

        return redirect()->route('espace.publications.index')->with('statut', match ($publication->statut) {
            StatutPublication::Publiee => 'Modifications enregistrées : votre publication est en ligne.',
            StatutPublication::EnAttente => 'Modifications enregistrées : votre publication sera vérifiée avant d’être affichée.',
            StatutPublication::Brouillon => 'Brouillon enregistré.',
            default => 'Modifications enregistrées.',
        });
    }

    public function cloturer(CloturerPublicationRequest $request, Publication $publication, CloturerPublication $cloturer): RedirectResponse
    {
        $cloturer->executer($publication, $request->string('motif_cloture')->toString());

        return back()->with('statut', 'Publication clôturée : elle n’apparaît plus dans le fil.');
    }

    public function prolonger(Publication $publication, ProlongerPublication $prolonger): RedirectResponse
    {
        $this->authorize('prolonger', $publication);

        $prolonger->executer($publication);

        return back()->with('statut', 'Publication prolongée jusqu’au '.$publication->expire_le?->translatedFormat('j F Y').'.');
    }

    public function dupliquer(Publication $publication, DupliquerPublication $dupliquer): RedirectResponse
    {
        $this->authorize('dupliquer', $publication);

        $copie = $dupliquer->executer($publication);

        return redirect()->route('espace.publications.edit', $copie->id)
            ->with('statut', 'Copie créée en brouillon : modifiez-la puis publiez-la.');
    }

    /**
     * Remontée incluse dans la formule (publication.remontees_mois) ; au-delà, elle s'achète.
     */
    public function remonter(Request $request, Publication $publication, RemonterPublication $remonter): RedirectResponse
    {
        $this->authorize('mettreEnAvant', $publication);

        try {
            $remonter->incluse($publication, $publication->entreprise ?? $this->membre($request));
        } catch (QuotaAtteintException) {
            return back()->with('erreur', 'Vous avez utilisé toutes les remontées incluses dans votre formule ce mois-ci.');
        } catch (OperationImpossibleException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }

        return back()->with('statut', 'Votre publication est remontée en tête du fil.');
    }

    public function destroy(Publication $publication): RedirectResponse
    {
        $this->authorize('delete', $publication);

        $publication->delete();

        return redirect()->route('espace.publications.index')->with('statut', 'Publication supprimée.');
    }

    private function requete(ParcoursPublication $parcours): PublierPublicationRequest
    {
        return app(match ($parcours) {
            ParcoursPublication::Emploi => PublierEmploiRequest::class,
            ParcoursPublication::ServiceOffre => PublierServiceOffreRequest::class,
            ParcoursPublication::ServiceDemande => PublierServiceDemandeRequest::class,
            ParcoursPublication::Formation => PublierFormationRequest::class,
        });
    }

    private function membre(Request $request): Utilisateur
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof Utilisateur, 401);

        return $utilisateur;
    }
}
