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
 * expire_le dépassé, ou jours_expiration_defaut jours après publiee_le si expire_le est nul.
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
            ->where('statut', StatutPublication::Publiee->value)
            ->where(fn ($requete) => $requete
                ->where('expire_le', '<=', now())
                ->orWhere(fn ($r) => $r->whereNull('expire_le')->where('publiee_le', '<=', now()->subDays(Parametre::entier('jours_expiration_defaut')))))
            ->lazyById(200)
            ->each(function (Publication $publication) use (&$expirees): void {
                $publication->statut = StatutPublication::Expiree;
                $publication->save();
                $expirees++;
            });

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

        $this->info("{$expirees} publication(s) expirée(s), {$prevenues} auteur(s) prévenu(s).");

        return self::SUCCESS;
    }
}
