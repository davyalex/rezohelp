<?php

namespace App\Jobs;

use App\Contracts\ClientIA;
use App\Donnees\DemandeEnrichissement;
use App\Donnees\ResultatEnrichissement;
use App\Enums\SourcePublication;
use App\Enums\StatutAppelIa;
use App\Enums\StatutPublication;
use App\Models\AppelIa;
use App\Models\Categorie;
use App\Models\Parametre;
use App\Models\Publication;
use App\Notifications\PublicationRemiseEnAttente;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Score de fiabilité et signaux d'arnaque calculés par l'IA, après l'envoi (CONTEXTE §11.5).
 * Une publication en ligne dont le score tombe sous seuil_remise_en_attente
 * repasse en attente et son auteur est prévenu.
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

    public function handle(ClientIA $client): void
    {
        $publication = Publication::query()->with(['entreprise:id,nom'])->find($this->publicationId);

        if ($publication === null || $publication->statut === StatutPublication::Brouillon) {
            return;
        }

        $debut = hrtime(true);
        $resultat = $client->enrichir(new DemandeEnrichissement(
            type: $publication->type->value,
            titre: $publication->titre,
            contenu: $publication->contenu,
            entreprise: $publication->entreprise->nom ?? $publication->auteur_nom_affiche,
            lieu: null,
            categoriesPossibles: Categorie::query()->where('type', $publication->type->value)->whereNull('parent_id')->pluck('nom')->all(),
        ));

        $this->journaliser($publication, $resultat, (int) ((hrtime(true) - $debut) / 1_000_000));

        $publication->score_fiabilite = $resultat->scoreFiabilite;
        $publication->signaux_risque = array_values(array_unique([...($publication->signaux_risque ?? []), ...$resultat->signauxRisque]));

        $remiseEnAttente = $publication->statut === StatutPublication::Publiee
            && $resultat->scoreFiabilite < Parametre::entier('seuil_remise_en_attente');

        if ($remiseEnAttente) {
            $publication->statut = StatutPublication::EnAttente;
        }

        $publication->save();

        if ($remiseEnAttente && $publication->source === SourcePublication::Utilisateur) {
            $publication->auteur?->notify(new PublicationRemiseEnAttente($publication));
        }
    }

    public function failed(?Throwable $erreur): void
    {
        AppelIa::query()->create([
            'publication_id' => $this->publicationId,
            'modele' => (string) config('rezohelp.ia.driver'),
            'statut' => StatutAppelIa::Echec,
        ]);
    }

    private function journaliser(Publication $publication, ResultatEnrichissement $resultat, int $dureeMs): void
    {
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
