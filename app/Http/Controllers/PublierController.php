<?php

namespace App\Http\Controllers;

use App\Actions\Publication\PublierPublication;
use App\Enums\CleDroit;
use App\Enums\ParcoursPublication;
use App\Enums\StatutPublication;
use App\Exceptions\PublicationImpossibleException;
use App\Exceptions\QuotaAtteintException;
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
use App\Services\RenduCarte;
use App\Services\TelephoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Parcours de publication en trois étapes (CONTEXTE §11.3) : choix du type,
 * formulaire (avec aperçu), puis enregistrement et confirmation.
 */
class PublierController extends Controller
{
    public function choix(): View
    {
        return view('publier.choix', [
            'autorisation' => Gate::inspect('create', Publication::class),
            'parcours' => ParcoursPublication::cases(),
        ]);
    }

    public function formulaire(Request $request, string $type): View
    {
        $parcours = ParcoursPublication::from($type);
        $membre = $this->membre($request);
        $autorisation = Gate::inspect('create', Publication::class);

        if ($autorisation->denied()) {
            return view('publier.refus', ['message' => $autorisation->message()]);
        }

        $restant = $this->quotaRestant($membre, $parcours);

        if ($restant === 0) {
            return self::quotaAtteint($membre, $parcours);
        }

        $donnees = $this->donneesFormulaire($membre, $parcours, null, [
            'telephone_contact' => $membre->telephone !== null ? app(TelephoneService::class)->formatNational($membre->telephone) : null,
            'afficher_telephone' => true,
            'whatsapp_meme_numero' => true,
            'nb_postes' => 1,
            'prix' => 0,
            'commune_id' => $membre->commune_id,
        ]);

        // « Il vous reste 1 offre gratuite ce mois-ci » (§14.11).
        return view('publier.formulaire', [...$donnees, 'dernierQuota' => $restant === 1]);
    }

    /**
     * Aperçu : la carte rendue par le vrai composant x-carte-publication (fragment HTML).
     */
    public function apercu(string $type, RemplissagePublication $remplissage, RenduCarte $rendu): Response
    {
        $requete = $this->requete(ParcoursPublication::from($type));
        $publication = $remplissage->apercu($requete->parcours(), $requete->validated(), $requete->membre());

        return response($rendu->rendre($publication))->header('Cache-Control', 'private, no-store');
    }

    public function store(string $type, PublierPublication $publier): RedirectResponse|View
    {
        $requete = $this->requete(ParcoursPublication::from($type));

        try {
            $publication = $publier->executer(
                $requete->membre(),
                $requete->parcours(),
                $requete->validated(),
                $requete->photos(),
                $requete->enBrouillon(),
            );
        } catch (QuotaAtteintException) {
            return $this->quotaAtteint($requete->membre(), $requete->parcours());
        } catch (PublicationImpossibleException $exception) {
            return back()->withInput()->withErrors(['titre' => $exception->getMessage()]);
        }

        if ($publication->statut === StatutPublication::Brouillon) {
            return redirect()->route('espace.publications.index')->with('statut', 'Brouillon enregistré. Vous pourrez le publier depuis « Mes publications ».');
        }

        return redirect()->route('publier.confirmation', $publication->id);
    }

    public function confirmation(Request $request, Publication $publication): View
    {
        abort_unless($publication->auteur_id === $this->membre($request)->id, 404);

        return view('publier.confirmation', ['publication' => $publication]);
    }

    /**
     * @param  array<string, mixed>  $valeurs
     * @return array<string, mixed>
     */
    public static function donneesFormulaire(Utilisateur $membre, ParcoursPublication $parcours, ?Publication $publication, array $valeurs): array
    {
        return [
            'parcours' => $parcours,
            'publication' => $publication,
            'valeurs' => $valeurs,
            'entreprises' => $membre->entreprises()->orderBy('nom')->get(['id', 'nom']),
            'photosMax' => max(0, Droits::pour($membre)->limite(CleDroit::PublicationPhotosMax)),
        ];
    }

    /**
     * Écran « Quota atteint » avec les options d'achat et d'essai pertinentes (§14.11).
     */
    public static function quotaAtteint(Utilisateur $membre, ParcoursPublication $parcours): View
    {
        $cle = PublierPublication::cleQuota($parcours);

        return view('publier.quota-atteint', [
            'parcours' => $parcours,
            'options' => $cle !== null ? app(Offres::class)->pourQuotaAtteint($membre, self::titulairesPossibles($membre, $parcours), $cle) : [],
        ]);
    }

    /**
     * Le formulaire s'affiche si au moins un titulaire possible (le membre ou une de ses
     * entreprises) a encore du quota pour ce type (§11.4) : plus grand reste, -1 = illimité.
     */
    private function quotaRestant(Utilisateur $membre, ParcoursPublication $parcours): int
    {
        $cle = PublierPublication::cleQuota($parcours);

        if ($cle === null) {
            return CleDroit::ILLIMITE;
        }

        $restants = self::titulairesPossibles($membre, $parcours)
            ->map(fn (Utilisateur|Entreprise $titulaire): int => Droits::pour($titulaire)->restant($cle));

        return $restants->contains(CleDroit::ILLIMITE) ? CleDroit::ILLIMITE : (int) $restants->max();
    }

    /**
     * @return Collection<int, Utilisateur|Entreprise>
     */
    private static function titulairesPossibles(Utilisateur $membre, ParcoursPublication $parcours): Collection
    {
        $titulaires = collect([$membre]);

        return $parcours === ParcoursPublication::ServiceOffre
            ? $titulaires
            : $titulaires->merge($membre->entreprises()->orderBy('nom')->get());
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
