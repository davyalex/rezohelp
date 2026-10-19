<?php

namespace App\Actions\Publication;

use App\Enums\ConfianceUtilisateur;
use App\Enums\ParcoursPublication;
use App\Enums\StatutPublication;
use App\Jobs\EnrichirPublicationIA;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Services\RemplissagePublication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Modification d'une publication par son auteur (CONTEXTE §11.7) :
 *  - auteur non fiable : changer le titre, le contenu ou les contacts renvoie en attente ;
 *  - auteur fiable : la publication reste en ligne, l'analyse de risque est relancée
 *    (un signal la renvoie en attente) ;
 *  - publication rejetée : corrigée, elle est renvoyée en attente ;
 *  - brouillon : il est publié comme une nouvelle publication.
 */
class ModifierPublication
{
    /** Champs dont la modification impose une nouvelle vérification. */
    private const array CHAMPS_SENSIBLES = ['titre', 'contenu'];

    private const array CONTACTS_SENSIBLES = ['contact_candidature', 'telephone_contact', 'whatsapp', 'contact_inscription'];

    public function __construct(
        private readonly RemplissagePublication $remplissage,
        private readonly AnalyserRisquePublication $analyserRisque,
        private readonly PublierPublication $publier,
    ) {}

    /**
     * @param  array<string, mixed>  $donnees
     * @param  list<UploadedFile>  $photos
     */
    public function executer(Publication $publication, Utilisateur $auteur, array $donnees, array $photos = [], bool $enBrouillon = false): Publication
    {
        $parcours = ParcoursPublication::depuisPublication($publication->type, $publication->sous_type);

        if ($publication->statut === StatutPublication::Brouillon) {
            return $this->publier->executer($auteur, $parcours, $donnees, $photos, $enBrouillon, $publication);
        }

        $publication = DB::transaction(function () use ($publication, $auteur, $donnees, $photos, $parcours): Publication {
            $contactsAvant = $this->contacts($publication);
            $entreprise = $this->remplissage->entreprise($auteur, $donnees);

            $this->remplissage->remplir($publication, $parcours, $donnees, $auteur, $entreprise);
            $sensible = $publication->isDirty(self::CHAMPS_SENSIBLES);
            $publication->save();

            $this->remplissage->details($publication, $parcours, $donnees, $auteur);
            $this->remplissage->ajouterPhotos($publication, $photos, remplacer: true);
            $sensible = $sensible || $contactsAvant !== $this->contacts($publication);

            $detailEmploi = $publication->detailEmploi;
            $publication->signaux_risque = $this->analyserRisque->executer(
                $publication->type,
                $publication->titre,
                $publication->contenu,
                $detailEmploi?->type_contrat,
                $detailEmploi !== null ? ($detailEmploi->salaire_max ?? $detailEmploi->salaire_min) : null,
            );

            if ($this->doitRepasserEnAttente($publication, $auteur, $sensible)) {
                $publication->statut = StatutPublication::EnAttente;
                $publication->motif_rejet = null;
                $publication->motif_rejet_detail = null;
            }

            $publication->save();

            return $publication;
        });

        EnrichirPublicationIA::dispatch($publication->id);

        return $publication;
    }

    /**
     * Le statut ne peut que repasser en attente : la mise en ligne reste du ressort
     * de DeciderStatutPublication ou de la modération.
     */
    private function doitRepasserEnAttente(Publication $publication, Utilisateur $auteur, bool $sensible): bool
    {
        return match ($publication->statut) {
            StatutPublication::Rejetee => true,
            StatutPublication::Publiee => $auteur->confiance === ConfianceUtilisateur::Fiable
                ? ($publication->signaux_risque ?? []) !== []
                : $sensible,
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function contacts(Publication $publication): array
    {
        $publication->load(['detailEmploi', 'detailService', 'detailFormation']);
        $detail = $publication->detailEmploi ?? $publication->detailService ?? $publication->detailFormation;

        return $detail !== null ? $detail->only(self::CONTACTS_SENSIBLES) : [];
    }
}
