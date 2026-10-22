<?php

namespace App\Console\Commands;

use App\Enums\SourcePublication;
use App\Enums\StatutPublication;
use App\Models\Parametre;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Notifications\PublicationExpireBientot;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Expiration des publications (CONTEXTE §13.3.C), planifiée chaque jour à 02h00 :
 * expire_le dépassé, date limite de candidature passée, ou jours_expiration_defaut jours après
 * publiee_le si expire_le est nul. Les annonces de la veille expirées sont supprimées
 * jours_suppression_veille jours plus tard.
 * Chaque passage en « expiree » passe par l'observer (fil:version, mises en avant suspendues).
 * Prévient aussi les auteurs dont la publication expire dans 3 jours (§11.7).
 */
class ExpirerPublications extends Command
{
    protected $signature = 'publications:expirer';

    protected $description = 'Expire les publications arrivées à échéance et prévient celles qui expirent dans 3 jours';

    public const int JOURS_PREAVIS = 3;

    public function handle(): int
    {
        $expirees = 0;

        Publication::query()
            ->where(fn ($requete) => $requete
                ->where('statut', StatutPublication::Publiee->value)
                // Une annonce de la veille close pendant sa modération ne reste pas dans la file.
                ->orWhere(fn ($r) => $r->where('statut', StatutPublication::EnAttente->value)->where('source', SourcePublication::VeilleIa->value)))
            ->where(fn ($requete) => $requete
                ->where('expire_le', '<=', now())
                ->orWhere(fn ($r) => $r->whereNull('expire_le')->where('publiee_le', '<=', now()->subDays(Parametre::entier('jours_expiration_defaut'))))
                // Offre d'emploi dont la date limite de candidature est passée.
                ->orWhereHas('detailEmploi', fn ($r) => $r->where('date_limite', '<', Carbon::today())))
            ->lazyById(200)
            ->each(function (Publication $publication) use (&$expirees): void {
                $publication->statut = StatutPublication::Expiree;
                $publication->save();
                $expirees++;
            });

        $supprimees = $this->supprimerVeilleExpiree();

        // Fenêtre d'un jour : la commande quotidienne prévient chaque publication une seule fois.
        $debut = Carbon::today()->addDays(self::JOURS_PREAVIS);
        $prevenues = 0;

        Publication::query()
            ->where('statut', StatutPublication::Publiee->value)
            ->where('source', SourcePublication::Utilisateur->value)
            ->whereNotNull('auteur_id')
            ->whereBetween('expire_le', [$debut, $debut->copy()->endOfDay()])
            ->with('auteur')
            ->lazyById(200)
            ->each(function (Publication $publication) use (&$prevenues): void {
                if ($publication->auteur instanceof Utilisateur) {
                    $publication->auteur->notify(new PublicationExpireBientot($publication));
                    $prevenues++;
                }
            });

        $this->info("{$expirees} publication(s) expirée(s), {$supprimees} annonce(s) de la veille supprimée(s), {$prevenues} auteur(s) prévenu(s).");

        return self::SUCCESS;
    }

    /**
     * Annonces de la veille expirées depuis jours_suppression_veille jours (5 par défaut, D-067) :
     * supprimées (corbeille, puis purge par publications:purger). 0 désactive la suppression.
     * Les publications des membres restent : leur auteur peut les prolonger.
     */
    private function supprimerVeilleExpiree(): int
    {
        $jours = Parametre::entier('jours_suppression_veille');

        if ($jours <= 0) {
            return 0;
        }

        $supprimees = 0;

        Publication::query()
            ->where('source', SourcePublication::VeilleIa->value)
            ->where('statut', StatutPublication::Expiree->value)
            ->where(fn ($requete) => $requete
                ->where('expire_le', '<=', now()->subDays($jours))
                ->orWhereHas('detailEmploi', fn ($r) => $r->where('date_limite', '<', Carbon::today()->subDays($jours))))
            ->lazyById(200)
            ->each(function (Publication $publication) use (&$supprimees): void {
                $publication->delete();
                $supprimees++;
            });

        return $supprimees;
    }
}
