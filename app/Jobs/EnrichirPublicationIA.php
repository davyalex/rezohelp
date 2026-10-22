<?php

namespace App\Jobs;

use App\Actions\Veille\DeciderPublicationVeille;
use App\Contracts\ClientIA;
use App\Donnees\DemandeEnrichissement;
use App\Donnees\ResultatEnrichissement;
use App\Enums\SourcePublication;
use App\Enums\StatutAppelIa;
use App\Enums\StatutPublication;
use App\Events\PublicationPubliee;
use App\Models\AppelIa;
use App\Models\Categorie;
use App\Models\Parametre;
use App\Models\Publication;
use App\Notifications\PublicationRemiseEnAttente;
use App\Services\CorrespondanceVeille;
use App\Services\Referentiels;
use App\Support\IdentifiantsPublication;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Score de fiabilité et signaux d'arnaque calculés par l'IA, en file (CONTEXTE §11.5, §12).
 *
 * - Publication d'un membre : le texte n'est jamais réécrit (D-038) ; une publication en ligne dont
 *   le score tombe sous seuil_remise_en_attente repasse en attente et son auteur est prévenu.
 * - Annonce de la veille : texte de carte et catégorie fine proposés par l'IA, puis mise en ligne
 *   automatique si DeciderPublicationVeille l'autorise ; sinon elle reste en attente de modération.
 *   Si l'IA échoue, l'annonce reste en attente.
 */
class EnrichirPublicationIA implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $publicationId) {}

    public function handle(ClientIA $client, DeciderPublicationVeille $decider, CorrespondanceVeille $correspondance): void
    {
        $publication = Publication::query()->with(['entreprise:id,nom'])->find($this->publicationId);

        if ($publication === null || $publication->statut === StatutPublication::Brouillon) {
            return;
        }

        $veille = $publication->source === SourcePublication::VeilleIa;
        $debut = hrtime(true);
        $resultat = $client->enrichir(new DemandeEnrichissement(
            type: $publication->type->value,
            titre: $publication->titre,
            contenu: $publication->contenu,
            entreprise: $publication->entreprise->nom ?? $publication->auteur_nom_affiche,
            lieu: app(Referentiels::class)->nomCommune($publication->commune_id),
            categoriesPossibles: Categorie::query()->actives()->where('type', $publication->type->value)->orderBy('ordre')->pluck('nom')->all(),
        ));

        $this->journaliser($publication, $resultat, (int) ((hrtime(true) - $debut) / 1_000_000));

        $publication->score_fiabilite = $resultat->scoreFiabilite;
        $publication->signaux_risque = array_values(array_unique([...($publication->signaux_risque ?? []), ...$resultat->signauxRisque]));

        if ($veille) {
            $this->enrichirAnnonceVeille($publication, $resultat, $correspondance);
        }

        $remiseEnAttente = $publication->statut === StatutPublication::Publiee
            && $resultat->scoreFiabilite < Parametre::entier('seuil_remise_en_attente');
        $miseEnLigne = $veille && $decider->peutPublier($publication);

        if ($remiseEnAttente) {
            $publication->statut = StatutPublication::EnAttente;
        } elseif ($miseEnLigne) {
            $decider->mettreEnLigne($publication);
        }

        $publication->save();

        if ($miseEnLigne) {
            PublicationPubliee::dispatch($publication);
        }

        if ($remiseEnAttente && ! $veille) {
            $publication->auteur?->notify(new PublicationRemiseEnAttente($publication));
        }
    }

    public function failed(?Throwable $erreur): void
    {
        $publication = Publication::query()->find($this->publicationId, ['id', 'import_veille_id']);

        AppelIa::query()->create([
            'publication_id' => $this->publicationId,
            'import_veille_id' => $publication?->import_veille_id,
            'modele' => (string) config('rezohelp.ia.driver'),
            'statut' => StatutAppelIa::Echec,
        ]);
    }

    /**
     * Texte de carte de l'IA (le texte source n'est jamais affiché en entier) et catégorie manquante.
     */
    private function enrichirAnnonceVeille(Publication $publication, ResultatEnrichissement $resultat, CorrespondanceVeille $correspondance): void
    {
        $texte = trim($resultat->texteCarte);

        if ($texte !== '') {
            $publication->extrait = IdentifiantsPublication::extrait($texte);
        }

        // La colonne « Domaine » de la veille fait foi ; l'IA ne fait que combler une catégorie introuvable.
        if ($publication->categorie_id === null && $resultat->categorie !== null) {
            $publication->categorie_id = $correspondance->categorieParNom($publication->type, $resultat->categorie);
        }
    }

    private function journaliser(Publication $publication, ResultatEnrichissement $resultat, int $dureeMs): void
    {
        if (! $resultat->appelExterne) {
            return;
        }

        $cout = ($resultat->tokensEntree * (int) config('services.claude.cout_entree_fcfa_par_million')
            + $resultat->tokensSortie * (int) config('services.claude.cout_sortie_fcfa_par_million')) / 1_000_000;

        AppelIa::query()->create([
            'publication_id' => $publication->id,
            'import_veille_id' => $publication->import_veille_id,
            'modele' => $resultat->modele,
            'tokens_entree' => $resultat->tokensEntree,
            'tokens_sortie' => $resultat->tokensSortie,
            'cout_estime_fcfa' => (int) ceil($cout),
            'duree_ms' => $resultat->dureeMs > 0 ? $resultat->dureeMs : $dureeMs,
            'statut' => StatutAppelIa::Succes,
        ]);
    }
}
