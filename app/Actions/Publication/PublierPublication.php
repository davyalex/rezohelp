<?php

namespace App\Actions\Publication;

use App\Enums\CleDroit;
use App\Enums\ParcoursPublication;
use App\Enums\StatutPublication;
use App\Enums\TypePublication;
use App\Events\PublicationPubliee;
use App\Jobs\EnrichirPublicationIA;
use App\Models\Entreprise;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Services\Droits;
use App\Services\RemplissagePublication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Publication par un membre (CONTEXTE §11.9) : tout se fait dans une transaction
 * (entreprise créée en ligne, publication, détails, médias, champs dénormalisés),
 * puis le statut est décidé par DeciderStatutPublication. Les effets de bord
 * (alertes, enrichissement IA) partent en file après la validation de la transaction.
 */
class PublierPublication
{
    public function __construct(
        private readonly RemplissagePublication $remplissage,
        private readonly AnalyserRisquePublication $analyserRisque,
        private readonly DeciderStatutPublication $decider,
        private readonly DetecterDoublon $detecterDoublon,
        private readonly AttribuerRoleAuteur $attribuerRole,
    ) {}

    /**
     * @param  array<string, mixed>  $donnees  données validées par la FormRequest du parcours
     * @param  list<UploadedFile>  $photos
     * @param  Publication|null  $brouillonExistant  brouillon serveur que l'on publie
     */
    public function executer(
        Utilisateur $auteur,
        ParcoursPublication $parcours,
        array $donnees,
        array $photos = [],
        bool $enBrouillon = false,
        ?Publication $brouillonExistant = null,
    ): Publication {
        $publication = DB::transaction(function () use ($auteur, $parcours, $donnees, $photos, $enBrouillon, $brouillonExistant): Publication {
            $entreprise = $this->remplissage->entreprise($auteur, $donnees);

            if (! $enBrouillon) {
                Droits::pour($entreprise ?? $auteur)->verifierOuEchouer(self::cleQuota($parcours));
                $this->detecterDoublon->verifierOuEchouer($auteur, (string) $donnees['titre'], $brouillonExistant?->id);
            }

            $publication = $brouillonExistant ?? new Publication;
            $this->remplissage->remplir($publication, $parcours, $donnees, $auteur, $entreprise);
            $publication->statut = StatutPublication::Brouillon;
            $publication->save();

            $this->remplissage->details($publication, $parcours, $donnees, $auteur);
            $this->remplissage->ajouterPhotos($publication, $photos);

            if (! $enBrouillon) {
                $this->soumettre($publication, $auteur, $entreprise);
            }

            return $publication;
        });

        if ($publication->statut !== StatutPublication::Brouillon) {
            EnrichirPublicationIA::dispatch($publication->id);
        }

        if ($publication->statut === StatutPublication::Publiee) {
            PublicationPubliee::dispatch($publication);
        }

        return $publication;
    }

    /**
     * Clé de quota consommée par une nouvelle publication du parcours.
     */
    public static function cleQuota(ParcoursPublication $parcours): CleDroit
    {
        return match ($parcours->type()) {
            TypePublication::Emploi => CleDroit::EmploiOffresMois,
            TypePublication::Service => CleDroit::ServiceOffresActives,
            TypePublication::Formation => CleDroit::FormationOffresActives,
        };
    }

    private function soumettre(Publication $publication, Utilisateur $auteur, ?Entreprise $entreprise): void
    {
        $detailEmploi = $publication->detailEmploi;

        $signaux = $this->analyserRisque->executer(
            $publication->type,
            $publication->titre,
            $publication->contenu,
            $detailEmploi?->type_contrat,
            $detailEmploi !== null ? ($detailEmploi->salaire_max ?? $detailEmploi->salaire_min) : null,
        );

        $statut = $this->decider->executer($auteur, $entreprise, $signaux, null, $publication->id);

        $publication->signaux_risque = $signaux;
        $publication->statut = $statut;

        if ($statut === StatutPublication::Publiee) {
            $publication->publiee_le = now();
            $publication->tri_le = now();
        }

        $publication->save();

        $this->attribuerRole->executer($auteur, $publication);
    }
}
